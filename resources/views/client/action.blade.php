<div class="d-flex justify-content-center align-items-center">
    @if (!empty($deleted_at))
        <a href="{{ route('client.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
        <form method="POST" action="{{ route('client.restore', $id) }}" class="d-inline" onsubmit="return confirm('La empresa vuelve al listado. Los proyectos y las facturas siguen en la empresa con la que se fusionó.');">
            @csrf
            <button type="submit" class="btn btn-link text-body p-0" title="Restaurar">
                <i class="ti ti-arrow-back-up ti-sm"></i>
            </button>
        </form>
    @else
    {{-- View client details --}}
    @role('admin|collaborator|client')
        <a href="{{ route('client.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Edit client --}}
    @role('admin|collaborator')
        <a href="{{ route('client.edit', $id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endrole
    @endif
</div>