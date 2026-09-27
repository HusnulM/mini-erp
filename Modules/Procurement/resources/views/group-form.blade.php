@extends('core::layouts.app')

@section('title', 'Purchasing group '.$group->code)

@section('content')
    <div class="card">
        <h1>Purchasing group {{ $group->code }}</h1>
        <form method="POST" action="{{ route('procurement.groups.update', $group, false) }}">
            @csrf
            @method('PUT')
            <div class="grid2">
                <div><label for="code">Kode</label><input id="code" name="code" value="{{ old('code', $group->code) }}"></div>
                <div><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $group->name) }}"></div>
                <div>
                    <label for="lead_user_id">Lead</label>
                    <select id="lead_user_id" name="lead_user_id"><option value="">—</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected((int) old('lead_user_id', $group->lead_user_id) === $u->id)>{{ $u->name }}</option>@endforeach</select>
                </div>
                <div><label for="max_po_amount">Maks. nilai PO</label><input id="max_po_amount" type="number" step="any" name="max_po_amount" value="{{ old('max_po_amount', $group->max_po_amount) }}"></div>
            </div>
            <label class="inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active))> Aktif</label>
            <div class="actions"><button class="btn" type="submit">Simpan</button> <a href="{{ route('procurement.setup', ['company_id' => $group->company_id], false) }}">Batal</a></div>
        </form>
    </div>
@endsection
