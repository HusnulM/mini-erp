<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;
use Modules\Core\Concerns\ScopedByOrganization;

class Warehouse extends Model
{
    use Auditable, HasUserStamps, ScopedByOrganization, SoftDeletes;

    protected $fillable = ['company_id', 'branch_id', 'code', 'name', 'type', 'allow_negative_stock', 'status'];

    protected function casts(): array
    {
        return ['allow_negative_stock' => 'boolean'];
    }

    public function dataScopeColumns(): array
    {
        return ['company' => 'company_id', 'branch' => 'branch_id', 'warehouse' => 'id', 'own' => 'created_by'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}
