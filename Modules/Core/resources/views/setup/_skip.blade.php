<form method="POST" action="{{ route('core.setup.skip', ['step' => $step], false) }}" style="display:inline">
    @csrf
    <button class="btn btn-light" type="submit">Lewati</button>
</form>
