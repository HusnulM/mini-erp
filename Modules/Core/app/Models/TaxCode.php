<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;

class TaxCode extends Model
{
    use Auditable, HasUserStamps, SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'rate', 'type', 'effective_from', 'effective_to', 'account_id', 'status'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];
    }
}
