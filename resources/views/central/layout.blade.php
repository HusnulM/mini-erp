<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #f6f7f9; color: #1f2933; line-height: 1.5; }
        main { max-width: @yield('width', '720px'); margin: 40px auto; padding: 0 16px; }
        header.bar { background: #1f2933; color: #fff; }
        header.bar .inner { max-width: 1100px; margin: 0 auto; padding: 12px 16px; display: flex; gap: 16px; align-items: center; justify-content: space-between; }
        header.bar a, header.bar button { color: #fff; }
        a { color: #1c64f2; }
        .card { background: #fff; border: 1px solid #e4e7eb; border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin: 14px 0 4px; }
        input, select { width: 100%; padding: 8px 10px; border: 1px solid #cbd2d9; border-radius: 6px; font: inherit; background: #fff; }
        input[type=radio], input[type=checkbox] { width: auto; }
        .hint { color: #616e7c; font-size: .875rem; margin: 2px 0 0; }
        .error { color: #c81e1e; font-size: .875rem; margin: 4px 0 0; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-ok { background: #def7ec; color: #03543f; }
        .alert-err { background: #fde8e8; color: #9b1c1c; }
        .btn { display: inline-block; background: #1c64f2; color: #fff; border: 0; border-radius: 6px; padding: 9px 16px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-sm { padding: 4px 10px; font-size: .8125rem; }
        .btn-link { background: none; border: 0; padding: 0; font: inherit; cursor: pointer; text-decoration: underline; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; } .row > * { flex: 1 1 200px; }
        .suffix { display: flex; align-items: center; } .suffix input { border-radius: 6px 0 0 6px; } .suffix span { padding: 8px 10px; background: #eef1f4; border: 1px solid #cbd2d9; border-left: 0; border-radius: 0 6px 6px 0; white-space: nowrap; }
        .plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .plan { border: 1px solid #cbd2d9; border-radius: 8px; padding: 12px; font-weight: normal; margin: 0; cursor: pointer; }
        .plan strong { display: block; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e4e7eb; vertical-align: top; }
        th { color: #52606d; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #e4e7eb; color: #323f4b; }
        .b-trial, .b-active, .b-done, .b-trialing { background: #def7ec; color: #03543f; }
        .b-pending, .b-provisioning, .b-running, .b-installing { background: #e1effe; color: #1e429f; }
        .b-failed, .b-suspended, .b-cancelled, .b-expired { background: #fde8e8; color: #9b1c1c; }
        .b-past_due { background: #fdf6b2; color: #723b13; }
        dl.grid { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0; } dl.grid dt { font-weight: 600; } dl.grid dd { margin: 0; }
        code { background: #eef1f4; padding: 1px 5px; border-radius: 4px; font-size: .85em; word-break: break-all; }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; } .filters > * { flex: 0 1 auto; width: auto; }
        nav.pager { margin-top: 12px; } nav.pager svg { width: 16px; }
    </style>
    @stack('head')
</head>
<body>
@yield('header')
<main>
    @yield('content')
</main>
</body>
</html>
