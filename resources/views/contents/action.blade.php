<div class="d-flex justify-content-center align-items-center">
    @can('view', $content)
        <a href="{{ route('contents.show', $content->id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endcan
    @can('update', $content)
        <a href="{{ route('contents.edit', $content->id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endcan
    @can('delete', $content)
        <a href="#" class="text-danger" onclick="deleteContent({{ $content->id }})">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    @endcan
</div>

<script>
function deleteContent(id) {
    Swal.fire({
        title: '{{ __("app.Are you sure?") }}',
        text: '{{ __("app.This action cannot be undone.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("app.Yes, delete it") }}',
        cancelButtonText: '{{ __("app.Cancel") }}',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("contents.destroy", ":id") }}'.replace(':id', id),
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    Swal.fire({
                        title: '{{ __("app.Deleted") }}',
                        text: response.success,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        window.LaravelDataTables['content-table'].draw();
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        title: '{{ __("app.Error") }}',
                        text: xhr.responseJSON?.error || '{{ __("app.Failed to delete content") }}',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
