<?php

namespace Tests\Feature;

use App\Central\Enums\TenantStatus;
use App\Central\Models\Tenant;
use App\Central\Provisioning\TenantDatabaseProvisioner;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\ProvisionsTenants;
use Tests\TestCase;

/**
 * Runs against a real MySQL server: tenant databases and users are created
 * and dropped for every test (CREATE DATABASE cannot run inside the
 * transaction RefreshDatabase uses).
 */
class TenantProvisioningTest extends TestCase
{
    use DatabaseMigrations, ProvisionsTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('modules:sync');
        $this->seed(PlanSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }

    #[Test]
    public function provisioning_creates_a_database_with_its_own_user_and_core_tables(): void
    {
        $tenant = $this->provisionTenant('alpha');

        $this->assertSame('erp_test_t_'.$tenant->id, $tenant->db_name);
        $this->assertSame('ut_'.$tenant->id, $tenant->db_username);
        $this->assertTrue(app(TenantDatabaseProvisioner::class)->databaseExists($tenant));

        $tenant->run(function () {
            $this->assertTrue(Schema::hasTable('users'));
            $this->assertTrue(Schema::hasTable('installed_modules'));
        });
    }

    #[Test]
    public function the_database_password_is_encrypted_at_rest(): void
    {
        $tenant = $this->provisionTenant('alpha');
        $raw = DB::connection('central')->table('tenants')->where('id', $tenant->id)->value('db_password');

        $this->assertNotSame($tenant->db_password, $raw);
        $this->assertSame(32, strlen($tenant->db_password));
        $this->assertArrayNotHasKey('db_password', $tenant->toArray());
    }

    #[Test]
    public function tenant_queries_run_as_the_tenant_user_not_the_provisioner(): void
    {
        $tenant = $this->provisionTenant('alpha');

        $tenant->run(function () use ($tenant) {
            $current = DB::selectOne('SELECT CURRENT_USER() AS u')->u;
            $this->assertStringStartsWith($tenant->db_username.'@', $current);
            $this->assertSame($tenant->db_name, DB::connection()->getDatabaseName());
        });

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('erp_central_test', DB::connection()->getDatabaseName());
    }

    #[Test]
    public function a_tenant_user_cannot_read_another_tenant_or_the_central_database(): void
    {
        $alpha = $this->provisionTenant('alpha');
        $beta = $this->provisionTenant('beta');

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', config('database.connections.provisioner.host'), config('database.connections.provisioner.port')),
            $alpha->db_username,
            $alpha->db_password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM `{$alpha->db_name}`.users")->fetchColumn());

        foreach (["`{$beta->db_name}`.users", '`erp_central_test`.tenants'] as $table) {
            try {
                $pdo->query("SELECT COUNT(*) FROM {$table}");
                $this->fail("Tenant alpha could read {$table}");
            } catch (PDOException $e) {
                $this->assertStringContainsString('denied', $e->getMessage());
            }
        }
    }

    #[Test]
    public function every_provisioning_step_can_be_retried(): void
    {
        $tenant = $this->provisionTenant('alpha');
        $password = $tenant->db_password;
        $p = app(TenantDatabaseProvisioner::class);

        $p->reserveNames($tenant);
        $p->createDatabase($tenant);
        $p->createDatabaseUser($tenant);
        $p->migrateCoreModules($tenant);
        $p->seed($tenant);

        $this->assertSame($password, $tenant->fresh()->db_password, 'names are reserved only once');
        $tenant->run(fn () => $this->assertTrue(Schema::hasTable('users')));
    }

    #[Test]
    public function a_tenant_without_credentials_never_falls_back_to_the_provisioner(): void
    {
        $tenant = Tenant::create([
            'code' => 'GAMMA', 'name' => 'Gamma', 'slug' => 'gamma',
            'owner_name' => 'O', 'owner_email' => 'o@gamma.test', 'status' => TenantStatus::Provisioning,
        ]);

        $this->expectException(RuntimeException::class);
        $tenant->database()->connection();
    }

    #[Test]
    public function destroy_drops_the_database_and_the_user(): void
    {
        $tenant = $this->provisionTenant('alpha');
        $p = app(TenantDatabaseProvisioner::class);

        $p->destroy($tenant);

        $this->assertFalse($p->databaseExists($tenant));
        $this->assertFalse($tenant->database()->manager()->userExists($tenant->db_username));
    }

    #[Test]
    public function the_dev_command_rejects_reserved_and_duplicate_subdomains(): void
    {
        $this->artisan('erp:tenant:create', ['slug' => 'admin'])->assertFailed();

        $this->artisan('erp:tenant:create', ['slug' => 'delta'])->assertSuccessful();
        $this->artisan('erp:tenant:create', ['slug' => 'delta'])->assertFailed();

        $this->assertSame(TenantStatus::Trial, Tenant::where('slug', 'delta')->first()->status);
    }
}
