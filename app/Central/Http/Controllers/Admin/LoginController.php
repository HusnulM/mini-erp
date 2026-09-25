<?php

namespace App\Central\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Operator login (guard "central"). TDD §11: 5 attempts per minute per
 * IP + email, and the email is locked for 15 minutes after 10 failures.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('central.admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower($credentials['email']);
        $perIp = "central-login:{$email}|{$request->ip()}";
        $perAccount = "central-login-lock:{$email}";

        foreach ([$perIp, $perAccount] as $key) {
            if (RateLimiter::tooManyAttempts($key, $key === $perIp ? 5 : 10)) {
                throw ValidationException::withMessages([
                    'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
                ]);
            }
        }

        $ok = Auth::guard('central')->attempt(
            ['email' => $email, 'password' => $credentials['password'], 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($perIp, 60);
            RateLimiter::hit($perAccount, 15 * 60);

            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($perIp);
        RateLimiter::clear($perAccount);
        $request->session()->regenerate();
        Auth::guard('central')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(central_route('admin.tenants.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(central_route('admin.login'));
    }
}
