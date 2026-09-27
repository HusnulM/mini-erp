<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tenant user login (guard "web", tenant database). TDD §11: 5 attempts per
 * minute per IP + login, and the login is locked for 15 minutes after 10
 * failures. After login, the `setup` middleware sends admins to the setup
 * wizard until it is done.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('core::auth.login', ['tenant' => tenant()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = Str::lower($data['login']);
        $key = 'tenant-login:'.tenant()->getTenantKey().":{$login}|{$request->ip()}";
        $lock = 'tenant-login-lock:'.tenant()->getTenantKey().":{$login}";

        foreach ([$key => 5, $lock => 10] as $limiterKey => $max) {
            if (RateLimiter::tooManyAttempts($limiterKey, $max)) {
                throw ValidationException::withMessages([
                    'login' => 'Terlalu banyak percobaan login. Coba lagi dalam '.ceil(RateLimiter::availableIn($limiterKey) / 60).' menit.',
                ]);
            }
        }

        $field = str_contains($login, '@') ? 'email' : 'username';

        if (! Auth::guard('web')->attempt([$field => $login, 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($lock, 15 * 60);

            throw ValidationException::withMessages(['login' => 'Email/username atau password salah.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($lock);
        $request->session()->regenerate();
        Auth::guard('web')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('core.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('core.login');
    }
}
