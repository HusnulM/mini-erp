<?php

namespace Tests\Feature;

use App\Central\Entitlement\SubscriptionEntitlement;
use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Models\Module;
use App\Central\Models\SubscriptionAddon;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Support\Modules\ModuleState;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/** SaaS entitlement driver (TDD §6, §8 "Dampak ke akses"). Central tables only. */
class SubscriptionEntitlementTest extends TestCase
{
    use DatabaseMigrations, RegistersTenants;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();
        Queue::fake();

        // STARTER: core, master, inventory, pos, reporting. Modules marked
        // active by hand; no tenant database is needed for entitlement.
        $this->tenant = $this->register();
        $this->verify($this->tenant);
        TenantModule::where('tenant_id', $this->tenant->id)->update(['status' => TenantModuleStatus::Active]);
    }

    private function entitlement(): SubscriptionEntitlement
    {
        return app(SubscriptionEntitlement::class)->forTenant($this->tenant);
    }

    private function row(string $code): TenantModule
    {
        return TenantModule::where('tenant_id', $this->tenant->id)->where('module_id', Module::where('code', $code)->value('id'))->sole();
    }

    #[Test]
    public function plan_modules_are_active_and_others_inactive(): void
    {
        $e = $this->entitlement();

        foreach (['core', 'master', 'inventory', 'pos', 'reporting'] as $code) {
            $this->assertSame(ModuleState::Active, $e->state($code), $code);
        }
        foreach (['procurement', 'finance', 'workflow', 'unknown'] as $code) {
            $this->assertSame(ModuleState::Inactive, $e->state($code), $code);
        }
    }

    #[Test]
    public function installing_or_failed_modules_are_inactive_and_readonly_rows_are_readonly(): void
    {
        $this->row('pos')->update(['status' => TenantModuleStatus::Installing]);
        $this->row('reporting')->update(['status' => TenantModuleStatus::Failed]);
        $this->row('inventory')->update(['status' => TenantModuleStatus::Readonly]);

        $e = $this->entitlement();
        $this->assertSame(ModuleState::Inactive, $e->state('pos'));
        $this->assertSame(ModuleState::Inactive, $e->state('reporting'));
        $this->assertSame(ModuleState::ReadOnly, $e->state('inventory'));
    }

    #[Test]
    public function an_expired_module_becomes_readonly(): void
    {
        $this->row('pos')->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(ModuleState::ReadOnly, $this->entitlement()->state('pos'));
        $this->assertTrue($this->entitlement()->expiresAt('pos')->isPast());
    }

    #[Test]
    public function expired_or_cancelled_subscriptions_make_everything_readonly_and_nothing_activatable(): void
    {
        foreach ([SubscriptionStatus::Expired, SubscriptionStatus::Cancelled] as $status) {
            $this->tenant->currentSubscription->update(['status' => $status]);

            $e = $this->entitlement();
            $this->assertSame(ModuleState::ReadOnly, $e->state('core'), $status->value);
            $this->assertSame(ModuleState::ReadOnly, $e->state('pos'), $status->value);
            $this->assertFalse($e->canActivate('pos'), $status->value);
        }
    }

    #[Test]
    public function past_due_keeps_full_access(): void
    {
        $this->tenant->currentSubscription->update(['status' => SubscriptionStatus::PastDue]);

        $this->assertSame(ModuleState::Active, $this->entitlement()->state('pos'));
        $this->assertTrue($this->entitlement()->canActivate('pos'));
    }

    #[Test]
    public function core_plan_and_running_addons_can_be_activated(): void
    {
        $e = $this->entitlement();
        $this->assertTrue($e->canActivate('core'));
        $this->assertTrue($e->canActivate('inventory'));
        $this->assertFalse($e->canActivate('procurement'));

        $addon = SubscriptionAddon::create([
            'subscription_id' => $this->tenant->currentSubscription->id,
            'module_id' => Module::where('code', 'procurement')->value('id'),
            'started_at' => now(),
        ]);
        $this->assertTrue($this->entitlement()->canActivate('procurement'), 'cache flushed when the add-on is saved');

        $addon->update(['ended_at' => now()->subDay()]);
        $this->assertFalse($this->entitlement()->canActivate('procurement'));
    }

    #[Test]
    public function limits_come_from_the_plan(): void
    {
        $limits = $this->entitlement()->limits();

        $this->assertSame(5, $limits->users);
        $this->assertSame(1, $limits->companies);
        $this->assertSame(2, $limits->stores);
        $this->assertFalse($limits->allows('stores', 2));
    }

    #[Test]
    public function the_snapshot_is_cached_and_flushed_when_central_rows_change(): void
    {
        $this->assertSame(ModuleState::Active, $this->entitlement()->state('pos'));

        // A raw query bypasses the model hook: the cached answer stays.
        TenantModule::whereKey($this->row('pos')->id)->toBase()->update(['status' => 'inactive']);
        $this->assertSame(ModuleState::Active, $this->entitlement()->state('pos'));

        // Model writes (ModuleManager, billing) flush it.
        $this->row('reporting')->update(['status' => TenantModuleStatus::Active]);
        $this->assertSame(ModuleState::Inactive, $this->entitlement()->state('pos'));

        $this->tenant->currentSubscription->update(['status' => SubscriptionStatus::Expired]);
        $this->assertSame(ModuleState::ReadOnly, $this->entitlement()->state('reporting'));
    }

    #[Test]
    public function a_tenant_without_subscription_may_use_core_modules(): void
    {
        $this->tenant->subscriptions()->delete();

        $e = $this->entitlement();
        $this->assertTrue($e->canActivate('core'));
        $this->assertFalse($e->canActivate('pos'));
        $this->assertSame(ModuleState::Active, $e->state('pos'), 'existing active rows stay usable');
        $this->assertNull($e->limits()->users);
    }
}
