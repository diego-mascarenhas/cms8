<div class="d-flex justify-content-center align-items-center">
    @can('view', $teamFile)
        <a href="{{ route('team-file.show', $teamFile) }}" class="text-body" title="{{ __('View') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
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
</div>
