<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|developer|editor|technical|client')
        <a href="{{ route('opportunity.show', $id) }}" class="text-body" title="{{ __('View') }}"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endrole
    @if (isset($contact) && auth()->user()->can('view', $contact))
        <a href="{{ route('contact.show', $contact->id) }}#activity" class="text-body" title="{{ __('Contact activity') }}"><i class="ti ti-history ti-sm me-2"></i></a>
    @endif
    @role('admin|collaborator|developer|technical')
        <a href="{{ route('opportunity.edit', $id) }}" class="text-body" title="{{ __('Edit') }}"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endrole
</div>
