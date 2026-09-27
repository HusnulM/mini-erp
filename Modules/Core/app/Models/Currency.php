<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

class Currency extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
