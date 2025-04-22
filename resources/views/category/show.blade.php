@extends('layouts/layoutMaster')

@section('title', 'Category Details')

@section('content')
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Category Details</h5>
                <div class="btn-group">
                    <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-primary">
                        <i class="ti ti-edit me-1"></i> Edit
                    </a>
                    <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-list me-1"></i> All Categories
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h4>{{ $category->name }}</h4>
                        @if($category->description)
                            <p class="text-muted">{{ $category->description }}</p>
                        @endif
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex flex-column align-items-end">
                            <span class="badge bg-label-{{ $category->status ? 'success' : 'warning' }} mb-2">
                                {{ $category->status ? 'Active' : 'Inactive' }}
                            </span>
                            
                            @if($category->module)
                                <span class="badge bg-label-info mb-2">
                                    Module: {{ $category->module->name }}
                                </span>
                            @endif
                            
                            <small class="text-muted">Order: {{ $category->order }}</small>
                        </div>
                    </div>
                </div>
                
                @if($category->parent)
                    <div class="mb-4">
                        <h6>Parent Category</h6>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>{{ $category->parent->name }}</span>
                                    <a href="{{ route('categories.show', $category->parent->id) }}" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                @if($category->children && $category->children->count() > 0)
                    <div class="mb-4">
                        <h6>Subcategories ({{ $category->children->count() }})</h6>
                        <div class="list-group">
                            @foreach($category->children as $child)
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $child->name }}</strong>
                                        @if(!$child->status)
                                            <span class="badge bg-label-warning ms-1">Inactive</span>
                                        @endif
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('categories.show', $child->id) }}" class="btn btn-outline-primary">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ route('categories.edit', $child->id) }}" class="btn btn-outline-secondary">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                <div class="mt-4">
                    <h6>Category Information</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th style="width: 30%">ID</th>
                                    <td>{{ $category->id }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ $category->created_at->format('F j, Y g:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Last Updated</th>
                                    <td>{{ $category->updated_at->format('F j, Y g:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Full Path</th>
                                    <td>{{ $category->full_path }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4 d-flex justify-content-between">
                    <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back to Categories
                    </a>
                    <div>
                        @if($category->children->count() === 0)
                            <a href="#" class="btn btn-outline-danger delete-category" 
                                data-url="{{ route('categories.destroy', $category->id) }}"
                                data-name="{{ $category->name }}">
                                <i class="ti ti-trash me-1"></i> Delete
                            </a>
                        @endif
                        <a href="{{ route('categories.create', ['parent_id' => $category->id]) }}" class="btn btn-success ms-2">
                            <i class="ti ti-plus me-1"></i> Add Subcategory
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete category
    $(document).on('click', '.delete-category', function(e) {
        e.preventDefault();
        
        const deleteUrl = $(this).data('url');
        const categoryName = $(this).data('name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete category "${categoryName}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: deleteUrl,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: response.success,
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        }).then(() => {
                            window.location.href = '{{ route("categories.index") }}';
                        });
                    },
                    error: function(xhr) {
                        Swal.fire({
                            title: 'Error!',
                            text: xhr.responseJSON?.error || 'Failed to delete category',
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
    });
});
</script>
@endsection 