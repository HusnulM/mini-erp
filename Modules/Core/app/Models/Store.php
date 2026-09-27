<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;
use Modules\Core\Concerns\ScopedByOrganization;

class Store extends Model
{
    use Auditable, HasUserStamps, ScopedByOrganization, SoftDeletes;

    protected $fillable = ['company_id', 'branch_id', 'code', 'name', 'default_warehouse_id', 'price_list_id', 'status'];

    public function dataScopeColumns(): array
    {
        return ['company' => 'company_id', 'branch' => 'branch_id', 'store' => 'id', 'own' => 'created_by'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}
