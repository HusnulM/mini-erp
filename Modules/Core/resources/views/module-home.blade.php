@extends('core::layouts.app', ['tenant' => tenant()])

@php($manifest = app(\App\Support\Modules\ModuleRegistry::class)->get($module))

@section('title', $manifest->name)

@section('content')
    <div class="card">
        <h1>{{ $manifest->name }}</h1>
        <p>{{ $manifest->description }}</p>
        <p>Modul aktif. Fitur modul ini dibangun mulai Phase 1.</p>
    </div>
@endsection
