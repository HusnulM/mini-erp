<?php

namespace App\Providers;

use App\Central\Console\CreateTenantCommand;
use App\Central\Console\DeleteTenantCommand;
use App\Central\Console\PurgeUnverifiedRegistrationsCommand;
use App\Central\Enums\CentralUserRole;
use App\Central\Models\CentralUser;
use App\Central\Registration\CaptchaVerifier;
use App\Central\Registration\TurnstileVerifier;
use App\Support\Modules\Console\SyncModulesCommand;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(config('erp.modules_path')));
        $this->app->bind(CaptchaVerifier::class, fn () => new TurnstileVerifier(config('services.turnstile')));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Operator panel: support may retry provisioning, billing only reads.
        Gate::define('retry-provisioning', fn (CentralUser $user) => in_array(
            $user->role, [CentralUserRole::Owner, CentralUserRole::Support], true
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncModulesCommand::class,
                CreateTenantCommand::class,
                DeleteTenantCommand::class,
                PurgeUnverifiedRegistrationsCommand::class,
            ]);
        }
    }
}
