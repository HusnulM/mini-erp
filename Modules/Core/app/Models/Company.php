<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;

/** A legal entity inside the tenant (TDD ADR-02: one tenant, many companies). */
class Company extends Model
{
    use Auditable, HasUserStamps, SoftDeletes;

    protected $fillable = ['code', 'name', 'legal_name', 'tax_id', 'address', 'base_currency', 'fiscal_year_start_month', 'timezone', 'logo_path', 'status'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }
}
