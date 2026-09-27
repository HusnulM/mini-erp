@extends('core::layouts.app')

@section('content')
    @php($company = auth('web')->user()->defaultCompany ?? \Modules\Core\Models\Company::orderBy('id')->first())
    <div class="card">
        <h1>{{ $company?->name ?? $tenant->name }}</h1>
        <p>Selamat datang, {{ auth('web')->user()->name }}. Modul bisnis dibangun mulai Phase 1.</p>
        <dl>
            <dt>Tenant</dt><dd>{{ $tenant->name }} <code>{{ $tenant->id }}</code></dd>
            <dt>Status</dt><dd>{{ $tenant->status->value }}</dd>
            <dt>Database</dt><dd><code>{{ \Illuminate\Support\Facades\DB::connection()->getDatabaseName() }}</code></dd>
        </dl>
    </div>
@endsection
