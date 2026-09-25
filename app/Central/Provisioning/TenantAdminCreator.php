<?php

namespace App\Central\Provisioning;

use App\Central\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Models\User;
use RuntimeException;

/**
 * create_admin step: the owner becomes the first tenant user, with the
 * password hash from registration and the SUPER ADMIN role. The hash is
 * then removed from the central database (TDD §7).
 */
class TenantAdminCreator
{
    public const USERNAME = 'admin';

    /** @return string the admin's email */
    public function create(Tenant $tenant): string
    {
        $registration = $tenant->registration();
        $hash = isset($registration['admin_password']) ? Crypt::decryptString($registration['admin_password']) : null;
        $role = config('erp.provisioning.admin_role');

        $tenant->run(function () use ($tenant, $hash, $role) {
            $user = User::where('email', $tenant->owner_email)->first();

            if (! $user) {
                if ($hash === null) {
                    throw new RuntimeException('Admin user does not exist and the registration password has already been removed.');
                }

                $user = User::create([
                    'name' => $tenant->owner_name,
                    'username' => self::USERNAME,
                    'email' => $tenant->owner_email,
                    'password' => $hash,
                    'status' => 'active',
                ]);
                // The owner proved the address during registration.
                $user->forceFill(['email_verified_at' => $tenant->email_verified_at ?? now()])->save();
            }

            $user->assignRole($role);
        });

        if (isset($registration['admin_password'])) {
            unset($registration['admin_password']);
            $tenant->setRegistration($registration)->save();
        }

        return $tenant->owner_email;
    }
}
