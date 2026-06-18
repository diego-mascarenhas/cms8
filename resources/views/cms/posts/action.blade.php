<div class="d-flex justify-content-center align-items-center">
    @can('view', $post)
        <a href="{{ route('cms.posts.show', $post->id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endcan
    @can('update', $post)
        <a href="{{ route('cms.posts.edit', $post->id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endcan
    @can('delete', $post)
        <a href="#" class="text-danger" onclick="deletePost({{ $post->id }})">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    @endcan
</div>

<script>
function deletePost(id) {
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
                url: '{{ route("cms.posts.destroy", ":id") }}'.replace(':id', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function() {
                    window.LaravelDataTables['posts-table'].draw();
                },
                error: function(xhr) {
                    Swal.fire({
                        title: '{{ __("app.Error") }}',
                        text: xhr.responseJSON?.message || '{{ __("app.Failed to delete") }}',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
}
</script>
