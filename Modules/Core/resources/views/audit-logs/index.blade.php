@extends('core::layouts.app')

@section('title', 'Audit log')

@section('content')
    <h1>Audit log</h1>
    <form method="GET" class="actions" style="margin-top:0">
        <input name="type" value="{{ $filters['type'] ?? '' }}" placeholder="Jenis data" style="width:auto">
        <select name="event" style="width:auto">
            <option value="">Semua event</option>
            @foreach (['created', 'updated', 'deleted', 'restored', 'access_changed'] as $e)<option @selected(($filters['event'] ?? null) === $e)>{{ $e }}</option>@endforeach
        </select>
        <button class="btn btn-light" type="submit">Filter</button>
    </form>
    <div class="card table-wrap">
        <table>
            <tr><th>Waktu</th><th>User</th><th>Event</th><th>Data</th><th>Sebelum</th><th>Sesudah</th><th>IP</th></tr>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->username ?? 'sistem' }}</td>
                    <td>{{ $log->event }}</td>
                    <td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                    <td><code>{{ $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                    <td><code>{{ $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                    <td>{{ $log->ip }}</td>
                </tr>
            @endforeach
        </table>
        {{ $logs->links() }}
    </div>
@endsection
