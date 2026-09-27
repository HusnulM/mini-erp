<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Concerns\Auditable;
use RuntimeException;

/** Simple dropdown values without behavior (TDD §9 "lookups"). */
class Lookup extends Model
{
    use Auditable;

    protected $fillable = ['module', 'group', 'code', 'label', 'sort', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $lookup) {
            if ($lookup->is_system) {
                throw new RuntimeException("System lookup [{$lookup->group}.{$lookup->code}] cannot be deleted.");
            }
        });
    }

    /** @return Collection<int, self> active options of a dropdown, sorted */
    public static function options(string $module, string $group): Collection
    {
        return static::where('module', $module)->where('group', $group)->where('is_active', true)
            ->orderBy('sort')->orderBy('label')->get();
    }
}
