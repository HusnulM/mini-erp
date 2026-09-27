@extends('core::layouts.app')

@section('title', 'Lupa password')

@section('content')
    <div class="card" style="max-width:420px;margin:40px auto">
        <h1>Lupa password</h1>
        <form method="POST" action="{{ route('core.password.email', absolute: false) }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <div class="actions"><button class="btn" type="submit">Kirim link reset</button> <a href="{{ route('core.login', absolute: false) }}">Masuk</a></div>
        </form>
    </div>
@endsection
