<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

class FiscalYear extends Model
{
    use Auditable;

    protected $fillable = ['company_id', 'year', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('period');
    }
}
