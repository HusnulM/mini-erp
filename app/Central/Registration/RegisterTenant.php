<?php

namespace App\Central\Registration;

use App\Central\Enums\TenantMode;
use App\Central\Enums\TenantStatus;
use App\Central\Models\Plan;
use App\Central\Models\Tenant;
use App\Central\Notifications\VerifyRegistrationEmail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Step 1 of TDD §7: store the registration as a `pending` tenant with its
 * domain and email a verification link. No database is created until the
 * email is verified (VerifyRegistration).
 */
class RegisterTenant
{
    /**
     * @param  array{company_name: string, slug: string, owner_name: string, owner_email: string,
     *               password: string, phone?: ?string, billing_cycle: string}  $data
     */
    public function __invoke(array $data, Plan $plan, bool $sendVerification = true): Tenant
    {
        $tenant = DB::connection('central')->transaction(function () use ($data, $plan) {
            $tenant = new Tenant([
                'code' => strtoupper($data['slug']),
                'name' => $data['company_name'],
                'slug' => $data['slug'],
                'owner_name' => $data['owner_name'],
                'owner_email' => $data['owner_email'],
                'phone' => $data['phone'] ?? null,
                'status' => TenantStatus::Pending,
                'mode' => TenantMode::Saas,
            ]);

            // Only a hash is kept, encrypted with APP_KEY, and it is removed
            // once the tenant admin user exists (create_admin step).
            $tenant->setRegistration([
                'plan_id' => $plan->id,
                'billing_cycle' => $data['billing_cycle'],
                'admin_password' => Crypt::encryptString(Hash::make($data['password'])),
            ]);
            $tenant->save();

            $tenant->domains()->create([
                'domain' => $data['slug'].'.'.config('erp.tenant_base_domain'),
                'is_primary' => true,
            ]);

            return $tenant;
        });

        if ($sendVerification) {
            Notification::route('mail', [$tenant->owner_email => $tenant->owner_name])
                ->notify(new VerifyRegistrationEmail($tenant));
        }

        return $tenant;
    }
}
