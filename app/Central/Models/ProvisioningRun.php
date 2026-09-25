<?php

namespace App\Central\Models;

use App\Central\Enums\ProvisioningRunType;
use App\Central\Enums\ProvisioningStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProvisioningRun extends CentralModel
{
    protected function casts(): array
    {
        return [
            'type' => ProvisioningRunType::class,
            'status' => ProvisioningStatus::class,
            'context' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProvisioningStep::class, 'run_id')->orderBy('seq');
    }
}
