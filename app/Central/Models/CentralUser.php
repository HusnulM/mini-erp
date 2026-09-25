<?php

namespace App\Central\Models;

use App\Central\Enums\CentralUserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * SaaS operator account (owner / support / billing). Authenticates with the
 * `central` guard on central domains only.
 */
class CentralUser extends Authenticatable
{
    use Notifiable;

    protected $connection = 'central';

    protected $table = 'central_users';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => CentralUserRole::class,
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
        ];
    }
}
