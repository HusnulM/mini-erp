@php($menu = auth('web')->check() ? app(\App\Support\Modules\MenuBuilder::class)->for(auth('web')->user()) : [])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenant->name) · Mini ERP</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; }
        header { background: #1f2933; color: #fff; }
        header .inner { max-width: 960px; margin: 0 auto; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center; }
        header a, header button { color: #fff; text-decoration: none; }
        header nav { display: flex; flex-wrap: wrap; gap: 4px 14px; flex: 1; }
        header nav .ro { opacity: .7; }
        header form { margin: 0; } header button { background: none; border: 0; font: inherit; cursor: pointer; text-decoration: underline; }
        main { max-width: 960px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 24px; }
        dt { font-weight: 600; margin-top: 12px; } dd { margin: 4px 0 0; }
        code { background: #eef1f4; padding: 2px 6px; border-radius: 4px; word-break: break-all; }
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
            </nav>
            <form method="POST" action="{{ route('core.logout', absolute: false) }}">
                @csrf
                {{ auth('web')->user()->name }} · <button type="submit">Keluar</button>
            </form>
        </div>
    </header>
@endauth
<main>
    @yield('content')
</main>
</body>
</html>
