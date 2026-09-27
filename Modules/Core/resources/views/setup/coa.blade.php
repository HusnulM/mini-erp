@extends('core::layouts.app')

@section('title', 'Setup: Chart of Accounts')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card">
            <h1>5. Chart of Accounts</h1>
            <p>Pilih template akun. Akun dibuat oleh modul Finance dari template ini.</p>
            <form method="POST" action="{{ route('core.setup.coa.save', absolute: false) }}">
                @csrf
                @foreach ($templates as $key => $label)
                    <label class="inline"><input type="radio" name="template" value="{{ $key }}" @checked(old('template') === $key)> {{ $label }}</label>
                @endforeach
                <div class="actions"><button class="btn" type="submit">Simpan &amp; lanjut</button></div>
            </form>
        </div>
    </div>
@endsection
