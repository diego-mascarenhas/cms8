<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('supplier.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div>
