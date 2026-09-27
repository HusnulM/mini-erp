@extends('core::layouts.app')

@section('title', 'Setup: Pajak')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card">
            <h1>4. Pajak</h1>
            <form method="POST" action="{{ route('core.setup.tax.save', absolute: false) }}">
                @csrf
                <label>Status PKP {{ $company->name }}</label>
                <label class="inline"><input type="radio" name="is_pkp" value="1" @checked(old('is_pkp', $isPkp ? '1' : '0') === '1')> PKP (memungut PPN)</label>
                <label class="inline"><input type="radio" name="is_pkp" value="0" @checked(old('is_pkp', $isPkp ? '1' : '0') === '0')> Bukan PKP</label>
                <label for="vat_rate">Tarif PPN (%)</label>
                <input id="vat_rate" type="number" step="0.01" name="vat_rate" value="{{ old('vat_rate', $vatRate) }}">
                <p class="hint">PKP: kode pajak PPN Masukan dan PPN Keluaran dibuat dengan tarif ini.</p>
                <div class="actions"><button class="btn" type="submit">Simpan &amp; lanjut</button></div>
            </form>
            @if ($taxCodes->isNotEmpty())
                <table>
                    <tr><th>Kode</th><th>Nama</th><th>Tarif</th><th>Jenis</th><th>Berlaku</th></tr>
                    @foreach ($taxCodes as $t)
                        <tr><td>{{ $t->code }}</td><td>{{ $t->name }}</td><td>{{ (float) $t->rate }}%</td><td>{{ $t->type }}</td><td>{{ $t->effective_from->format('d/m/Y') }}</td></tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection
