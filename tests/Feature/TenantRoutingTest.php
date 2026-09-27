<?php

namespace Tests\Feature;

use App\Central\Enums\TenantStatus;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ProvisionsTenants;
use Tests\TestCase;

class TenantRoutingTest extends TestCase
{
    use DatabaseMigrations, ProvisionsTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('modules:sync');
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }

    #[Test]
    public function the_central_domain_serves_the_central_app(): void
    {
        $this->get('http://erp.localhost/')->assertOk()->assertSee('Central app');
    }

    #[Test]
    public function each_subdomain_is_served_from_its_own_database(): void
    {
        $alpha = $this->provisionTenant('alpha');
        $beta = $this->provisionTenant('beta');

        $this->get('http://alpha.erp.localhost/login')->assertOk()->assertSee('Alpha Store');
        $this->assertSame($alpha->db_name, DB::connection()->getDatabaseName());
        tenancy()->end();
        $this->get('http://beta.erp.localhost/login')->assertOk()->assertSee('Beta Store');
        $this->assertSame($beta->db_name, DB::connection()->getDatabaseName());
    }

    #[Test]
    public function unknown_subdomains_return_404(): void
    {
        $this->get('http://nobody.erp.localhost/')->assertNotFound();
    }

    #[Test]
    public function guests_are_sent_to_the_tenant_login(): void
    {
        $this->provisionTenant('alpha');

        $this->get('http://alpha.erp.localhost/')->assertRedirect('/login');
    }

    #[Test]
    public function tenants_being_provisioned_see_a_waiting_page(): void
    {
        $this->provisionTenant('alpha', TenantStatus::Provisioning);

        $this->get('http://alpha.erp.localhost/')->assertStatus(503)->assertSee('sedang disiapkan');
    }

    #[Test]
    public function suspended_tenants_are_blocked(): void
    {
        $this->provisionTenant('alpha', TenantStatus::Suspended);

        $this->get('http://alpha.erp.localhost/')->assertForbidden()->assertSee('tidak aktif');
    }

    #[Test]
    public function past_due_tenants_can_still_work(): void
    {
        $this->provisionTenant('alpha', TenantStatus::PastDue);

        $this->get('http://alpha.erp.localhost/login')->assertOk();
    }
}
