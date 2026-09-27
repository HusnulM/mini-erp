@extends('core::layouts.app')

@section('title', 'Setup: Perusahaan')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card">
            <h1>1. Perusahaan</h1>
            <form method="POST" action="{{ route('core.setup.company.save', absolute: false) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid2">
                    <div>
                        <label for="code">Kode</label>
                        <input id="code" name="code" value="{{ old('code', $company?->code ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', tenant()->slug), 0, 20))) }}" required>
                        <p class="hint">Huruf besar, angka, minus.</p>
                    </div>
                    <div>
                        <label for="name">Nama</label>
                        <input id="name" name="name" value="{{ old('name', $company?->name ?? tenant()->name) }}" required>
                    </div>
                    <div>
                        <label for="legal_name">Nama legal</label>
                        <input id="legal_name" name="legal_name" value="{{ old('legal_name', $company?->legal_name) }}" placeholder="PT ...">
                    </div>
                    <div>
                        <label for="tax_id">NPWP</label>
                        <input id="tax_id" name="tax_id" value="{{ old('tax_id', $company?->tax_id) }}" inputmode="numeric">
                    </div>
                    <div>
                        <label for="base_currency">Mata uang dasar</label>
                        <select id="base_currency" name="base_currency">
                            @foreach ($currencies as $c)
                                <option value="{{ $c->code }}" @selected(old('base_currency', $company?->base_currency ?? 'IDR') === $c->code)>{{ $c->code }} · {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="timezone">Zona waktu</label>
                        <select id="timezone" name="timezone">
                            @foreach (['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'] as $tz => $label)
                                <option value="{{ $tz }}" @selected(old('timezone', $company?->timezone ?? 'Asia/Jakarta') === $tz)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label for="address">Alamat</label>
                <textarea id="address" name="address" rows="3">{{ old('address', $company?->address) }}</textarea>
                <label for="logo">Logo <span class="hint">(opsional, gambar maks. 2 MB)</span></label>
                <input id="logo" type="file" name="logo" accept="image/*">
                <div class="actions"><button class="btn" type="submit">Simpan &amp; lanjut</button></div>
            </form>
        </div>
    </div>
@endsection
