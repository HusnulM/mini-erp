<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class SetupProgress extends Model
{
    protected $table = 'setup_progress';

    protected $fillable = ['step', 'status', 'completed_at', 'data'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'data' => 'array'];
    }
}
