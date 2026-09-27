@extends('core::layouts.app')

@section('title', 'Organisasi')

@section('content')
    @php($w = $wizard ? ['wizard' => 1] : [])
    @if ($wizard)
        <div class="card">
            <h1>3. Struktur organisasi</h1>
            <p>Tambahkan branch, store, dan gudang. Minimal 1 store atau 1 gudang. Setiap store otomatis mendapat gudang store.</p>
            <form method="POST" action="{{ route('core.setup.organization.save', absolute: false) }}">
                @csrf
                <button class="btn" type="submit">Lanjut</button>
                <a href="{{ route('core.setup.index', absolute: false) }}">Kembali ke ringkasan</a>
            </form>
        </div>
    @else
        <h1>Organisasi</h1>
    @endif

    <div class="grid2">
        <div class="card table-wrap">
            <h2>Company</h2>
            <table>
                @foreach ($companies as $c)
                    <tr><td><code>{{ $c->code }}</code></td><td>{{ $c->name }}</td><td>{{ $c->base_currency }}</td></tr>
                @endforeach
            </table>
            @can('core.company.create')
                <form method="POST" action="{{ route('core.organization.companies.store', $w, false) }}">
                    @csrf
                    <div class="grid2">
                        <div><label for="c_code">Kode</label><input id="c_code" name="code" value="{{ old('code') }}"></div>
                        <div><label for="c_name">Nama</label><input id="c_name" name="name"></div>
                    </div>
                    <input type="hidden" name="base_currency" value="{{ $companies->first()?->base_currency ?? 'IDR' }}">
                    <div class="actions"><button class="btn btn-sm" type="submit">Tambah company</button></div>
                </form>
            @endcan
        </div>

        <div class="card table-wrap">
            <h2>Branch</h2>
            <table>
                @foreach ($branches as $b)
                    <tr><td><code>{{ $b->code }}</code></td><td>{{ $b->name }}</td><td>{{ $b->company->code }}</td></tr>
                @endforeach
            </table>
            @can('core.branch.create')
                <form method="POST" action="{{ route('core.organization.branches.store', $w, false) }}">
                    @csrf
                    <div class="grid2">
                        <div><label for="b_company">Company</label><select id="b_company" name="company_id">@foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
                        <div><label for="b_code">Kode</label><input id="b_code" name="code"></div>
                    </div>
                    <label for="b_name">Nama</label><input id="b_name" name="name">
                    <div class="actions"><button class="btn btn-sm" type="submit">Tambah branch</button></div>
                </form>
            @endcan
        </div>

        <div class="card table-wrap">
            <h2>Store</h2>
            <table>
                @foreach ($stores as $s)
                    <tr><td><code>{{ $s->code }}</code></td><td>{{ $s->name }}</td><td>{{ $s->branch->code }}</td><td class="hint">gudang {{ $s->defaultWarehouse?->code }}</td></tr>
                @endforeach
            </table>
            @can('core.store.create')
                @if ($branches->isEmpty())
                    <p class="hint">Tambahkan branch dulu.</p>
                @else
                    <form method="POST" action="{{ route('core.organization.stores.store', $w, false) }}">
                        @csrf
                        <div class="grid2">
                            <div><label for="s_branch">Branch</label><select id="s_branch" name="branch_id">@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->company->code }} / {{ $b->code }}</option>@endforeach</select></div>
                            <div><label for="s_code">Kode</label><input id="s_code" name="code"></div>
                        </div>
                        <label for="s_name">Nama</label><input id="s_name" name="name">
                        <div class="actions"><button class="btn btn-sm" type="submit">Tambah store</button></div>
                    </form>
                @endif
            @endcan
        </div>

        <div class="card table-wrap">
            <h2>Gudang</h2>
            <table>
                @foreach ($warehouses as $wh)
                    <tr><td><code>{{ $wh->code }}</code></td><td>{{ $wh->name }}</td><td>{{ $wh->type }}</td><td>{{ $wh->branch?->code }}</td></tr>
                @endforeach
            </table>
            @can('core.warehouse.create')
                <form method="POST" action="{{ route('core.organization.warehouses.store', $w, false) }}">
                    @csrf
                    <div class="grid2">
                        <div><label for="w_company">Company</label><select id="w_company" name="company_id">@foreach ($companies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
                        <div><label for="w_branch">Branch <span class="hint">(opsional)</span></label><select id="w_branch" name="branch_id"><option value="">—</option>@foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach</select></div>
                        <div><label for="w_code">Kode</label><input id="w_code" name="code"></div>
                        <div><label for="w_type">Tipe</label><select id="w_type" name="type"><option value="main">Utama</option><option value="store">Store</option><option value="transit">Transit</option></select></div>
                    </div>
                    <label for="w_name">Nama</label><input id="w_name" name="name">
                    <div class="actions"><button class="btn btn-sm" type="submit">Tambah gudang</button></div>
                </form>
            @endcan
        </div>
    </div>
@endsection
