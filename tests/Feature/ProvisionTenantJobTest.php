<?php

namespace Tests\Feature;

use App\Central\Enums\CentralUserRole;
use App\Central\Enums\ProvisioningStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Jobs\ProvisionTenant;
use App\Central\Models\CentralUser;
use App\Central\Models\ProvisioningRun;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Notifications\ProvisioningFailed;
use App\Central\Notifications\TenantReady;
use App\Central\Provisioning\ModuleInstaller;
use App\Central\Provisioning\ProvisioningRunner;
use App\Central\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProvisionsTenants;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/**
 * ProvisionTenant against a real MySQL server (TDD §7): steps, logging,
 * per-step retries with backoff, permanent failure and "Retry from step".
 */
class ProvisionTenantJobTest extends TestCase
{
    use DatabaseMigrations, ProvisionsTenants, RegistersTenants;

    private ?QueueManager $realQueue = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }

    /** Registers + verifies with the job queued (not run), returns the run. */
    private function verifiedRun(array $overrides = []): ProvisioningRun
    {
        $this->realQueue = Queue::getFacadeRoot();
        Queue::fake();
        $tenant = $this->register($overrides);
        $this->verify($tenant)->assertOk();
        Queue::assertPushed(ProvisionTenant::class);

        return $tenant->provisioningRuns()->sole();
    }

    /**
     * Makes install_modules (step 5) throw the first $times times. With
     * $afterWork the modules are migrated first, so a retry meets a
     * half-finished step.
     */
    private function failInstallModules(int $times, bool $afterWork = false): void
    {
        $real = $this->app->make(ModuleInstaller::class);

        $this->app->instance(ModuleInstaller::class, new class($times, $afterWork, $real) extends ModuleInstaller
        {
            public function __construct(public int $remaining, private readonly bool $afterWork, private readonly ModuleInstaller $real) {}

            public function install(Tenant $tenant): array
            {
                if ($this->remaining-- > 0) {
                    if ($this->afterWork) {
                        $this->real->install($tenant);
                    }

                    throw new RuntimeException('Simulated failure in install_modules');
                }

                return $this->real->install($tenant);
            }
        });
    }

    private function runJob(ProvisioningRun $run): ProvisionTenant
    {
        $job = (new ProvisionTenant($run->id))->withFakeQueueInteractions();
        $job->handle($this->app->make(ProvisioningRunner::class));

        return $job;
    }

    #[Test]
    public function verification_provisions_the_tenant_end_to_end(): void
    {
        // QUEUE_CONNECTION=sync: the job runs inside the verification request.
        $tenant = $this->register();
        $this->verify($tenant)->assertOk()->assertSee('sudah siap');

        $tenant->refresh();
        $run = $tenant->provisioningRuns()->sole();

        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $this->assertSame(ProvisioningStatus::Done, $run->status);
        $this->assertNotNull($run->finished_at);
        foreach ($run->steps as $step) {
            $this->assertSame(ProvisioningStatus::Done, $step->status, $step->step);
            $this->assertSame(1, $step->attempts, $step->step);
        }

        $this->assertTrue(app(TenantDatabaseProvisioner::class)->databaseExists($tenant));
        $this->assertSame("{$tenant->db_name} / {$tenant->db_username}", $run->steps->firstWhere('step', 'reserve_names')->message);

        // Plan modules installed in dependency order and marked active.
        $this->assertSame('core, master, inventory, pos, reporting', $run->steps->firstWhere('step', 'install_modules')->message);
        $tenant->run(fn () => $this->assertEqualsCanonicalizing(
            ['core', 'master', 'inventory', 'pos', 'reporting'],
            InstalledModule::pluck('module_code')->all()
        ));
        $modules = TenantModule::where('tenant_id', $tenant->id)->get();
        $this->assertTrue($modules->every(fn ($m) => $m->status === TenantModuleStatus::Active && $m->installed_version === '0.1.0'));

        // Owner is the SUPER ADMIN with the password chosen at registration.
        $tenant->run(function () {
            $admin = User::sole();
            $this->assertSame('ani@alpha.test', $admin->email);
            $this->assertSame('admin', $admin->username);
            $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
            $this->assertTrue($admin->hasRole('SUPER ADMIN'));
        });

        // The password hash is removed from the central database.
        $this->assertArrayNotHasKey('admin_password', $tenant->registration());

        Notification::assertSentOnDemand(TenantReady::class, function (TenantReady $n, $channels, AnonymousNotifiable $notifiable) {
            return $notifiable->routes['mail'] === ['ani@alpha.test' => 'Ani Owner']
                && $n->loginUrl() === 'http://alpha.erp.localhost/login'
                && str_contains($n->toMail($notifiable)->actionUrl, 'alpha.erp.localhost/login');
        });
    }

    #[Test]
    public function the_owner_can_log_in_on_the_tenant_subdomain(): void
    {
        $tenant = $this->register();
        $this->verify($tenant)->assertOk();

        $this->get('http://alpha.erp.localhost/login')->assertOk()->assertSee('Toko Alpha');

        $this->post('http://alpha.erp.localhost/login', ['login' => 'ani@alpha.test', 'password' => 'wrong-password-123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest('web');

        $this->post('http://alpha.erp.localhost/login', ['login' => 'ani@alpha.test', 'password' => self::PASSWORD])
            ->assertRedirect('/');
        $this->assertAuthenticated('web');
        $this->assertSame('ani@alpha.test', auth('web')->user()->email);
    }

    #[Test]
    public function a_failing_step_is_retried_with_backoff_10_60_300_then_fails_permanently(): void
    {
        $operator = CentralUser::create(['name' => 'Ops', 'email' => 'ops@erp.test', 'password' => 'x', 'role' => CentralUserRole::Support]);
        $billing = CentralUser::create(['name' => 'Bill', 'email' => 'bill@erp.test', 'password' => 'x', 'role' => CentralUserRole::Billing]);
        $run = $this->verifiedRun();
        $this->failInstallModules(99);

        $this->runJob($run)->assertReleased(delay: 10);
        $step = $run->steps()->where('step', 'install_modules')->sole();
        $this->assertSame(ProvisioningStatus::Failed, $step->status);
        $this->assertSame(1, $step->attempts);
        $this->assertSame('Simulated failure in install_modules', $step->message);
        $this->assertSame(ProvisioningStatus::Running, $run->fresh()->status, 'still retrying');
        $this->assertSame(TenantStatus::Provisioning, $run->tenant->fresh()->status);

        $this->runJob($run)->assertReleased(delay: 60);
        $this->assertSame(2, $step->fresh()->attempts);

        $this->runJob($run)->assertNotReleased();
        $this->assertSame(3, $step->fresh()->attempts);

        $run->refresh();
        $this->assertSame(ProvisioningStatus::Failed, $run->status);
        $this->assertSame('install_modules: Simulated failure in install_modules', $run->error);
        $this->assertSame(TenantStatus::Failed, $run->tenant->fresh()->status);
        $this->assertSame(
            ['done', 'done', 'done', 'done', 'failed', 'pending', 'pending', 'pending'],
            $run->steps()->pluck('status')->map->value->all()
        );
        // Earlier steps ran once only, although the job ran three times.
        $this->assertSame([1, 1, 1, 1], $run->steps()->where('seq', '<', 5)->pluck('attempts')->all());

        Notification::assertSentTo($operator, ProvisioningFailed::class);
        Notification::assertNotSentTo($billing, ProvisioningFailed::class);

        // The customer sees the "being prepared" page, not an error.
        $this->get('http://alpha.erp.localhost/')->assertStatus(503)->assertSee('sedang disiapkan');

        // A failed run is not resumed by a stray job; only "Retry from step" restarts it.
        $this->runJob($run)->assertNotReleased();
        $this->assertSame(3, $step->fresh()->attempts);
    }

    #[Test]
    public function the_third_backoff_is_300_seconds(): void
    {
        config(['erp.provisioning.max_attempts' => 4]);
        $run = $this->verifiedRun();
        $this->failInstallModules(99);

        $this->runJob($run)->assertReleased(delay: 10);
        $this->runJob($run)->assertReleased(delay: 60);
        $this->runJob($run)->assertReleased(delay: 300);
    }

    #[Test]
    public function a_step_that_recovers_on_the_second_attempt_completes_the_run(): void
    {
        $run = $this->verifiedRun();
        $this->failInstallModules(1);

        $this->runJob($run)->assertReleased(delay: 10);
        $this->runJob($run)->assertNotReleased();

        $run->refresh();
        $this->assertSame(ProvisioningStatus::Done, $run->status);
        $this->assertNull($run->error);
        $this->assertSame(2, $run->steps()->where('step', 'install_modules')->value('attempts'));
        $this->assertSame(TenantStatus::Trial, $run->tenant->fresh()->status);
    }

    #[Test]
    public function a_run_failed_at_step_5_is_retried_from_the_panel_without_duplicating_data(): void
    {
        $operator = CentralUser::create(['name' => 'Ops', 'email' => 'ops@erp.test', 'password' => 'x', 'role' => CentralUserRole::Support]);
        $run = $this->verifiedRun();
        $tenant = $run->tenant;

        // Step 5 fails 3 times, each time after its modules were migrated.
        $this->failInstallModules(3, afterWork: true);
        $this->runJob($run);
        $this->runJob($run);
        $this->runJob($run);
        $this->assertSame(ProvisioningStatus::Failed, $run->fresh()->status);
        $doneBefore = $run->steps()->where('seq', '<', 5)->get()->keyBy('step');

        // Operator clicks "Retry from step" on install_modules; the job runs (sync queue).
        Queue::swap($this->realQueue); // stop faking: the sync queue runs the job
        $this->actingAs($operator, 'central')
            ->post("http://erp.localhost/admin/tenants/{$tenant->id}/runs/{$run->id}/steps/".$run->steps()->where('step', 'install_modules')->value('id').'/retry')
            ->assertRedirect("http://erp.localhost/admin/tenants/{$tenant->id}")
            ->assertSessionHas('status');

        $run->refresh();
        $tenant->refresh();
        $this->assertSame(ProvisioningStatus::Done, $run->status);
        $this->assertSame(TenantStatus::Trial, $tenant->status);

        // Done steps were skipped: not attempted again, timestamps untouched.
        foreach ($run->steps()->where('seq', '<', 5)->get() as $step) {
            $this->assertSame(1, $step->attempts, $step->step);
            $this->assertTrue($step->finished_at->equalTo($doneBefore[$step->step]->finished_at), $step->step);
        }
        $this->assertSame(1, $run->steps()->where('step', 'install_modules')->value('attempts'));

        // No duplicated data anywhere.
        $this->assertSame(5, TenantModule::where('tenant_id', $tenant->id)->count());
        $tenant->run(function () {
            $this->assertSame(5, InstalledModule::count());
            $this->assertSame(1, User::count());
            $this->assertSame(1, Role::count());
        });

        Notification::assertSentOnDemandTimes(TenantReady::class, 1);
    }

    #[Test]
    public function only_failed_runs_can_be_retried(): void
    {
        $tenant = $this->register();
        $this->verify($tenant);
        $run = $tenant->provisioningRuns()->sole();
        $runner = app(ProvisioningRunner::class);

        $this->expectExceptionMessage('Hanya run yang gagal');
        $runner->retryFrom($run, $run->steps()->where('step', 'migrate_core')->sole());
    }

    #[Test]
    public function every_step_can_run_twice_without_side_effects(): void
    {
        $tenant = $this->register();
        $this->verify($tenant);
        $run = $tenant->provisioningRuns()->sole();

        // Force a full re-run of every step (as if a worker crashed after each).
        $run->update(['status' => ProvisioningStatus::Failed]);
        app(ProvisioningRunner::class)->retryFrom($run, $run->steps()->where('seq', 1)->sole());

        $tenant->refresh();
        $this->assertSame(ProvisioningStatus::Done, $run->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $tenant->run(function () {
            $this->assertSame(1, User::count());
            $this->assertSame(5, InstalledModule::count());
            $this->assertTrue(User::sole()->hasRole('SUPER ADMIN'));
        });
        Notification::assertSentOnDemandTimes(TenantReady::class, 1);
    }

    #[Test]
    public function a_job_that_dies_outside_a_step_marks_the_run_failed(): void
    {
        $run = $this->verifiedRun();
        $run->steps()->where('seq', 1)->update(['status' => ProvisioningStatus::Running, 'attempts' => 1]);
        $run->update(['status' => ProvisioningStatus::Running]);

        (new ProvisionTenant($run->id))->failed(new RuntimeException('Job timed out'));

        $this->assertSame(ProvisioningStatus::Failed, $run->fresh()->status);
        $this->assertSame('reserve_names: Job timed out', $run->fresh()->error);
        $this->assertSame(TenantStatus::Failed, $run->tenant->fresh()->status);
    }
}
