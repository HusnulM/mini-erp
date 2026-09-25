<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} · Mini ERP</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; }
        main { max-width: 720px; margin: 64px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 24px; }
        dt { font-weight: 600; margin-top: 12px; } dd { margin: 4px 0 0; }
        code { background: #eef1f4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>{{ $tenant->name }}</h1>
        <p>Tenant context aktif. Halaman ini placeholder sampai setup wizard (Sprint 4).</p>
        <dl>
            <dt>Tenant ID</dt><dd><code>{{ $tenant->id }}</code></dd>
            <dt>Status</dt><dd>{{ $tenant->status->value }}</dd>
            <dt>Database</dt><dd><code>{{ \Illuminate\Support\Facades\DB::connection()->getDatabaseName() }}</code></dd>
        </dl>
    </div>
</main>
</body>
</html>
