<?php

namespace App\Central\Models;

use App\Central\Enums\TenantMode;
use App\Central\Enums\TenantStatus;
use App\Tenancy\Database\TenantDatabaseConfig;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\DatabaseConfig;

/**
 * A SaaS customer. One tenant = one database; a tenant can hold many
 * companies (TDD ADR-02).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property TenantMode $mode
 * @property ?string $db_name
 * @property ?string $db_username
 * @property ?string $db_password decrypted on read
 * @property ?string $db_host
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * stancl keeps db credentials as "tenancy_db_*" keys inside the JSON data
     * column. We keep them in real columns instead (encrypted password), so
     * these internal keys are mapped to columns.
     */
    protected const INTERNAL_COLUMNS = [
        'db_name' => 'db_name',
        'db_username' => 'db_username',
        'db_password' => 'db_password',
        'db_host' => 'db_host',
    ];

    protected $hidden = ['db_password'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'mode' => TenantMode::class,
            'db_password' => 'encrypted',
            'trial_ends_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /** Real columns; anything else goes into the JSON `data` column. */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'code', 'name', 'slug', 'owner_name', 'owner_email', 'phone',
            'status', 'mode', 'db_name', 'db_username', 'db_password', 'db_host',
            'trial_ends_at', 'setup_completed_at', 'suspended_at',
            'created_at', 'updated_at',
        ];
    }

    public function getInternal(string $key)
    {
        if (isset(self::INTERNAL_COLUMNS[$key])) {
            return $this->getAttribute(self::INTERNAL_COLUMNS[$key]);
        }

        return parent::getInternal($key);
    }

    public function setInternal(string $key, $value)
    {
        if (isset(self::INTERNAL_COLUMNS[$key])) {
            $this->setAttribute(self::INTERNAL_COLUMNS[$key], $value);

            return $this;
        }

        return parent::setInternal($key, $value);
    }

    public function database(): DatabaseConfig
    {
        return new TenantDatabaseConfig($this);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function provisioningRuns(): HasMany
    {
        return $this->hasMany(ProvisioningRun::class);
    }

    public function primaryDomain(): ?string
    {
        return $this->domains->firstWhere('is_primary', true)?->domain
            ?? $this->domains->first()?->domain;
    }
}
