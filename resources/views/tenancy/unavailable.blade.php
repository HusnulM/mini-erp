<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $preparing ? 'Sistem sedang disiapkan' : 'Akun tidak aktif' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; }
        main { max-width: 560px; margin: 96px auto; padding: 0 16px; text-align: center; }
    </style>
</head>
<body>
<main>
    @if ($preparing)
        <h1>Sistem sedang disiapkan</h1>
        <p>Database untuk <strong>{{ $tenant?->name }}</strong> sedang dibuat. Kami akan mengirim email begitu sistem siap.</p>
    @else
        <h1>Akun tidak aktif</h1>
        <p>Akses untuk <strong>{{ $tenant?->name }}</strong> sedang ditangguhkan. Hubungi administrator atau tim billing.</p>
    @endif
</main>
</body>
</html>
