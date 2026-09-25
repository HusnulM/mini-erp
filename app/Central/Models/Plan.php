<?php

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends CentralModel
{
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:4',
            'price_yearly' => 'decimal:4',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_modules');
    }
}
