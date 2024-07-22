<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('host.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    @if (auth()->user()->can('host.edit'))
        <a href="{{ route('host.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
</div>
