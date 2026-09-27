@extends('core::layouts.app')

@section('title', 'Konfigurasi Procurement')

@section('content')
    <h1>Konfigurasi Procurement</h1>
    @if ($wizard)
        <div class="card">
            <p>Langkah opsional setup awal. Default sudah dibuat (purchase type <strong>REG Regular</strong>).</p>
            <form method="POST" action="{{ route('core.setup.done', ['step' => 'module:procurement'], false) }}" style="display:inline">@csrf <button class="btn" type="submit">Selesai</button></form>
            <a href="{{ route('core.setup.index', absolute: false) }}">Kembali ke setup</a>
        </div>
    @endif

    @if ($companies->count() > 1)
        <form method="GET" class="actions" style="margin-top:0">
            <label for="company_id" style="margin:0">Company</label>
            <select id="company_id" name="company_id" style="width:auto" onchange="this.form.submit()">
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected($c->id === $company?->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach
            </select>
        </form>
    @endif

    @if (! $company)
        <div class="card"><p>Buat company terlebih dahulu.</p></div>
    @else
        <div class="grid2">
            <div class="card table-wrap">
                <h2>Purchase type</h2>
                <table>
                    <tr><th>Kode</th><th>Nama</th><th>PR</th><th>GR</th><th>Aktif</th><th></th></tr>
                    @foreach ($types as $t)
                        <tr><td><code>{{ $t->code }}</code></td><td>{{ $t->name }}</td><td>{{ $t->requires_pr ? 'wajib' : '—' }}</td><td>{{ $t->requires_gr ? 'wajib' : '—' }}</td><td>{{ $t->is_active ? 'ya' : 'tidak' }}</td>
                            <td><a href="{{ route('procurement.types.edit', $t, false) }}">Ubah</a></td></tr>
                    @endforeach
                </table>
                <form method="POST" action="{{ route('procurement.types.store', absolute: false) }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <div class="grid2">
                        <div><label for="t_code">Kode</label><input id="t_code" name="code" placeholder="SRV"></div>
                        <div><label for="t_name">Nama</label><input id="t_name" name="name" placeholder="Service"></div>
                    </div>
                    <label class="inline"><input type="checkbox" name="requires_pr" value="1" checked> Wajib PR</label>
                    <label class="inline"><input type="checkbox" name="requires_gr" value="1" checked> Wajib penerimaan barang</label>
                    <input type="hidden" name="is_active" value="1">
                    <div class="actions"><button class="btn btn-sm" type="submit">Tambah purchase type</button></div>
                </form>
            </div>

            <div class="card table-wrap">
                <h2>Purchasing group</h2>
                <table>
                    <tr><th>Kode</th><th>Nama</th><th>Lead</th><th>Maks. PO</th><th></th></tr>
                    @foreach ($groups as $g)
                        <tr><td><code>{{ $g->code }}</code></td><td>{{ $g->name }}</td><td>{{ $g->lead?->name }}</td><td>{{ $g->max_po_amount !== null ? number_format($g->max_po_amount, 0, ',', '.') : '—' }}</td>
                            <td><a href="{{ route('procurement.groups.edit', $g, false) }}">Ubah</a></td></tr>
                    @endforeach
                </table>
                <form method="POST" action="{{ route('procurement.groups.store', absolute: false) }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <div class="grid2">
                        <div><label for="g_code">Kode</label><input id="g_code" name="code" placeholder="PG01"></div>
                        <div><label for="g_name">Nama</label><input id="g_name" name="name" placeholder="Pembelian Makanan"></div>
                    </div>
                    <label for="g_max">Maks. nilai PO <span class="hint">(opsional)</span></label><input id="g_max" type="number" step="any" name="max_po_amount">
                    <input type="hidden" name="is_active" value="1">
                    <div class="actions"><button class="btn btn-sm" type="submit">Tambah purchasing group</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Pengaturan {{ $company->code }}</h2>
            <table>
                @foreach ($definitions as $key => $def)
                    <tr><td>{{ $def['label'] }}</td><td>{{ is_bool($settings[$key]) ? ($settings[$key] ? 'ya' : 'tidak') : $settings[$key] }}</td></tr>
                @endforeach
            </table>
            <p><a href="{{ route('core.settings.edit', ['module' => 'procurement', 'company_id' => $company->id], false) }}">Ubah pengaturan</a></p>
        </div>
    @endif
@endsection
