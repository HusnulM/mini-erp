<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Concerns\Auditable;

class UserScope extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'scope_type', 'scope_id'];
}
