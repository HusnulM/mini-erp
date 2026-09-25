@extends('central.layout')

@section('title', 'Login operator')

@section('content')
    <div class="card" style="max-width: 420px; margin: 40px auto">
        <h1>Login operator</h1>
        <form method="POST" action="{{ central_route('admin.login.store') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="error">{{ $message }}</p>@enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label style="font-weight: normal"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            <p><button class="btn" type="submit">Masuk</button></p>
        </form>
    </div>
@endsection
