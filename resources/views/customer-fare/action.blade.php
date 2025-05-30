<div class="dropdown">
    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
        <i class="ti ti-dots-vertical"></i>
    </button>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="{{ route('customer-fare.edit', $customerFare->id) }}">
            <i class="ti ti-pencil me-1"></i> Editar
        </a>
        <a class="dropdown-item" href="{{ route('customer-fare.show', $customerFare->id) }}">
            <i class="ti ti-eye me-1"></i> Ver
        </a>
        <form action="{{ route('customer-fare.destroy', $customerFare->id) }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="dropdown-item text-danger delete-record">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        </form>
    </div>
</div> 