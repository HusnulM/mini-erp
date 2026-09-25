<?php

namespace App\Central\Models;

use App\Central\Entitlement\FlushesEntitlementCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddon extends CentralModel
{
    use FlushesEntitlementCache;

    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    protected function entitlementTenantId(): ?string
    {
        return Subscription::whereKey($this->subscription_id)->value('tenant_id');
    }
}
