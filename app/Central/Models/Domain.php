<?php

namespace App\Central\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * @property string $domain full host, e.g. tokoabc.erp.localhost
 */
class Domain extends BaseDomain
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
