<?php

namespace Tests\Feature;

use App\Central\Enums\ModuleSource;
use App\Central\Enums\ProvisioningStatus;
use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Jobs\ProvisionTenant;
use App\Central\Models\Subscription;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Notifications\VerifyRegistrationEmail;
use App\Central\Provisioning\ProvisioningRunner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/** TDD §7: registration form, validation and email verification. */
class RegistrationTest extends TestCase
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

    #[Test]
    public function the_form_offers_only_public_plans(): void
    {
        $this->get('http://erp.localhost/register')
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Business')
            ->assertDontSee('Enterprise')
            ->assertSee('cf-turnstile', false)
            ->assertSee('.erp.localhost');
    }

    #[Test]
    public function registering_creates_a_pending_tenant_and_sends_a_verification_email(): void
    {
        $tenant = $this->register();

        $this->assertSame(TenantStatus::Pending, $tenant->status);
        $this->assertNull($tenant->email_verified_at);
        $this->assertNull($tenant->db_name, 'no database before the email is verified');
        $this->assertSame('ALPHA', $tenant->code);
        $this->assertSame('Toko Alpha', $tenant->name);
        $this->assertSame('+62 812 3456 7890', $tenant->phone);
        $this->assertSame(['alpha.erp.localhost'], $tenant->domains->pluck('domain')->all());

        // Only an encrypted hash of the admin password is stored centrally.
        $registration = $tenant->registration();
        $this->assertSame('monthly', $registration['billing_cycle']);
        $raw = DB::connection('central')->table('tenants')->where('id', $tenant->id)->value('data');
        $this->assertStringNotContainsString(self::PASSWORD, $raw);
        $this->assertTrue(Hash::check(self::PASSWORD, Crypt::decryptString($registration['admin_password'])));

        Notification::assertSentOnDemand(VerifyRegistrationEmail::class, function ($n, $channels, AnonymousNotifiable $notifiable) {
            return $notifiable->routes['mail'] === ['ani@alpha.test' => 'Ani Owner'];
        });
        Queue::assertNothingPushed();
        $this->assertSame(0, Subscription::count());
    }

    #[Test]
    public function the_sent_page_shows_the_email_address(): void
    {
        $this->submitRegistration();

        $this->get('http://erp.localhost/register/sent')->assertOk()->assertSee('ani@alpha.test');
    }

    public static function invalidRegistrations(): array
    {
        return [
            'missing company' => [['company_name' => ''], 'company_name'],
            'company too long' => [['company_name' => str_repeat('a', 151)], 'company_name'],
            'slug too short' => [['slug' => 'ab'], 'slug'],
            'slug too long' => [['slug' => str_repeat('a', 31)], 'slug'],
            'slug uppercase is normalized, but underscore is not allowed' => [['slug' => 'toko_abc'], 'slug'],
            'slug starts with dash' => [['slug' => '-toko'], 'slug'],
            'reserved slug www' => [['slug' => 'www'], 'slug'],
            'reserved slug admin' => [['slug' => 'admin'], 'slug'],
            'reserved slug billing' => [['slug' => 'billing'], 'slug'],
            'invalid email' => [['owner_email' => 'not-an-email'], 'owner_email'],
            'missing owner name' => [['owner_name' => ''], 'owner_name'],
            'password shorter than 10' => [['password' => 'short-pw1', 'password_confirmation' => 'short-pw1'], 'password'],
            'password not confirmed' => [['password_confirmation' => 'something-else-123'], 'password'],
            'invalid phone' => [['phone' => 'call me'], 'phone'],
            'unknown plan' => [['plan' => 'GOLD'], 'plan'],
            'non-public plan' => [['plan' => 'ENTERPRISE'], 'plan'],
            'invalid billing cycle' => [['billing_cycle' => 'weekly'], 'billing_cycle'],
            'missing captcha' => [['cf-turnstile-response' => ''], 'cf-turnstile-response'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRegistrations')]
    public function invalid_registrations_are_rejected(array $overrides, string $field): void
    {
        $this->submitRegistration($overrides)->assertSessionHasErrors($field);

        $this->assertSame(0, Tenant::count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function the_phone_number_is_optional(): void
    {
        $tenant = $this->register(['phone' => '']);

        $this->assertNull($tenant->phone);
    }

    #[Test]
    public function the_slug_and_owner_email_must_be_unique(): void
    {
        $this->register();

        $this->submitRegistration(['owner_email' => 'other@beta.test'])->assertSessionHasErrors('slug');
        $this->submitRegistration(['slug' => 'beta', 'owner_email' => 'ANI@alpha.test'])->assertSessionHasErrors('owner_email');
        $this->assertSame(1, Tenant::count());
    }

    #[Test]
    public function a_leaked_password_is_rejected(): void
    {
        $this->leakedPasswords = ['password1234'];

        $this->submitRegistration(['password' => 'password1234', 'password_confirmation' => 'password1234'])
            ->assertSessionHasErrors(['password' => 'Password ini pernah bocor di internet. Pilih password lain.']);
    }

    #[Test]
    public function a_failed_captcha_is_rejected(): void
    {
        $this->captchaPasses = false;

        $this->submitRegistration()->assertSessionHasErrors('cf-turnstile-response');
        $this->assertSame(0, Tenant::count());
    }

    #[Test]
    public function verifying_starts_a_trial_entitles_plan_modules_and_queues_provisioning(): void
    {
        $this->travelTo(now()->startOfMinute());
        $tenant = $this->register();

        $this->verify($tenant)->assertOk()->assertSee('sedang disiapkan');

        $tenant->refresh();
        $this->assertSame(TenantStatus::Provisioning, $tenant->status);
        $this->assertNotNull($tenant->email_verified_at);

        $subscription = $tenant->currentSubscription;
        $this->assertSame('STARTER', $subscription->plan->code);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame('monthly', $subscription->billing_cycle->value);
        $this->assertTrue($subscription->current_period_end->equalTo(now()->addDays(14)));
        $this->assertTrue($tenant->trial_ends_at->equalTo($subscription->current_period_end));

        $modules = TenantModule::with('module')->where('tenant_id', $tenant->id)->get()->keyBy('module.code');
        $this->assertEqualsCanonicalizing(['core', 'master', 'inventory', 'pos', 'reporting'], $modules->keys()->all());
        $this->assertSame(ModuleSource::Core, $modules['core']->source);
        $this->assertSame(ModuleSource::Plan, $modules['pos']->source);
        $this->assertTrue($modules->every(fn ($m) => $m->status === TenantModuleStatus::Installing));

        $run = $tenant->provisioningRuns()->sole();
        $this->assertSame(ProvisioningStatus::Pending, $run->status);
        $this->assertSame(ProvisioningRunner::STEPS, $run->steps->pluck('step')->all());

        Queue::assertPushedOn('provisioning', ProvisionTenant::class, fn (ProvisionTenant $job) => $job->runId === $run->id);
    }

    #[Test]
    public function plan_modules_include_their_dependencies(): void
    {
        $tenant = $this->register(['plan' => 'BUSINESS']);
        $this->verify($tenant)->assertOk();

        $codes = TenantModule::with('module')->where('tenant_id', $tenant->id)->get()->pluck('module.code');
        $this->assertEqualsCanonicalizing(
            ['core', 'master', 'workflow', 'inventory', 'procurement', 'pos', 'finance', 'reporting'],
            $codes->all()
        );
    }

    #[Test]
    public function clicking_the_link_twice_does_not_duplicate_anything(): void
    {
        $tenant = $this->register();

        $this->verify($tenant)->assertOk();
        $this->verify($tenant)->assertOk();

        $this->assertSame(1, Subscription::where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, $tenant->provisioningRuns()->count());
        $this->assertSame(5, TenantModule::where('tenant_id', $tenant->id)->count());
        Queue::assertPushed(ProvisionTenant::class, 1);
    }

    #[Test]
    public function tampered_or_expired_links_are_rejected(): void
    {
        $tenant = $this->register();
        $url = VerifyRegistrationEmail::url($tenant);

        $this->get(str_replace('/alpha', '/beta', $url.'x'))->assertForbidden();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=abc', $url))->assertForbidden();

        $this->travel(8)->days();
        $this->get($url)->assertForbidden();

        $this->assertSame(TenantStatus::Pending, $tenant->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_link_for_another_email_is_rejected(): void
    {
        $tenant = $this->register();
        $tenant->update(['owner_email' => 'changed@alpha.test']);

        // Hash in the signed URL was made for the old address.
        $url = URL::temporarySignedRoute(
            'central.register.verify', now()->addHour(), ['tenant' => $tenant->id, 'hash' => sha1('ani@alpha.test')]
        );

        $this->get($url)->assertForbidden();
    }
}
