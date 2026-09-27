<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

class ExchangeRate extends Model
{
    use Auditable;

    protected $fillable = ['currency_code', 'date', 'rate'];

    protected function casts(): array
    {
        return ['date' => 'date', 'rate' => 'decimal:6'];
    }
}
