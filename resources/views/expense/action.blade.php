{{-- Action column template for Expense DataTable --}}
<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|client')
        <a href="{{ route('payments.show', $id) }}" class="text-body" title="{{ __('View Payment') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole
</div>
