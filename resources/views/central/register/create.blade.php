@extends('central.layout')

@section('title', 'Daftar · '.config('app.name'))

@push('head')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endpush

@section('content')
    <div class="card">
        <h1>Daftar {{ config('app.name') }}</h1>
        <p class="hint">Sistem Anda disiapkan otomatis di subdomain sendiri setelah email diverifikasi.</p>

        @if ($errors->any())
            <div class="alert alert-err" role="alert">Periksa kembali isian yang ditandai.</div>
        @endif

        <form method="POST" action="{{ central_route('register.store') }}" novalidate>
            @csrf

            <label for="company_name">Nama perusahaan</label>
            <input id="company_name" name="company_name" value="{{ old('company_name') }}" maxlength="150" required autofocus>
            @error('company_name')<p class="error">{{ $message }}</p>@enderror

            <label for="slug">Subdomain</label>
            <div class="suffix">
                <input id="slug" name="slug" value="{{ old('slug') }}" maxlength="30" pattern="[a-z0-9-]{3,30}" autocapitalize="off" required>
                <span>.{{ $baseDomain }}</span>
            </div>
            <p class="hint">3–30 karakter: huruf kecil, angka, tanda minus.</p>
            @error('slug')<p class="error">{{ $message }}</p>@enderror

            <div class="row">
                <div>
                    <label for="owner_name">Nama pemilik</label>
                    <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="150" required>
                    @error('owner_name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="owner_email">Email pemilik</label>
                    <input id="owner_email" type="email" name="owner_email" value="{{ old('owner_email') }}" required>
                    @error('owner_email')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="password">Password admin</label>
                    <input id="password" type="password" name="password" minlength="{{ config('erp.registration.password_min') }}" autocomplete="new-password" required>
                    <p class="hint">Minimal {{ config('erp.registration.password_min') }} karakter.</p>
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation">Ulangi password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
            </div>

            <label for="phone">Nomor HP <span class="hint">(opsional)</span></label>
            <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" maxlength="30">
            @error('phone')<p class="error">{{ $message }}</p>@enderror

            <label>Paket</label>
            <div class="plans">
                @foreach ($plans as $plan)
                    <label class="plan">
                        <input type="radio" name="plan" value="{{ $plan->code }}" @checked(old('plan', $plans->first()?->code) === $plan->code)>
                        <strong>{{ $plan->name }}</strong>
                        Rp {{ number_format($plan->price_monthly, 0, ',', '.') }}/bulan
                        · Rp {{ number_format($plan->price_yearly, 0, ',', '.') }}/tahun<br>
                        <span class="hint">Trial {{ $plan->trial_days }} hari · {{ $plan->modules->pluck('name')->join(', ') }}</span>
                    </label>
                @endforeach
            </div>
            @error('plan')<p class="error">{{ $message }}</p>@enderror

            <label for="billing_cycle">Siklus tagihan</label>
            <select id="billing_cycle" name="billing_cycle">
                @foreach ($cycles as $cycle)
                    <option value="{{ $cycle }}" @selected(old('billing_cycle', 'monthly') === $cycle)>{{ $cycle === 'yearly' ? 'Tahunan' : 'Bulanan' }}</option>
                @endforeach
            </select>
            @error('billing_cycle')<p class="error">{{ $message }}</p>@enderror

            <div style="margin-top: 16px" class="cf-turnstile" data-sitekey="{{ $captchaSiteKey }}" data-language="id"></div>
            @error('cf-turnstile-response')<p class="error">{{ $message }}</p>@enderror

            <p style="margin-top: 20px"><button class="btn" type="submit">Daftar</button></p>
        </form>
    </div>
@endsection
