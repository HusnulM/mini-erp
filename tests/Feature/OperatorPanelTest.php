<?php

namespace Tests\Feature;

use App\Central\Enums\CentralUserRole;
use App\Central\Enums\ProvisioningStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Jobs\ProvisionTenant;
use App\Central\Models\CentralUser;
use App\Central\Models\Module;
use App\Central\Models\ProvisioningRun;
use App\Central\Models\SubscriptionAddon;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/** Operator panel on the central domain, guard "central" (TDD §7, §11). */
class OperatorPanelTest extends TestCase
{
    use DatabaseMigrations, RegistersTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();
        Queue::fake();
    }

    private function operator(CentralUserRole $role = CentralUserRole::Support, bool $active = true): CentralUser
    {
        return CentralUser::create([
            'name' => ucfirst($role->value), 'email' => "{$role->value}@erp.test",
            'password' => 'operator-secret-1', 'role' => $role, 'is_active' => $active,
        ]);
    }

    /** A verified tenant whose run failed at install_modules. */
    private function failedTenant(): Tenant
    {
        $tenant = $this->register();
        $this->verify($tenant);
        $run = $tenant->provisioningRuns()->sole();

        foreach ($run->steps as $step) {
            $step->update(match (true) {
                $step->seq < 5 => ['status' => ProvisioningStatus::Done, 'attempts' => 1, 'finished_at' => now()],
                $step->seq === 5 => ['status' => ProvisioningStatus::Failed, 'attempts' => 3, 'message' => 'Access denied for user'],
                default => [],
            });
        }
        $run->update(['status' => ProvisioningStatus::Failed, 'error' => 'install_modules: Access denied for user']);
        $tenant->update(['status' => TenantStatus::Failed]);

        return $tenant->fresh();
    }

    private function retryUrl(Tenant $tenant, string $step): string
    {
        $run = $tenant->provisioningRuns()->sole();

        return "http://erp.localhost/admin/tenants/{$tenant->id}/runs/{$run->id}/steps/".$run->steps()->where('step', $step)->value('id').'/retry';
    }

    #[Test]
    public function guests_are_sent_to_the_operator_login(): void
    {
        $this->get('http://erp.localhost/admin')->assertRedirect('http://erp.localhost/admin/login');
        $this->get('http://erp.localhost/admin/login')->assertOk()->assertSee('Login operator');
    }

    #[Test]
    public function operators_log_in_with_the_central_guard(): void
    {
        $operator = $this->operator();

        $this->post('http://erp.localhost/admin/login', ['email' => 'support@erp.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('central');

        $this->post('http://erp.localhost/admin/login', ['email' => 'support@erp.test', 'password' => 'operator-secret-1'])
            ->assertRedirect('http://erp.localhost/admin');
        $this->assertAuthenticatedAs($operator, 'central');
        $this->assertGuest('web');
        $this->assertNotNull($operator->fresh()->last_login_at);

        $this->post('http://erp.localhost/admin/logout')->assertRedirect('http://erp.localhost/admin/login');
        $this->assertGuest('central');
    }

    #[Test]
    public function inactive_operators_cannot_log_in(): void
    {
        $this->operator(active: false);

        $this->post('http://erp.localhost/admin/login', ['email' => 'support@erp.test', 'password' => 'operator-secret-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('central');
    }

    #[Test]
    public function login_is_throttled_after_five_failures(): void
    {
        $this->operator();

        for ($i = 0; $i < 5; $i++) {
            $this->post('http://erp.localhost/admin/login', ['email' => 'support@erp.test', 'password' => 'wrong']);
        }

        $this->post('http://erp.localhost/admin/login', ['email' => 'support@erp.test', 'password' => 'operator-secret-1'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.']);
        $this->assertGuest('central');
    }

    #[Test]
    public function the_panel_is_not_served_on_tenant_domains(): void
    {
        $this->get('http://alpha.erp.localhost/admin/login')->assertNotFound();
    }

    #[Test]
    public function the_tenant_list_shows_status_and_can_be_filtered(): void
    {
        $failed = $this->failedTenant();
        $pending = $this->register(['slug' => 'beta', 'company_name' => 'Toko Beta', 'owner_email' => 'budi@beta.test']);

        $this->actingAs($this->operator(), 'central');

        $this->get('http://erp.localhost/admin')
            ->assertOk()
            ->assertSee('Toko Alpha')->assertSee('alpha.erp.localhost')->assertSee('Starter')
            ->assertSee('Toko Beta')->assertSee('menunggu verifikasi email')
            ->assertSee('failed: 1')->assertSee('pending: 1');

        $this->get('http://erp.localhost/admin?status=failed')->assertOk()->assertSee('Toko Alpha')->assertDontSee('Toko Beta');
        $this->get('http://erp.localhost/admin?q=budi')->assertOk()->assertSee('Toko Beta')->assertDontSee('Toko Alpha');
        $this->get('http://erp.localhost/admin?status=bogus')->assertRedirect();

        $this->assertNotNull($failed->id);
        $this->assertNotNull($pending->id);
    }

    #[Test]
    public function the_detail_page_shows_each_provisioning_step(): void
    {
        $tenant = $this->failedTenant();
        $this->actingAs($this->operator(), 'central');

        $this->get("http://erp.localhost/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSeeInOrder(['reserve_names', 'create_database', 'create_db_user', 'migrate_core', 'install_modules', 'seed_defaults', 'create_admin', 'finalize'])
            ->assertSee('Access denied for user')
            ->assertSee('3/3')
            ->assertSee('trialing')
            ->assertSee('Retry from step');
    }

    #[Test]
    public function retry_from_step_resets_the_failed_step_and_queues_the_job(): void
    {
        $tenant = $this->failedTenant();
        Queue::fake();
        $this->actingAs($this->operator(), 'central');

        $this->post($this->retryUrl($tenant, 'install_modules'))
            ->assertRedirect("http://erp.localhost/admin/tenants/{$tenant->id}")
            ->assertSessionHas('status', 'Provisioning diulang dari langkah install_modules.');

        $run = $tenant->provisioningRuns()->sole();
        $this->assertSame(ProvisioningStatus::Pending, $run->status);
        $this->assertNull($run->error);
        $this->assertSame(TenantStatus::Provisioning, $tenant->fresh()->status);
        $this->assertSame(
            [['done', 1], ['done', 1], ['done', 1], ['done', 1], ['pending', 0], ['pending', 0], ['pending', 0], ['pending', 0]],
            $run->steps->map(fn ($s) => [$s->status->value, $s->attempts])->all()
        );
        Queue::assertPushedOn('provisioning', ProvisionTenant::class, fn ($job) => $job->runId === $run->id);
    }

    #[Test]
    public function retry_cannot_skip_an_unfinished_step(): void
    {
        $tenant = $this->failedTenant();
        Queue::fake();
        $this->actingAs($this->operator(), 'central');

        $this->post($this->retryUrl($tenant, 'create_admin'))->assertSessionHasErrors('retry');

        $this->assertSame(ProvisioningStatus::Failed, $tenant->provisioningRuns()->sole()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_run_that_is_not_failed_cannot_be_retried(): void
    {
        $tenant = $this->register();
        $this->verify($tenant);
        Queue::fake();
        $this->actingAs($this->operator(), 'central');

        $this->post($this->retryUrl($tenant, 'reserve_names'))->assertSessionHasErrors('retry');
        Queue::assertNothingPushed();
    }

    #[Test]
    public function billing_operators_can_view_but_not_retry(): void
    {
        $tenant = $this->failedTenant();
        $this->actingAs($this->operator(CentralUserRole::Billing), 'central');

        $this->get("http://erp.localhost/admin/tenants/{$tenant->id}")->assertOk()->assertDontSee('Retry from step');
        $this->post($this->retryUrl($tenant, 'install_modules'))->assertForbidden();
        $this->assertSame(ProvisioningStatus::Failed, $tenant->provisioningRuns()->sole()->status);
    }

    #[Test]
    public function steps_of_another_tenants_run_are_not_found(): void
    {
        $alpha = $this->failedTenant();
        $beta = $this->register(['slug' => 'beta', 'owner_email' => 'budi@beta.test']);
        $this->actingAs($this->operator(), 'central');

        $run = $alpha->provisioningRuns()->sole();
        $step = $run->steps()->where('step', 'install_modules')->value('id');

        $this->post("http://erp.localhost/admin/tenants/{$beta->id}/runs/{$run->id}/steps/{$step}/retry")->assertNotFound();
        $this->assertSame(ProvisioningStatus::Failed, ProvisioningRun::find($run->id)->status);
    }

    #[Test]
    public function tenant_users_are_not_operators(): void
    {
        // A session on the tenant guard gives no access to the panel.
        $this->actingAs(new User(['name' => 'x', 'email' => 'x@x.test']), 'web');

        $this->get('http://erp.localhost/admin')->assertRedirect('http://erp.localhost/admin/login');
    }

    private function moduleUrl(Tenant $tenant, string $module, string $action): string
    {
        return "http://erp.localhost/admin/tenants/{$tenant->id}/modules/{$module}/{$action}";
    }

    /** A verified STARTER tenant whose plan modules are active (no database needed here). */
    private function liveTenant(): Tenant
    {
        $tenant = $this->register();
        $this->verify($tenant);
        TenantModule::where('tenant_id', $tenant->id)->update(['status' => TenantModuleStatus::Active]);
        $tenant->update(['status' => TenantStatus::Trial]);
        Queue::fake(); // forget the ProvisionTenant job pushed by the verification

        return $tenant->fresh();
    }

    #[Test]
    public function the_detail_page_lists_every_module_with_its_state(): void
    {
        $tenant = $this->liveTenant();
        $this->actingAs($this->operator(CentralUserRole::Owner), 'central');

        $this->get("http://erp.localhost/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('<code>procurement</code>', false)
            ->assertSee('tidak ter-entitle (add-on)')
            ->assertSee('Tambah add-on')
            ->assertSee('Nonaktifkan');
    }

    #[Test]
    public function billing_adds_addons_and_support_activates_modules(): void
    {
        $tenant = $this->liveTenant();
        $support = $this->operator(CentralUserRole::Support);

        $this->actingAs($support, 'central');
        $this->post($this->moduleUrl($tenant, 'procurement', 'addon'))->assertForbidden();

        $this->actingAs($this->operator(CentralUserRole::Billing), 'central');
        $this->post($this->moduleUrl($tenant, 'procurement', 'addon'))
            ->assertSessionHas('status', 'Add-on ditambahkan: workflow, procurement. Modul procurement sedang diinstal.');
        $this->post($this->moduleUrl($tenant, 'pos', 'deactivate'))->assertForbidden();

        $this->assertSame(2, SubscriptionAddon::count());
        Queue::assertPushedOn('provisioning', ProvisionTenant::class);
        Queue::assertPushed(ProvisionTenant::class, 1);
        $this->assertSame(TenantModuleStatus::Installing, TenantModule::where('tenant_id', $tenant->id)
            ->where('module_id', Module::where('code', 'procurement')->value('id'))->sole()->status);

        $this->actingAs($support, 'central');
        $this->post($this->moduleUrl($tenant, 'pos', 'deactivate'))->assertSessionHas('status');
        $this->post($this->moduleUrl($tenant, 'pos', 'activate'))->assertSessionHas('status', 'Modul pos sedang diinstal.');
    }

    #[Test]
    public function refused_module_changes_show_the_reason(): void
    {
        $tenant = $this->liveTenant();
        $this->actingAs($this->operator(), 'central');

        $this->post($this->moduleUrl($tenant, 'finance', 'activate'))
            ->assertSessionHasErrors(['module' => 'Modul Finance & Accounting tidak termasuk paket atau add-on langganan ini.']);
        $this->post($this->moduleUrl($tenant, 'master', 'deactivate'))
            ->assertSessionHasErrors(['module' => 'Modul inti Master Data tidak bisa dinonaktifkan.']);
        $this->post($this->moduleUrl($tenant, 'nope', 'activate'))
            ->assertSessionHasErrors(['module' => 'Modul [nope] tidak dikenal.']);
        Queue::assertNothingPushed();
    }
}
