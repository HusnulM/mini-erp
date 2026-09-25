<?php

namespace App\Central\Http\Controllers;

use App\Central\Http\Requests\RegisterTenantRequest;
use App\Central\Models\Plan;
use App\Central\Models\Tenant;
use App\Central\Registration\CaptchaVerifier;
use App\Central\Registration\RegisterTenant;
use App\Central\Registration\VerifyRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(CaptchaVerifier $captcha): View
    {
        return view('central.register.create', [
            'plans' => Plan::where('is_public', true)->where('is_active', true)->with('modules')->orderBy('sort')->get(),
            'cycles' => config('erp.registration.billing_cycles'),
            'baseDomain' => config('erp.tenant_base_domain'),
            'captchaSiteKey' => $captcha->siteKey(),
        ]);
    }

    public function store(RegisterTenantRequest $request, RegisterTenant $register): RedirectResponse
    {
        try {
            $tenant = $register($request->safe()->except(['cf-turnstile-response', 'password_confirmation', 'plan']), $request->plan());
        } catch (UniqueConstraintViolationException) {
            // Two registrations for the same slug/email passed validation at once.
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['slug' => 'Subdomain atau email ini baru saja dipakai. Silakan coba yang lain.']);
        }

        return redirect()->to(central_route('register.sent'))->with('registered_email', $tenant->owner_email);
    }

    public function sent(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('registered_email')) {
            return redirect()->to(central_route('register'));
        }

        return view('central.register.sent', ['email' => $request->session()->get('registered_email')]);
    }

    public function verify(Tenant $tenant, string $hash, VerifyRegistration $verify): View
    {
        abort_unless(hash_equals(sha1($tenant->owner_email), $hash), 403);

        $verify($tenant);

        return view('central.register.verified', ['tenant' => $tenant->fresh('domains')]);
    }
}
