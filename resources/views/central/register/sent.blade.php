@extends('central.layout')

@section('title', 'Cek email Anda')

@section('content')
    <div class="card">
        <h1>Cek email Anda</h1>
        <p>Kami mengirim link verifikasi ke <strong>{{ $email }}</strong>. Klik link tersebut untuk mulai menyiapkan sistem Anda.</p>
        <p class="hint">Link berlaku {{ config('erp.registration.unverified_ttl_days') }} hari. Pendaftaran yang tidak diverifikasi akan dihapus otomatis.</p>
    </div>
@endsection
