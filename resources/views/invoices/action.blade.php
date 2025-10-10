{{-- Action column template for Invoice DataTables --}}
<div class="d-flex justify-content-center align-items-center">
    {{-- View invoice details --}}
    @if (auth()->user()->can('invoice.show'))
        <a href="{{ route('invoice.show', $id) }}" class="text-body" title="{{ __('View details') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endif

    {{-- Edit invoice --}}
    @if (auth()->user()->can('invoice.edit'))
        <a href="{{ route('invoice.edit', $id) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endif
</div>

