@extends('central.admin.layout')

@section('title', 'Tenant · Operator')

@section('content')
    <h1>Tenant</h1>

    <div class="card">
        <p>
            @foreach (\App\Central\Enums\TenantStatus::cases() as $s)
                <a href="{{ central_route('admin.tenants.index', ['status' => $s->value]) }}" class="badge b-{{ $s->value }}">{{ $s->value }}: {{ $counts[$s->value] ?? 0 }}</a>
            @endforeach
        </p>
        <form method="GET" action="{{ central_route('admin.tenants.index') }}" class="filters">
            <div>
                <label for="q">Cari</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nama, subdomain, email">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Semua</option>
                    @foreach (\App\Central\Enums\TenantStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(($filters['status'] ?? null) === $s->value)>{{ $s->value }}</option>
                    @endforeach
                </select>
            </div>
            <div><button class="btn" type="submit">Filter</button></div>
        </form>
    </div>

    <div class="card table-wrap">
        <table>
            <thead>
            <tr><th>Tenant</th><th>Domain</th><th>Pemilik</th><th>Paket</th><th>Status</th><th>Provisioning</th><th>Terdaftar</th></tr>
            </thead>
            <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td><a href="{{ central_route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a><br><code>{{ $tenant->code }}</code></td>
                    <td>{{ $tenant->primaryDomain() }}</td>
                    <td>{{ $tenant->owner_name }}<br><span class="hint">{{ $tenant->owner_email }}</span></td>
                    <td>{{ $tenant->currentSubscription?->plan->name ?? '—' }}</td>
                    <td><span class="badge b-{{ $tenant->status->value }}">{{ $tenant->status->value }}</span></td>
                    <td>
                        @if ($run = $tenant->latestProvisioningRun)
                            <span class="badge b-{{ $run->status->value }}">{{ $run->status->value }}</span>
                        @else
                            <span class="hint">{{ $tenant->email_verified_at ? '—' : 'menunggu verifikasi email' }}</span>
                        @endif
                    </td>
                    <td>{{ $tenant->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Tidak ada tenant.</td></tr>
            @endforelse
            </tbody>
        </table>
        <nav class="pager">{{ $tenants->links() }}</nav>
    </div>
@endsection
