@extends('core::layouts.app')

@section('title', 'Setup: Penomoran dokumen')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card table-wrap">
            <h1>6. Penomoran dokumen</h1>
            <p class="hint">Token: <code>{PREFIX}</code> <code>{YYYY}</code> <code>{YY}</code> <code>{MM}</code> <code>{DD}</code> <code>{BRANCH}</code> <code>{SEQ:6}</code></p>
            @if ($sequences->isEmpty())
                <p>Belum ada dokumen dari modul aktif.</p>
            @endif
            <form method="POST" action="{{ route('core.setup.numbering.save', absolute: false) }}">
                @csrf
                @if ($sequences->isNotEmpty())
                    <table>
                        <tr><th>Modul</th><th>Dokumen</th><th>Prefix</th><th>Format</th><th>Reset</th></tr>
                        @foreach ($sequences as $s)
                            <tr>
                                <td>{{ $s->module }}</td>
                                <td>{{ $s->document_type }}</td>
                                <td><input name="sequences[{{ $s->id }}][prefix]" value="{{ old("sequences.{$s->id}.prefix", $s->prefix) }}"></td>
                                <td><input name="sequences[{{ $s->id }}][format]" value="{{ old("sequences.{$s->id}.format", $s->format) }}"></td>
                                <td>
                                    <select name="sequences[{{ $s->id }}][reset_period]">
                                        @foreach (['yearly' => 'Tahunan', 'monthly' => 'Bulanan', 'never' => 'Tidak pernah'] as $v => $l)
                                            <option value="{{ $v }}" @selected($s->reset_period === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                <div class="actions">
                    <button class="btn" type="submit">Simpan &amp; lanjut</button>
                </div>
            </form>
            @include('core::setup._skip', ['step' => 'numbering'])
        </div>
    </div>
@endsection
