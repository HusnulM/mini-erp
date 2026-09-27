<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;

/** Bin / rack inside a warehouse (hierarchical). */
class WarehouseLocation extends Model
{
    use Auditable, HasUserStamps, SoftDeletes;

    protected $fillable = ['warehouse_id', 'parent_id', 'code', 'name', 'status'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
