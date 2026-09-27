<?php

namespace Tests\Concerns;

use App\Central\Models\Tenant;
use Illuminate\Support\Facades\Notification;

/**
 * A tenant registered and provisioned for real (sync queue) on a plan, with
 * the owner ani@alpha.test as SUPER ADMIN. Tenant databases are dropped in
 * tearDown.
 */
trait UsesProvisionedTenant
{
    use CompletesSetup, ProvisionsTenants, RegistersTenants;

    protected Tenant $tenant;

    protected function provisionedTenant(string $plan = 'STARTER', bool $setup = true): Tenant
    {
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();

        $this->tenant = $this->register(['plan' => $plan]);
        $this->verify($this->tenant)->assertOk();
        $this->tenant->refresh();

        if ($setup) {
            $this->completeSetup($this->tenant);
            $this->tenant->refresh();
        }

        return $this->tenant;
    }

    protected function loginAsOwner(): void
    {
        $this->post('http://alpha.erp.localhost/login', ['login' => 'ani@alpha.test', 'password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
    }

    protected function url(string $path): string
    {
        return 'http://alpha.erp.localhost/'.ltrim($path, '/');
    }

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }
}
