@extends('central.layout')

@section('width', '1100px')

@section('header')
    <header class="bar">
        <div class="inner">
            <a href="{{ central_route('admin.tenants.index') }}"><strong>{{ config('app.name') }} · Operator</strong></a>
            @auth('central')
                <form method="POST" action="{{ central_route('admin.logout') }}">
                    @csrf
                    {{ auth('central')->user()->name }} ({{ auth('central')->user()->role->value }}) ·
                    <button class="btn-link" type="submit">Keluar</button>
                </form>
            @endauth
        </div>
    </header>
@endsection
