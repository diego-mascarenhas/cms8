<div class="d-flex justify-content-center align-items-center">
    @can('view', $campaign)
        <a href="{{ route('paid-ads.show', $campaign->id) }}" class="text-body" title="{{ __('View') }}"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endcan
    @can('update', $campaign)
        <a href="{{ route('paid-ads.edit', $campaign->id) }}" class="text-body" title="{{ __('Edit') }}"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endcan
    @can('delete', $campaign)
        <a href="#" class="text-danger" onclick="deleteRecord({{ $campaign->id }}, this)" title="{{ __('Delete') }}"><i class="ti ti-trash ti-sm"></i></a>
    @endcan
</div>
