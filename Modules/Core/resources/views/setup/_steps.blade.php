<div class="card">
    <strong>Setup awal</strong>
    <ol class="steps">
        @foreach ($steps as $s)
            <li @class(['current' => ($current ?? null) === $s['key']])>
                <a href="{{ route($s['route'], absolute: false) }}">{{ $s['label'] }}</a>
                @if (! $s['required'])<span class="hint">(opsional)</span>@endif
                <span class="badge b-{{ $s['status'] }}">{{ ['done' => 'selesai', 'skipped' => 'dilewati', 'pending' => 'belum'][$s['status']] }}</span>
            </li>
        @endforeach
    </ol>
    <p><a href="{{ route('core.setup.index', absolute: false) }}">Ringkasan</a></p>
</div>
