@extends('core::layouts.app')

@section('title', 'Pengaturan '.$module->name)

@section('content')
    <h1>Pengaturan</h1>
    <p>
        @foreach ($modules as $code => $name)
            <a href="{{ route('core.settings.edit', ['module' => $code, 'company_id' => $companyId], false) }}" @if ($code === $module->code) style="font-weight:700" @endif>{{ $name }}</a>@if (! $loop->last) · @endif
        @endforeach
    </p>
    <div class="card">
        <h2>{{ $module->name }}</h2>
        <form method="GET" action="{{ route('core.settings.edit', ['module' => $module->code], false) }}" class="actions" style="margin-top:0">
            <label for="company_id" style="margin:0">Company</label>
            <select id="company_id" name="company_id" style="width:auto" onchange="this.form.submit()">
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected($c->id === $companyId)>{{ $c->code }} · {{ $c->name }}</option>@endforeach
            </select>
        </form>
        <form method="POST" action="{{ route('core.settings.update', ['module' => $module->code], false) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="company_id" value="{{ $companyId }}">
            @foreach ($definitions as $key => $def)
                @php($value = old($key, $values[$key]))
                @if ($def['type'] === 'bool')
                    <label class="inline" style="display:flex"><input type="checkbox" name="{{ $key }}" value="1" @checked($value)> {{ $def['label'] ?? $key }}</label>
                @else
                    <label for="{{ $key }}">{{ $def['label'] ?? $key }}</label>
                    @if ($def['type'] === 'select')
                        <select id="{{ $key }}" name="{{ $key }}">@foreach ($def['options'] as $v => $l)<option value="{{ $v }}" @selected((string) $value === (string) $v)>{{ $l }}</option>@endforeach</select>
                    @else
                        <input id="{{ $key }}" name="{{ $key }}" value="{{ $value }}" @if (in_array($def['type'], ['int', 'decimal'], true)) type="number" step="{{ $def['type'] === 'int' ? 1 : 'any' }}" @endif>
                    @endif
                @endif
                @if (! empty($def['help']))<p class="hint">{{ $def['help'] }}</p>@endif
            @endforeach
            <div class="actions">
                @if ($readonly)
                    <span class="hint">Modul dalam mode baca saja.</span>
                @else
                    <button class="btn" type="submit">Simpan</button>
                @endif
            </div>
        </form>
    </div>
@endsection
