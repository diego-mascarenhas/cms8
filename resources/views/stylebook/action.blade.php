<div class="d-flex justify-content-center align-items-center">
    @can('view', $stylebook)
        <a href="{{ route('stylebook.show', $stylebook->id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endcan
    @can('update', $stylebook)
        <a href="{{ route('stylebook.edit', $stylebook->id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endcan
</div> 