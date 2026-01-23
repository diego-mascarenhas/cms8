<div class="d-flex justify-content-center align-items-center">
    @can('view', $multimedia)
        @if($multimedia->getFirstMediaUrl('media'))
            <a href="{{ $multimedia->getFirstMediaUrl('media') }}" target="_blank" class="text-body">
                <i class="ti ti-eye ti-sm me-2"></i>
            </a>
        @endif
    @endcan
    @can('update', $multimedia)
        <a href="{{ route('multimedia.edit', $multimedia->id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endcan
    @can('delete', $multimedia)
        <a href="#" class="text-danger" onclick="deleteRecord({{ $multimedia->id }})">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    @endcan
</div>
