{{-- Action column template for Invoice DataTables --}}
<div class="d-flex justify-content-center align-items-center">
    {{-- View invoice details - Only admin can view invoices --}}
    @role('admin')
        <a href="{{ route('invoice.show', $id) }}" class="text-body" title="{{ __('View details') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Edit invoice - Only admin can edit invoices --}}
    @role('admin')
        <a href="{{ route('invoice.edit', $id) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endrole
</div>

