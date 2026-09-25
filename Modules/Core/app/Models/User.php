<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant user. No $connection: it uses the default connection, which
 * tenancy switches to the current tenant's database.
 */
class User extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'username', 'email', 'password', 'status', 'default_company_id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
        ];
    }
}
