<?php

namespace Tests\Concerns;

use App\Central\Models\Tenant;
use App\Central\Notifications\VerifyRegistrationEmail;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Registration through the real HTTP form, with Turnstile and the
 * Have I Been Pwned range API faked.
 */
trait RegistersTenants
{
    public const PASSWORD = 'correct-horse-battery';

    protected function seedCatalog(): void
    {
        $this->artisan('modules:sync');
        $this->seed(PlanSeeder::class);
    }

    /** Turnstile answer used by the fake. */
    protected bool $captchaPasses = true;

    /** @var list<string> passwords the fake Have I Been Pwned API reports as breached */
    protected array $leakedPasswords = [];

    protected function fakeExternalServices(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => fn () => Http::response(['success' => $this->captchaPasses]),
            'api.pwnedpasswords.com/range/*' => function ($request) {
                $prefix = substr($request->url(), -5);
                $body = collect($this->leakedPasswords)
                    ->map(fn ($p) => strtoupper(sha1($p)))
                    ->filter(fn ($h) => str_starts_with($h, $prefix))
                    ->map(fn ($h) => substr($h, 5).':42')
                    ->push('0000000000000000000000000000000000A:1')
                    ->join("\r\n");

                return Http::response($body);
            },
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function registrationData(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Toko Alpha',
            'slug' => 'alpha',
            'owner_name' => 'Ani Owner',
            'owner_email' => 'ani@alpha.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'phone' => '+62 812 3456 7890',
            'plan' => 'STARTER',
            'billing_cycle' => 'monthly',
            'cf-turnstile-response' => 'token-from-widget',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function submitRegistration(array $overrides = []): TestResponse
    {
        return $this->post('http://erp.localhost/register', $this->registrationData($overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    protected function register(array $overrides = []): Tenant
    {
        $data = $this->registrationData($overrides);
        $this->submitRegistration($overrides)->assertSessionHasNoErrors()->assertRedirect('http://erp.localhost/register/sent');

        return Tenant::where('slug', $data['slug'])->firstOrFail();
    }

    protected function verify(Tenant $tenant): TestResponse
    {
        return $this->get(VerifyRegistrationEmail::url($tenant));
    }
}
