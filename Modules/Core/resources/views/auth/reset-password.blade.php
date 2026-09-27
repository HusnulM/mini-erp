@extends('core::layouts.app')

@section('title', 'Buat password')

@section('content')
    <div class="card" style="max-width:420px;margin:40px auto">
        <h1>Buat password</h1>
        <form method="POST" action="{{ route('core.password.update', absolute: false) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            <label for="password">Password baru</label>
            <input id="password" type="password" name="password" minlength="{{ config('erp.registration.password_min') }}" required>
            <label for="password_confirmation">Ulangi password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
            <div class="actions"><button class="btn" type="submit">Simpan password</button></div>
        </form>
    </div>
@endsection
