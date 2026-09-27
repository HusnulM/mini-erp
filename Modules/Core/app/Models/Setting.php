<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

/** Raw setting row; read and write through Modules\\Core\\Services\\Settings. */
class Setting extends Model
{
    use Auditable;

    protected $fillable = ['company_id', 'module', 'key', 'value', 'updated_by'];

    protected array $auditExclude = ['company_key'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
