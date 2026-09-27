<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    protected $fillable = ['fiscal_year_id', 'period', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
