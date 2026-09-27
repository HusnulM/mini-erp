<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;
use Modules\Core\Concerns\HasUserStamps;
use Modules\Core\Concerns\ScopedByOrganization;

class Branch extends Model
{
    use Auditable, HasUserStamps, ScopedByOrganization, SoftDeletes;

    protected $fillable = ['company_id', 'code', 'name', 'address', 'cost_center_id', 'status'];

    public function dataScopeColumns(): array
    {
        return ['company' => 'company_id', 'branch' => 'id', 'own' => 'created_by'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
