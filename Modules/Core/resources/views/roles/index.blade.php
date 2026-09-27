@extends('core::layouts.app')

@section('title', 'Role')

@section('content')
    <h1>Role</h1>
    @can('core.role.create')<p><a class="btn" href="{{ route('core.roles.create', absolute: false) }}">Buat role</a></p>@endcan
    <div class="card table-wrap">
        <table>
            <tr><th>Role</th><th>Permission</th><th>User</th><th></th></tr>
            @foreach ($roles as $r)
                <tr>
                    <td>{{ $r->name }}</td><td>{{ $r->permissions_count }}</td><td>{{ $r->users_count }}</td>
                    <td>@if ($r->name !== config('erp.provisioning.admin_role'))@can('core.role.update')<a href="{{ route('core.roles.edit', $r, false) }}">Ubah</a>@endcan @else <span class="hint">dikelola sistem</span>@endif</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
