<?php

namespace App\Central\Console;

use App\Central\Enums\TenantStatus;
use App\Central\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Deletes registrations whose email was not verified within
 * erp.registration.unverified_ttl_days (TDD §7). They never got a database,
 * so only central rows (tenant + domain) are removed. Scheduled daily.
 */
class PurgeUnverifiedRegistrationsCommand extends Command
{
    protected $signature = 'erp:registrations:purge';

    protected $description = 'Delete registrations that were not email-verified in time';

    public function handle(): int
    {
        $days = (int) config('erp.registration.unverified_ttl_days');
        $deleted = 0;

        Tenant::query()
            ->where('status', TenantStatus::Pending)
            ->whereNull('email_verified_at')
            ->whereNull('db_name')
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (Tenant $tenant) use (&$deleted) {
                $tenant->delete(); // domains cascade
                $deleted++;
            });

        $this->components->info("{$deleted} unverified registration(s) older than {$days} days deleted.");

        return self::SUCCESS;
    }
}
