@extends('core::layouts.app')

@section('title', 'Setup: User & role')

@section('content')
    <div class="layout">
        @include('core::setup._steps')
        <div class="card">
            <h1>7. User &amp; role</h1>
            <p>Undang user lewat email, pilih role dan cakupan datanya. Bisa juga dilakukan nanti dari menu Pengguna.</p>
            <p><a class="btn btn-light" href="{{ route('core.users.create', absolute: false) }}">Undang user</a> <a href="{{ route('core.roles.index', absolute: false) }}">Kelola role</a></p>
            <div class="actions">
                <form method="POST" action="{{ route('core.setup.done', ['step' => 'users'], false) }}">@csrf <button class="btn" type="submit">Selesai</button></form>
                @include('core::setup._skip', ['step' => 'users'])
            </div>
        </div>
    </div>
@endsection
