<div class="d-flex gap-2">
    <a href="{{ route('fare.show', $fare->id) }}" class="btn btn-sm btn-icon btn-primary">
        <i class="ti ti-eye"></i>
    </a>
    <a href="{{ route('fare.edit', $fare->id) }}" class="btn btn-sm btn-icon btn-info">
        <i class="ti ti-pencil"></i>
    </a>
    <button type="button" class="btn btn-sm btn-icon btn-danger delete-record" 
        data-id="{{ $fare->id }}" 
        data-route="{{ route('fare.destroy', $fare->id) }}">
        <i class="ti ti-trash"></i>
    </button>
</div>

@push('page-script')
<script>
    $(document).on('click', '.delete-record', function() {
        const id = $(this).data('id');
        const route = $(this).data('route');
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.value) {
                $.ajax({
                    url: route,
                    type: 'DELETE',
                    data: {
                        "_token": $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#fares-table').DataTable().ajax.reload();
                            
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: 'La tarifa ha sido eliminada.',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            });
                        }
                    },
                    error: function(error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error al eliminar la tarifa.',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    });
</script>
@endpush 