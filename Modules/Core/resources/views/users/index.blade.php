@extends('core::layouts.app')

@section('title', 'Pengguna')

@section('content')
    <h1>Pengguna</h1>
    @can('core.user.create')<p><a class="btn" href="{{ route('core.users.create', absolute: false) }}">Undang user</a></p>@endcan
    <div class="card table-wrap">
        <table>
            <tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Cakupan data</th><th>Status</th><th></th></tr>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $u->name }}</td><td>{{ $u->username }}</td><td>{{ $u->email }}</td>
                    <td>{{ $u->roles->pluck('name')->join(', ') }}</td>
                    <td>{{ $u->scopes->map(fn ($s) => $s->scope_type.($s->scope_id ? ' #'.$s->scope_id : ''))->join(', ') ?: '—' }}</td>
                    <td><span class="badge b-{{ $u->status }}">{{ $u->status }}</span></td>
                    <td>@can('core.user.update')<a href="{{ route('core.users.edit', $u, false) }}">Ubah</a>@endcan</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
