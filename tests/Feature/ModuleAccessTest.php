<?php

namespace Tests\Feature;

use App\Central\Enums\CentralUserRole;
use App\Central\Enums\ProvisioningRunType;
use App\Central\Enums\ProvisioningStatus;
use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Models\CentralUser;
use App\Central\Models\Module;
use App\Central\Models\SubscriptionAddon;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Modules\ModuleActivationException;
use App\Central\Modules\ModuleManager;
use App\Central\Provisioning\ModuleInstaller;
use App\Central\Provisioning\ProvisioningRunner;
use App\Contracts\ModuleEntitlement;
use App\Support\Modules\ModuleState;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ProvisionsTenants;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/**
 * Sprint 3 acceptance criteria (TDD §12) on a real tenant database:
 * `module:` middleware, dynamic menu and permissions, ModuleManager.
 */
class ModuleAccessTest extends TestCase
{
    use DatabaseMigrations, ProvisionsTenants, RegistersTenants;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();

        // STARTER tenant, provisioned for real (sync queue).
        $this->tenant = $this->register();
        $this->verify($this->tenant)->assertOk();
        $this->tenant->refresh();
        $this->assertSame(TenantStatus::Trial, $this->tenant->status);

        // A write route in a module, to exercise readonly mode.
        Route::middleware(['web', 'tenant', 'module:inventory', 'auth'])
            ->post('inventory/adjustments', fn () => response('saved'));
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }

    private function login(): void
    {
        $this->post('http://alpha.erp.localhost/login', ['login' => 'ani@alpha.test', 'password' => self::PASSWORD])
            ->assertRedirect('/');
    }

    private function manager(): ModuleManager
    {
        return app(ModuleManager::class);
    }

    private function moduleStatus(string $code): ?TenantModuleStatus
    {
        return TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_id', Module::where('code', $code)->value('id'))
            ->first()?->status;
    }

    #[Test]
    public function plan_modules_get_their_permissions_and_menu(): void
    {
        $this->tenant->run(function () {
            $this->assertTrue(Permission::where('name', 'inventory.adjustment.create')->exists(), 'entity.* expanded');
            $this->assertTrue(Permission::where('name', 'core.company.view')->exists());
            $this->assertTrue(User::sole()->can('pos.transaction.view'), 'SUPER ADMIN has all module permissions');
        });

        $this->login();
        $this->get('http://alpha.erp.localhost/')
            ->assertOk()
            ->assertSeeInOrder(['Dashboard', 'Master Data', 'Inventory', 'POS', 'Laporan'])
            ->assertDontSee('data-module="procurement"', false);
        $this->get('http://alpha.erp.localhost/inventory')->assertOk()->assertSee('Inventory &amp; Warehouse', false);
    }

    #[Test]
    public function a_module_outside_the_plan_has_no_route_menu_or_permissions(): void
    {
        $this->login();

        $this->get('http://alpha.erp.localhost/procurement')->assertForbidden()->assertSee('Modul Procurement tidak aktif');
        $this->getJson('http://alpha.erp.localhost/procurement')->assertForbidden()->assertJson(['module' => 'procurement', 'state' => 'inactive']);
        $this->get('http://alpha.erp.localhost/')->assertDontSee('Procurement');
        $this->tenant->run(fn () => $this->assertSame(0, Permission::where('name', 'like', 'procurement.%')->count()));
    }

    #[Test]
    public function the_contract_resolves_the_current_tenant(): void
    {
        $this->tenant->run(function () {
            $this->assertSame(ModuleState::Active, app(ModuleEntitlement::class)->state('pos'));
            $this->assertSame(ModuleState::Inactive, app(ModuleEntitlement::class)->state('procurement'));
        });
    }

    #[Test]
    public function activating_an_addon_activates_its_entitled_dependencies_without_a_deploy(): void
    {
        $this->login();
        $this->get('http://alpha.erp.localhost/procurement')->assertForbidden();

        // procurement requires inventory (in plan) and workflow (add-on).
        $added = $this->manager()->addAddon($this->tenant, 'procurement');

        $this->assertSame(['workflow', 'procurement'], $added);
        $this->assertSame(TenantModuleStatus::Active, $this->moduleStatus('procurement'));
        $this->assertSame(TenantModuleStatus::Active, $this->moduleStatus('workflow'));

        $run = $this->tenant->provisioningRuns()->where('type', ProvisioningRunType::InstallModule)->sole();
        $this->assertSame(ProvisioningStatus::Done, $run->status);
        $this->assertSame(['install:workflow', 'install:procurement'], $run->steps->pluck('step')->all());

        $this->tenant->run(function () {
            $this->assertEqualsCanonicalizing(
                ['core', 'master', 'inventory', 'pos', 'reporting', 'workflow', 'procurement'],
                InstalledModule::pluck('module_code')->all()
            );
            $this->assertTrue(User::sole()->can('procurement.purchase_order.create'));
        });

        $this->get('http://alpha.erp.localhost/procurement')->assertOk();
        $this->get('http://alpha.erp.localhost/')->assertSee('data-module="procurement"', false)->assertSee('Approval');
    }

    #[Test]
    public function a_dependency_the_tenant_is_not_entitled_to_is_refused_with_a_clear_message(): void
    {
        SubscriptionAddon::create([
            'subscription_id' => $this->tenant->currentSubscription->id,
            'module_id' => Module::where('code', 'procurement')->value('id'),
            'started_at' => now(),
        ]);

        try {
            $this->manager()->activate($this->tenant, 'procurement');
            $this->fail('Activation should be refused');
        } catch (ModuleActivationException $e) {
            $this->assertSame(
                'Modul Procurement membutuhkan Approval Workflow, yang tidak termasuk paket atau add-on langganan ini. Tambahkan sebagai add-on terlebih dahulu.',
                $e->getMessage()
            );
        }

        $this->assertNull($this->moduleStatus('procurement'));
        $this->assertNull($this->moduleStatus('workflow'));
    }

    #[Test]
    public function a_module_outside_plan_and_addons_cannot_be_activated(): void
    {
        $this->expectExceptionMessage('Modul Finance & Accounting tidak termasuk paket atau add-on langganan ini.');

        $this->manager()->activate($this->tenant, 'finance');
    }

    #[Test]
    public function an_expired_subscription_makes_modules_readonly(): void
    {
        $this->login();
        $this->tenant->currentSubscription->update(['status' => SubscriptionStatus::Expired]);

        $this->get('http://alpha.erp.localhost/inventory')->assertOk();
        $this->post('http://alpha.erp.localhost/inventory/adjustments')
            ->assertForbidden()
            ->assertSee('Modul Inventory &amp; Warehouse dalam mode baca saja karena langganan sudah berakhir', false);
        $this->postJson('http://alpha.erp.localhost/inventory/adjustments')
            ->assertForbidden()->assertJson(['state' => 'readonly']);
        $this->get('http://alpha.erp.localhost/')->assertOk()->assertSee('Inventory (baca saja)');

        // Users can still sign in to view and export their data.
        $this->post('http://alpha.erp.localhost/logout');
        $this->login();

        $this->tenant->currentSubscription->update(['status' => SubscriptionStatus::Active]);
        $this->post('http://alpha.erp.localhost/inventory/adjustments')->assertOk()->assertSee('saved');
    }

    #[Test]
    public function deactivation_respects_dependencies_and_keeps_the_data(): void
    {
        $this->manager()->addAddon($this->tenant, 'procurement');

        try {
            $this->manager()->deactivate($this->tenant, 'workflow');
            $this->fail('workflow is required by procurement');
        } catch (ModuleActivationException $e) {
            $this->assertSame('Modul Approval Workflow masih dibutuhkan oleh Procurement. Nonaktifkan modul tersebut terlebih dahulu.', $e->getMessage());
        }

        $this->manager()->deactivate($this->tenant, 'procurement');
        $this->manager()->deactivate($this->tenant, 'workflow');
        $this->assertSame(TenantModuleStatus::Inactive, $this->moduleStatus('procurement'));

        $this->login();
        $this->get('http://alpha.erp.localhost/procurement')->assertForbidden();
        $this->tenant->run(fn () => $this->assertTrue(InstalledModule::where('module_code', 'procurement')->exists(), 'no uninstall'));

        // Re-activation reuses the installed tables and permissions.
        $this->manager()->activate($this->tenant, 'procurement');
        $this->get('http://alpha.erp.localhost/procurement')->assertOk();
    }

    #[Test]
    public function core_modules_cannot_be_deactivated(): void
    {
        $this->expectExceptionMessage('Modul inti Core tidak bisa dinonaktifkan.');

        $this->manager()->deactivate($this->tenant, 'core');
    }

    #[Test]
    public function a_failed_module_install_leaves_the_tenant_running_and_can_be_retried(): void
    {
        $real = app(ModuleInstaller::class);
        $this->app->instance(ModuleInstaller::class, new class($real) extends ModuleInstaller
        {
            public int $failures = 3;

            public function __construct(private readonly ModuleInstaller $real) {}

            public function installModule(Tenant $tenant, string $code): string
            {
                if ($code === 'procurement' && $this->failures-- > 0) {
                    throw new RuntimeException('Simulated migration error');
                }

                return $this->real->installModule($tenant, $code);
            }
        });

        // Sync queue: release() is a no-op, so run the job attempts by hand.
        $this->manager()->addAddon($this->tenant, 'procurement');
        $run = $this->tenant->provisioningRuns()->where('type', ProvisioningRunType::InstallModule)->sole();
        $runner = app(ProvisioningRunner::class);
        $runner->execute($run);
        $runner->execute($run);

        $run->refresh();
        $this->assertSame(ProvisioningStatus::Failed, $run->status);
        $this->assertSame(TenantStatus::Trial, $this->tenant->fresh()->status, 'the tenant keeps working');
        $this->assertSame(TenantModuleStatus::Active, $this->moduleStatus('workflow'));

        $operator = CentralUser::create(['name' => 'Ops', 'email' => 'ops@erp.test', 'password' => 'x', 'role' => CentralUserRole::Support]);
        $this->actingAs($operator, 'central')
            ->post("http://erp.localhost/admin/tenants/{$this->tenant->id}/runs/{$run->id}/steps/".$run->steps()->where('step', 'install:procurement')->value('id').'/retry')
            ->assertSessionHas('status');

        $this->assertSame(ProvisioningStatus::Done, $run->fresh()->status);
        $this->assertSame(TenantModuleStatus::Active, $this->moduleStatus('procurement'));
    }
}
