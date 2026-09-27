<?php

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;

/** Purchasing type, e.g. REG Regular, SRV Service (no goods receipt). */
class ProcurementType extends Model
{
    use Auditable, HasUserStamps, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'requires_pr', 'requires_gr', 'pr_sequence_id', 'po_sequence_id',
        'pr_workflow_id', 'po_workflow_id', 'allowed_product_types', 'account_mapping_key', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_pr' => 'boolean',
            'requires_gr' => 'boolean',
            'is_active' => 'boolean',
            'allowed_product_types' => 'array',
        ];
    }
}
