@extends('core::layouts.app')

@section('content')
    <div class="card">
        <h1>{{ $tenant->name }}</h1>
        <p>Tenant context aktif. Halaman ini placeholder sampai setup wizard (Sprint 4).</p>
        <dl>
            <dt>Tenant ID</dt><dd><code>{{ $tenant->id }}</code></dd>
            <dt>Status</dt><dd>{{ $tenant->status->value }}</dd>
            <dt>Database</dt><dd><code>{{ \Illuminate\Support\Facades\DB::connection()->getDatabaseName() }}</code></dd>
        </dl>
    </div>
@endsection
