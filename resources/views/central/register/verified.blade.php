@extends('central.layout')

@section('title', 'Email terverifikasi')

@section('content')
    <div class="card">
        @if ($tenant->status->canAccess())
            <h1>Sistem Anda sudah siap</h1>
            <p><a class="btn" href="{{ $tenant->url('login') }}">Masuk ke {{ $tenant->primaryDomain() }}</a></p>
        @elseif ($tenant->status === \App\Central\Enums\TenantStatus::Failed)
            <h1>Email terverifikasi</h1>
            <p>Penyiapan sistem untuk <strong>{{ $tenant->name }}</strong> mengalami kendala. Tim kami sudah diberi tahu dan sedang menanganinya; Anda akan menerima email begitu sistem siap.</p>
        @else
            <h1>Email terverifikasi</h1>
            <p>Sistem untuk <strong>{{ $tenant->name }}</strong> sedang disiapkan di <code>{{ $tenant->primaryDomain() }}</code>. Biasanya kurang dari satu menit; kami akan mengirim email berisi link login begitu siap.</p>
        @endif
    </div>
@endsection
