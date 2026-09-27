<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Outbox row for a cross-module domain event (TDD §6). Processing comes with the modules. */
class ModuleEvent extends Model
{
    protected $fillable = ['event_type', 'source_type', 'source_id', 'payload', 'status', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
