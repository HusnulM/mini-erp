<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $readonly ? 'Mode baca saja' : 'Modul tidak aktif' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; }
        main { max-width: 560px; margin: 96px auto; padding: 0 16px; text-align: center; }
    </style>
</head>
<body>
<main>
    <h1>{{ $readonly ? 'Mode baca saja' : 'Modul tidak aktif' }}</h1>
    <p>{{ $message }}</p>
    <p><a href="/">Kembali ke dashboard</a></p>
</main>
</body>
</html>
