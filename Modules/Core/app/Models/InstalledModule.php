<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modules migrated into this tenant database (mirror of central
 * tenant_modules; the only source in on-prem mode).
 */
class InstalledModule extends Model
{
    protected $fillable = ['module_code', 'version', 'installed_at', 'last_migrated_at'];

    protected function casts(): array
    {
        return ['installed_at' => 'datetime', 'last_migrated_at' => 'datetime'];
    }
}
