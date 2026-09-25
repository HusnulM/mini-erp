<?php

namespace App\Central\Http\Requests;

use App\Central\Models\Domain;
use App\Central\Models\Plan;
use App\Central\Registration\CaptchaVerifier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Registration form, TDD §7 "Form registrasi". */
class RegisterTenantRequest extends FormRequest
{
    /** Valid DNS label, 3–30 chars, no leading/trailing dash (stricter than ^[a-z0-9-]{3,30}$). */
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/';

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'owner_email' => strtolower(trim((string) $this->input('owner_email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(config('erp.reserved_subdomains')),
                Rule::unique('central.tenants', 'slug'),
                function (string $attribute, mixed $value, Closure $fail) {
                    $domain = $value.'.'.config('erp.tenant_base_domain');
                    if (Domain::where('domain', $domain)->exists()) {
                        $fail('Subdomain ini sudah dipakai.');
                    }
                },
            ],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'string', 'email', 'max:255', Rule::unique('central.tenants', 'owner_email')],
            'password' => [
                'required', 'string', 'confirmed', 'max:255',
                Password::min(config('erp.registration.password_min'))->uncompromised(),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'plan' => [
                'required', 'string',
                Rule::exists('central.plans', 'code')->where('is_public', true)->where('is_active', true),
            ],
            'billing_cycle' => ['required', Rule::in(config('erp.registration.billing_cycles'))],
            'cf-turnstile-response' => [
                'bail', 'required', 'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(CaptchaVerifier::class)->verify($value, $this->ip())) {
                        $fail('Verifikasi captcha gagal, silakan coba lagi.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => 'nama perusahaan',
            'slug' => 'subdomain',
            'owner_name' => 'nama pemilik',
            'owner_email' => 'email pemilik',
            'password' => 'password admin',
            'phone' => 'nomor HP',
            'plan' => 'paket',
            'billing_cycle' => 'siklus tagihan',
            'cf-turnstile-response' => 'captcha',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Subdomain 3–30 karakter: huruf kecil, angka, dan tanda minus (tidak di awal/akhir).',
            'slug.not_in' => 'Subdomain ini tidak boleh dipakai.',
            'slug.unique' => 'Subdomain ini sudah dipakai.',
            'owner_email.unique' => 'Email ini sudah terdaftar sebagai pemilik tenant lain.',
            'password.uncompromised' => 'Password ini pernah bocor di internet. Pilih password lain.',
            'plan.exists' => 'Paket tidak tersedia.',
            'cf-turnstile-response.required' => 'Selesaikan captcha terlebih dahulu.',
        ];
    }

    public function plan(): Plan
    {
        return Plan::where('code', $this->validated('plan'))->firstOrFail();
    }
}
