<?php

namespace App\Central\Models;

use App\Central\Entitlement\FlushesEntitlementCache;
use App\Central\Enums\ModuleSource;
use App\Central\Enums\TenantModuleStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends CentralModel
{
    use FlushesEntitlementCache;

    protected function casts(): array
    {
        return [
            'status' => TenantModuleStatus::class,
            'source' => ModuleSource::class,
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
