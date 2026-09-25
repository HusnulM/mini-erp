<?php

namespace App\Central\Console;

use App\Central\Enums\TenantMode;
use App\Central\Enums\TenantStatus;
use App\Central\Models\Tenant;
use App\Central\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Development helper: creates a tenant and provisions it synchronously.
 * Sprint 2 replaces this path with registration + the queued, step-logged
 * ProvisionTenant job; the provisioner methods stay the same.
 */
class CreateTenantCommand extends Command
{
    protected $signature = 'erp:tenant:create
        {slug : Subdomain, e.g. tokoabc}
        {--name= : Company name}
        {--email= : Owner email}';

    protected $description = '[dev] Create a tenant with its own database and MySQL user';

    public function handle(TenantDatabaseProvisioner $provisioner): int
    {
        $slug = strtolower((string) $this->argument('slug'));
        $data = [
            'slug' => $slug,
            'name' => $this->option('name') ?: ucfirst($slug),
            'email' => $this->option('email') ?: "owner@{$slug}.test",
        ];

        $validator = Validator::make($data, [
            'slug' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', 'not_in:'.implode(',', config('erp.reserved_subdomains')), 'unique:central.tenants,slug'],
            'name' => ['required', 'max:150'],
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $tenant = Tenant::create([
            'code' => strtoupper(str_replace('-', '', $slug)),
            'name' => $data['name'],
            'slug' => $slug,
            'owner_name' => $data['name'],
            'owner_email' => $data['email'],
            'status' => TenantStatus::Provisioning,
            'mode' => TenantMode::Saas,
        ]);

        $domain = $slug.'.'.config('erp.tenant_base_domain');
        $tenant->domains()->create(['domain' => $domain, 'is_primary' => true]);

        try {
            $this->components->task('Reserve names', fn () => $provisioner->reserveNames($tenant));
            $this->components->task('Create database', fn () => $provisioner->createDatabase($tenant));
            $this->components->task('Create MySQL user', fn () => $provisioner->createDatabaseUser($tenant));
            $this->components->task('Migrate core modules', fn () => $provisioner->migrateCoreModules($tenant));
            $this->components->task('Seed defaults', fn () => $provisioner->seed($tenant));
        } catch (Throwable $e) {
            $tenant->update(['status' => TenantStatus::Failed]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $tenant->update([
            'status' => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->components->twoColumnDetail('Tenant ID', $tenant->id);
        $this->components->twoColumnDetail('Database', $tenant->db_name);
        $this->components->twoColumnDetail('MySQL user', $tenant->db_username);
        $this->components->twoColumnDetail('URL', 'http://'.$domain);

        return self::SUCCESS;
    }
}
