@extends('core::layouts.app')

@section('title', 'Setup awal')

@section('content')
    <div class="layout">
        @include('core::setup._steps', ['current' => null])
        <div class="card">
            <h1>Setup awal</h1>
            @if ($completed)
                <p>Setup sudah selesai. Anda tetap bisa mengubah pengaturan dari menu.</p>
                <p><a class="btn" href="{{ route('core.dashboard', absolute: false) }}">Ke dashboard</a></p>
            @else
                <p>Selesaikan langkah wajib sebelum mulai memakai sistem. Progres tersimpan, jadi Anda bisa melanjutkan kapan saja, juga setelah keluar.</p>
                @if ($missing)
                    <p>Langkah wajib yang belum selesai: <strong>{{ implode(', ', $missing) }}</strong>.</p>
                    @php($next = collect($steps)->first(fn ($s) => $s['status'] === 'pending'))
                    @if ($next)<p><a class="btn" href="{{ route($next['route'], absolute: false) }}">Lanjut: {{ $next['label'] }}</a></p>@endif
                @else
                    <p>Semua langkah wajib sudah selesai.</p>
                    <form method="POST" action="{{ route('core.setup.finish', absolute: false) }}">
                        @csrf
                        <button class="btn" type="submit">Selesaikan setup</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection
