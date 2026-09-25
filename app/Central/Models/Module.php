<?php

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catalog entry for a module. Rows are synced from Modules/{Name}/module.json
 * (`php artisan modules:sync`); do not edit them by hand.
 */
class Module extends CentralModel
{
    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_addon' => 'boolean',
            'price_monthly' => 'decimal:4',
            'manifest' => 'array',
        ];
    }

    public function requires(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'module_dependencies', 'module_id', 'requires_module_id')
            ->withPivot('is_optional');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_modules');
    }
}
