@php
    $tenant ??= tenant();
    $menu = auth('web')->check() && $tenant->setup_completed_at ? app(\App\Support\Modules\MenuBuilder::class)->for(auth('web')->user()) : [];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenant->name) · Mini ERP</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; line-height: 1.5; }
        header { background: #1f2933; color: #fff; }
        header .inner { max-width: 1100px; margin: 0 auto; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center; }
        header a, header button { color: #fff; text-decoration: none; }
        header nav { display: flex; flex-wrap: wrap; gap: 4px 14px; flex: 1; }
        header nav .ro { opacity: .7; }
        header form { margin: 0; } header button { background: none; border: 0; font: inherit; cursor: pointer; text-decoration: underline; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        a { color: #1c64f2; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 20px 24px; margin-bottom: 16px; }
        .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
        .layout { display: grid; grid-template-columns: 240px 1fr; gap: 16px; align-items: start; }
        @media (max-width: 760px) { .layout { grid-template-columns: 1fr; } }
        label { display: block; font-weight: 600; margin: 12px 0 4px; }
        label.inline { display: inline-flex; gap: 6px; align-items: center; font-weight: normal; margin: 4px 12px 4px 0; }
        input, select, textarea { width: 100%; padding: 7px 10px; border: 1px solid #cbd2d9; border-radius: 6px; font: inherit; background: #fff; }
        input[type=checkbox], input[type=radio] { width: auto; }
        .hint { color: #616e7c; font-size: .875rem; margin: 2px 0 0; }
        .error { color: #c81e1e; font-size: .875rem; margin: 4px 0 0; }
        .alert { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .alert-ok { background: #def7ec; color: #03543f; } .alert-err { background: #fde8e8; color: #9b1c1c; }
        .btn { display: inline-block; background: #1c64f2; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-light { background: #e4e7eb; color: #1f2933; }
        .btn-sm { padding: 3px 10px; font-size: .8125rem; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #e4e7eb; vertical-align: top; }
        th { color: #52606d; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #e4e7eb; }
        .b-done, .b-active { background: #def7ec; color: #03543f; } .b-skipped { background: #fdf6b2; color: #723b13; } .b-pending { background: #e1effe; color: #1e429f; }
        .steps { list-style: none; padding: 0; margin: 0; } .steps li { padding: 6px 0; border-bottom: 1px solid #eef1f4; }
        .steps li.current { font-weight: 700; }
        code { background: #eef1f4; padding: 1px 5px; border-radius: 4px; word-break: break-all; }
        dt { font-weight: 600; margin-top: 10px; } dd { margin: 2px 0 0; }
        fieldset { border: 1px solid #e4e7eb; border-radius: 6px; margin: 12px 0; } legend { font-weight: 600; padding: 0 6px; }
    </style>
</head>
<body>
@auth('web')
    <header>
        <div class="inner">
            <strong>{{ $tenant->name }}</strong>
            <nav aria-label="Menu">
                @foreach ($menu as $item)
                    <a href="{{ $item['url'] }}" data-module="{{ $item['module'] }}" @class(['ro' => $item['readonly']])>{{ $item['label'] }}@if ($item['readonly']) (baca saja)@endif</a>
                @endforeach
                @if (! $tenant->setup_completed_at)
                    <a href="{{ route('core.setup.index', absolute: false) }}">Setup awal</a>
                @endif
            </nav>
            <form method="POST" action="{{ route('core.logout', absolute: false) }}">
                @csrf
                {{ auth('web')->user()->name }} · <button type="submit">Keluar</button>
            </form>
        </div>
    </header>
@endauth
<main>
    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
