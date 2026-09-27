@extends('core::layouts.app')

@section('title', 'Setup: Tahun fiskal')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card">
            <h1>2. Tahun fiskal</h1>
            <p>{{ $company->name }}: 12 periode bulanan dibuat otomatis.</p>
            <form method="POST" action="{{ route('core.setup.fiscal-year.save', absolute: false) }}">
                @csrf
                <div class="grid2">
                    <div>
                        <label for="start_month">Bulan awal tahun fiskal</label>
                        <select id="start_month" name="start_month">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected((int) old('start_month', $company->fiscal_year_start_month) === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="year">Tahun fiskal pertama (tahun mulai)</label>
                        <input id="year" type="number" name="year" value="{{ old('year', now()->year) }}" min="2000" max="2100">
                    </div>
                </div>
                <div class="actions"><button class="btn" type="submit">Buat &amp; lanjut</button></div>
            </form>
            @foreach ($company->fiscalYears as $fy)
                <p><strong>{{ $fy->year }}</strong>: {{ $fy->start_date->format('d/m/Y') }} – {{ $fy->end_date->format('d/m/Y') }}, {{ $fy->periods->count() }} periode.</p>
            @endforeach
        </div>
    </div>
@endsection
