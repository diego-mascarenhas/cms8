{{-- Action column template for Invoice DataTables --}}
<div class="d-flex justify-content-center align-items-center">
    @if ($enterprise)
        <a href="{{ route('client.show', $enterprise->id) }}" class="text-body me-2" title="{{ $enterprise->name }}">
            <i class="ti ti-building ti-sm"></i>
        </a>
    @elseif(auth()->user()?->hasAnyRole(['admin', 'collaborator']))
        <a href="{{ route('invoice.link-enterprise', $id) }}" class="text-body me-2" title="{{ __('invoice_enterprise.link.action_title') }}">
            <i class="ti ti-link ti-sm"></i>
        </a>
    @endif

    @role('admin')
        <a href="{{ route('invoice.show', $id) }}" class="text-body" title="{{ __('View details') }}">
            <i class="ti ti-eye ti-sm"></i>
        </a>
    @endrole
</div>
