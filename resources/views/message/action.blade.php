<div class="d-flex justify-content-center align-items-center gap-1 flex-nowrap">
	<a href="{{ route('message.show', $id) }}" class="text-body" title="{{ __('View') }}"><i class="ti ti-eye ti-sm"></i></a>
	<a href="{{ route('message.edit', $id) }}" class="text-body" title="{{ __('Edit') }}"><i class="ti ti-edit ti-sm"></i></a>
</div>