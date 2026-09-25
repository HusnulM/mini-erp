<?php

namespace App\Central\Console;

use App\Central\Enums\ProvisioningStatus;
use App\Central\Http\Requests\RegisterTenantRequest;
use App\Central\Models\Plan;
use App\Central\Provisioning\ProvisioningRunner;
use App\Central\Registration\RegisterTenant;
use App\Central\Registration\VerifyRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Development helper: registration without the form, email or captcha,
 * provisioned synchronously through the same steps as ProvisionTenant
 * (subscription, plan modules, admin user, logged run).
 */
class CreateTenantCommand extends Command
{
    protected $signature = 'erp:tenant:create
        {slug : Subdomain, e.g. tokoabc}
        {--name= : Company name}
        {--email= : Owner email (also the admin login)}
        {--password= : Admin password (default: random, printed)}
        {--plan=STARTER : Plan code}';

    protected $description = '[dev] Create and provision a tenant (own database, MySQL user, plan modules, admin)';

    public function handle(RegisterTenant $register, VerifyRegistration $verify, ProvisioningRunner $runner): int
    {
        $slug = strtolower((string) $this->argument('slug'));
        $password = $this->option('password') ?: Str::password(16, symbols: false);
        $data = [
            'slug' => $slug,
            'company_name' => $this->option('name') ?: ucfirst($slug),
            'owner_name' => $this->option('name') ?: ucfirst($slug),
            'owner_email' => $this->option('email') ?: "owner@{$slug}.test",
            'password' => $password,
            'billing_cycle' => 'monthly',
            'plan' => strtoupper((string) $this->option('plan')),
        ];

        $validator = Validator::make($data, [
            'slug' => ['required', 'regex:'.RegisterTenantRequest::SLUG_PATTERN, 'not_in:'.implode(',', config('erp.reserved_subdomains')), 'unique:central.tenants,slug'],
            'company_name' => ['required', 'max:150'],
            'owner_email' => ['required', 'email', 'unique:central.tenants,owner_email'],
            'plan' => ['required', 'exists:central.plans,code'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $tenant = $register($data, Plan::where('code', $data['plan'])->firstOrFail(), sendVerification: false);
        $run = $verify($tenant, dispatch: false);

        // Same runner as the queued job; retries happen immediately here.
        while ($runner->execute($run) !== null) {
            $this->components->warn('Step failed, retrying: '.$run->fresh()->error);
        }

        $run->refresh();
        $this->table(['#', 'Step', 'Status', 'Attempts', 'Message'], $run->steps->map(fn ($s) => [
            $s->seq, $s->step, $s->status->value, $s->attempts, Str::limit((string) $s->message, 80),
        ]));

        if ($run->status !== ProvisioningStatus::Done) {
            $this->components->error('Provisioning failed: '.$run->error);

            return self::FAILURE;
        }

        $tenant->refresh();
        $this->components->twoColumnDetail('Tenant ID', $tenant->id);
        $this->components->twoColumnDetail('Plan', $data['plan']);
        $this->components->twoColumnDetail('Database', $tenant->db_name);
        $this->components->twoColumnDetail('MySQL user', $tenant->db_username);
        $this->components->twoColumnDetail('Login', $tenant->url('login'));
        $this->components->twoColumnDetail('Admin', $tenant->owner_email.' / '.($this->option('password') ? '(from --password)' : $password));

        return self::SUCCESS;
    }
}
