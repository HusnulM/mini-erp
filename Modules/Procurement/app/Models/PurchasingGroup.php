<?php

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;
use Modules\Core\Models\User;

/** A purchasing team ("purchaser"), e.g. PG01 Pembelian Makanan. */
class PurchasingGroup extends Model
{
    use Auditable, HasUserStamps, SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'lead_user_id', 'max_po_amount', 'is_active'];

    protected function casts(): array
    {
        return ['max_po_amount' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'purchasing_group_members')->withPivot('role');
    }
}
