<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Concerns\Auditable;

class DocumentSequence extends Model
{
    use Auditable;

    protected $fillable = ['company_id', 'branch_id', 'module', 'document_type', 'prefix', 'format', 'reset_period', 'next_number', 'current_period', 'updated_by'];

    /** Taking a number is not a configuration change. */
    protected array $auditExclude = ['next_number', 'current_period', 'branch_key'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
