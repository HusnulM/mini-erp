@extends('core::layouts.app')

@section('title', $role->exists ? 'Ubah role' : 'Buat role')

@section('content')
    <div class="card">
        <h1>{{ $role->exists ? "Ubah role {$role->name}" : 'Buat role' }}</h1>
        <form method="POST" action="{{ $role->exists ? route('core.roles.update', $role, false) : route('core.roles.store', absolute: false) }}">
            @csrf
            @if ($role->exists) @method('PUT') @endif
            <label for="name">Nama role</label>
            <input id="name" name="name" value="{{ old('name', $role->name) }}" required>
            @php($checked = old('permissions', $selected))
            @foreach ($permissions as $module => $names)
                <fieldset>
                    <legend>{{ $module }}</legend>
                    @foreach ($names as $p)
                        <label class="inline"><input type="checkbox" name="permissions[]" value="{{ $p }}" @checked(in_array($p, $checked, true))> {{ substr($p, strlen($module) + 1) }}</label>
                    @endforeach
                </fieldset>
            @endforeach
            <div class="actions"><button class="btn" type="submit">Simpan</button> <a href="{{ route('core.roles.index', absolute: false) }}">Batal</a></div>
        </form>
    </div>
@endsection
