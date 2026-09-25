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
 * Tenant user login (guard "web", tenant database). Minimal for Sprint 2 so
 * the "system ready" email leads somewhere; Sprint 4 adds the account lock,
 * 2FA and the setup-wizard redirect (TDD §9, §11).
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

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $field = str_contains($login, '@') ? 'email' : 'username';

        if (! Auth::guard('web')->attempt([$field => $login, 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['login' => 'Email/username atau password salah.']);
        }

        RateLimiter::clear($key);
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
