<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ $tenant->name }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; }
        main { max-width: 420px; margin: 64px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 24px; }
        label { display: block; font-weight: 600; margin: 14px 0 4px; }
        input { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd2d9; border-radius: 6px; font: inherit; }
        .error { color: #c81e1e; font-size: .875rem; margin: 4px 0 0; }
        button { margin-top: 16px; background: #1c64f2; color: #fff; border: 0; border-radius: 6px; padding: 9px 16px; font: inherit; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>{{ $tenant->name }}</h1>
        @if (session('status'))<p style="color:#03543f">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('core.login.store', absolute: false) }}">
            @csrf
            <label for="login">Email atau username</label>
            <input id="login" name="login" value="{{ old('login') }}" required autofocus>
            @error('login')<p class="error">{{ $message }}</p>@enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button type="submit">Masuk</button>
            <p><a href="{{ route('core.password.request', absolute: false) }}">Lupa password?</a></p>
        </form>
    </div>
</main>
</body>
</html>
