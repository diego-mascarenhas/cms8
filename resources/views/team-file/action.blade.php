<div class="d-flex justify-content-center align-items-center">
    @can('view', $teamFile)
        @if($teamFile->getFirstMedia('file'))
            <a href="{{ route('team-file.download', $teamFile) }}" class="text-body" title="{{ __('Download') }}">
                <i class="ti ti-download ti-sm me-2"></i>
            </a>
        @endif
    @endcan
    @can('update', $teamFile)
        <a href="{{ route('team-file.edit', $teamFile) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endcan
    @can('delete', $teamFile)
        <form method="POST" action="{{ route('team-file.destroy', $teamFile) }}" class="d-inline" onsubmit="return confirm(@json(__('Are you sure you want to delete this record?')));">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-link text-danger p-0 border-0" title="{{ __('Delete') }}">
                <i class="ti ti-trash ti-sm"></i>
            </button>
        </form>
    @endcan
</div>
