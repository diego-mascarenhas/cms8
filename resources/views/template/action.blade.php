<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('template.show', $hashedId) }}" class="text-body" target="_blank" title="{{ __('View') }}"><i class="ti ti-eye ti-sm me-2"></i></a>
    <a href="{{ route('template.editor', $hashedId) }}" class="text-body" target="_blank" title="{{ __('Editor') }}"><i class="ti ti-code ti-sm me-2"></i></a>
    <a href="{{ route('template.edit', $hashedId) }}" class="text-body" title="{{ __('Edit') }}"><i class="ti ti-edit ti-sm me-2"></i></a>
</div>
