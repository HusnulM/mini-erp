@extends('central.layout')

@section('content')
    <div class="card">
        <h1>{{ config('app.name') }}</h1>
        <p>Central app (landing, registrasi, panel operator).</p>
        <p>
            <a class="btn" href="{{ central_route('register') }}">Daftar &amp; coba gratis</a>
            <a href="{{ central_route('admin.login') }}" style="margin-left: 12px">Panel operator</a>
        </p>
    </div>
@endsection
