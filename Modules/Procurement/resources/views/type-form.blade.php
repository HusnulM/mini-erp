@extends('core::layouts.app')

@section('title', 'Purchase type '.$type->code)

@section('content')
    <div class="card">
        <h1>Purchase type {{ $type->code }}</h1>
        <form method="POST" action="{{ route('procurement.types.update', $type, false) }}">
            @csrf
            @method('PUT')
            <div class="grid2">
                <div><label for="code">Kode</label><input id="code" name="code" value="{{ old('code', $type->code) }}"></div>
                <div><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $type->name) }}"></div>
                @foreach (['pr_sequence_id' => 'Penomoran PR', 'po_sequence_id' => 'Penomoran PO'] as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>
                        <select id="{{ $field }}" name="{{ $field }}">
                            <option value="">—</option>
                            @foreach ($sequences as $s)<option value="{{ $s->id }}" @selected((int) old($field, $type->{$field}) === $s->id)>{{ $s->document_type }} ({{ $s->format }})</option>@endforeach
                        </select>
                    </div>
                @endforeach
                <div><label for="account_mapping_key">Kunci mapping akun <span class="hint">(Finance)</span></label><input id="account_mapping_key" name="account_mapping_key" value="{{ old('account_mapping_key', $type->account_mapping_key) }}"></div>
            </div>
            <label class="inline"><input type="checkbox" name="requires_pr" value="1" @checked(old('requires_pr', $type->requires_pr))> Wajib PR</label>
            <label class="inline"><input type="checkbox" name="requires_gr" value="1" @checked(old('requires_gr', $type->requires_gr))> Wajib penerimaan barang</label>
            <label class="inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active))> Aktif</label>
            <div>
                <strong>Jenis produk:</strong>
                @foreach (['stock' => 'Stok', 'non_stock' => 'Non-stok'] as $v => $l)
                    <label class="inline"><input type="checkbox" name="allowed_product_types[]" value="{{ $v }}" @checked(in_array($v, old('allowed_product_types', $type->allowed_product_types ?? []), true))> {{ $l }}</label>
                @endforeach
            </div>
            <div class="actions"><button class="btn" type="submit">Simpan</button> <a href="{{ route('procurement.setup', ['company_id' => $type->company_id], false) }}">Batal</a></div>
        </form>
    </div>
@endsection
