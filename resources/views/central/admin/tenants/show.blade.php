@extends('central.admin.layout')

@section('title', $tenant->name.' · Operator')

@section('content')
    <p><a href="{{ central_route('admin.tenants.index') }}">&larr; Semua tenant</a></p>
    <h1>{{ $tenant->name }} <span class="badge b-{{ $tenant->status->value }}">{{ $tenant->status->value }}</span></h1>

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @error('retry')
        <div class="alert alert-err" role="alert">{{ $message }}</div>
    @enderror

    <div class="row">
        <div class="card">
            <h2>Tenant</h2>
            <dl class="grid">
                <dt>ID</dt><dd><code>{{ $tenant->id }}</code></dd>
                <dt>Kode</dt><dd>{{ $tenant->code }}</dd>
                <dt>Domain</dt><dd>{{ $tenant->domains->pluck('domain')->join(', ') }}</dd>
                <dt>Pemilik</dt><dd>{{ $tenant->owner_name }} &lt;{{ $tenant->owner_email }}&gt;</dd>
                <dt>No HP</dt><dd>{{ $tenant->phone ?? '—' }}</dd>
                <dt>Email terverifikasi</dt><dd>{{ $tenant->email_verified_at?->format('Y-m-d H:i') ?? 'belum' }}</dd>
                <dt>Database</dt><dd>@if ($tenant->db_name)<code>{{ $tenant->db_name }}</code> / <code>{{ $tenant->db_username }}</code>@else — @endif</dd>
                <dt>Terdaftar</dt><dd>{{ $tenant->created_at->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
        <div class="card">
            <h2>Subscription</h2>
            @if ($sub = $tenant->currentSubscription)
                <dl class="grid">
                    <dt>Paket</dt><dd>{{ $sub->plan->name }} ({{ $sub->plan->code }})</dd>
                    <dt>Status</dt><dd><span class="badge b-{{ $sub->status->value }}">{{ $sub->status->value }}</span></dd>
                    <dt>Siklus</dt><dd>{{ $sub->billing_cycle->value }}</dd>
                    <dt>Periode</dt><dd>{{ $sub->current_period_start?->format('Y-m-d') }} s/d {{ $sub->current_period_end?->format('Y-m-d') }}</dd>
                </dl>
            @else
                <p class="hint">Belum ada (dibuat saat email diverifikasi).</p>
            @endif

            <h2>Modul</h2>
            @forelse ($tenant->tenantModules->sortBy('module.sort') as $tm)
                <span class="badge b-{{ $tm->status->value }}" title="{{ $tm->source->value }}{{ $tm->last_error ? ' · '.$tm->last_error : '' }}">{{ $tm->module->code }}: {{ $tm->status->value }}</span>
            @empty
                <p class="hint">Belum ada.</p>
            @endforelse
        </div>
    </div>

    <h2>Provisioning</h2>
    @forelse ($tenant->provisioningRuns as $run)
        @php($firstUnfinished = $run->steps->first(fn ($s) => ! in_array($s->status->value, ['done', 'skipped'], true))?->seq)
        <div class="card table-wrap">
            <p>
                Run #{{ $run->id }} ({{ $run->type->value }}) <span class="badge b-{{ $run->status->value }}">{{ $run->status->value }}</span>
                <span class="hint">mulai {{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }} · selesai {{ $run->finished_at?->format('Y-m-d H:i:s') ?? '—' }}</span>
            </p>
            @if ($run->error)
                <p class="error">{{ $run->error }}</p>
            @endif
            <table>
                <thead><tr><th>#</th><th>Langkah</th><th>Status</th><th>Percobaan</th><th>Pesan</th><th>Selesai</th><th></th></tr></thead>
                <tbody>
                @foreach ($run->steps as $step)
                    <tr>
                        <td>{{ $step->seq }}</td>
                        <td><code>{{ $step->step }}</code></td>
                        <td><span class="badge b-{{ $step->status->value }}">{{ $step->status->value }}</span></td>
                        <td>{{ $step->attempts }}/{{ config('erp.provisioning.max_attempts') }}</td>
                        <td>{{ $step->message }}</td>
                        <td>{{ $step->finished_at?->format('H:i:s') }}</td>
                        <td>
                            @if ($run->status->value === 'failed' && $firstUnfinished !== null && $step->seq <= $firstUnfinished)
                                @can('retry-provisioning')
                                    <form method="POST" action="{{ central_route('admin.provisioning.retry', [$tenant, $run, $step]) }}">
                                        @csrf
                                        <button class="btn btn-sm" type="submit">Retry from step</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="card"><p class="hint">Belum ada run (menunggu verifikasi email).</p></div>
    @endforelse
@endsection
