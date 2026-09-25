<?php

namespace App\Central\Models;

use App\Central\Enums\ProvisioningStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningStep extends CentralModel
{
    protected function casts(): array
    {
        return [
            'status' => ProvisioningStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProvisioningRun::class, 'run_id');
    }
}
