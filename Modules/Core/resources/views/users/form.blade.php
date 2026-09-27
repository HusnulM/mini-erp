@extends('core::layouts.app')

@section('title', $user->exists ? 'Ubah user' : 'Undang user')

@section('content')
    <div class="card">
        <h1>{{ $user->exists ? "Ubah user {$user->username}" : 'Undang user' }}</h1>
        <form method="POST" action="{{ $user->exists ? route('core.users.update', $user, false) : route('core.users.store', absolute: false) }}">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="grid2">
                <div><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
                @unless ($user->exists)
                    <div><label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" required></div>
                    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
                @endunless
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        @foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', $user->roles->first()?->name) === $r)>{{ $r }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="default_company_id">Company default</label>
                    <select id="default_company_id" name="default_company_id">
                        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((int) old('default_company_id', $user->default_company_id) === $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach
                    </select>
                </div>
                @if ($user->exists)
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'locked' => 'Terkunci'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $user->status) === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </div>

            <fieldset>
                <legend>Cakupan data</legend>
                <p class="hint">User hanya melihat data unit yang dipilih. SUPER ADMIN selalu melihat semuanya.</p>
                @php($selected = old('scopes', $selectedScopes))
                @foreach (['company' => $companies, 'branch' => $branches, 'store' => $stores, 'warehouse' => $warehouses] as $type => $items)
                    @if ($items->isNotEmpty())
                        <div><strong>{{ ucfirst($type) }}:</strong>
                            @foreach ($items as $item)
                                <label class="inline"><input type="checkbox" name="scopes[]" value="{{ $type }}:{{ $item->id }}" @checked(in_array("{$type}:{$item->id}", $selected, true))> {{ $item->code }}</label>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                <label class="inline"><input type="checkbox" name="scopes[]" value="own" @checked(in_array('own', $selected, true))> Data buatan sendiri</label>
            </fieldset>

            <div class="actions">
                <button class="btn" type="submit">{{ $user->exists ? 'Simpan' : 'Kirim undangan' }}</button>
                <a href="{{ route('core.users.index', absolute: false) }}">Batal</a>
            </div>
        </form>
    </div>
@endsection
