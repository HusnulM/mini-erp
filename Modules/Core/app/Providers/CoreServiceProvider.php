<?php

namespace Modules\Core\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $code = 'core';

    public function boot(): void
    {
        parent::boot();

        // Reset links point to the tenant's own domain.
        ResetPassword::createUrlUsing(fn ($user, string $token) => tenant()->url(
            'password/reset/'.$token.'?'.http_build_query(['email' => $user->getEmailForPasswordReset()])
        ));
    }
}
