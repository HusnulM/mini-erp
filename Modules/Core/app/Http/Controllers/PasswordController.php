<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Modules\Core\Models\User;

/** Forgot / reset password for tenant users; also used by user invitations. */
class PasswordController extends Controller
{
    public function request(): View
    {
        return view('core::auth.forgot-password', ['tenant' => tenant()]);
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Same answer whether or not the address exists.
        Password::broker('users')->sendResetLink(['email' => $request->input('email'), 'status' => 'active']);

        return back()->with('status', 'Jika email terdaftar, link reset password sudah dikirim.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('core::auth.reset-password', ['tenant' => tenant(), 'token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(config('erp.registration.password_min'))],
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('core.login')->with('status', 'Password tersimpan. Silakan masuk.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Link tidak valid atau sudah kedaluwarsa.']);
    }
}
