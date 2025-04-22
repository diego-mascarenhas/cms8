@extends('layouts/layoutMaster')

@section('title', 'Categories')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/nestable/nestable.css') }}">

<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>

<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/ui-toasts.js')}}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
</style>

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Categories</h5>
                <a href="{{ route('categories.create') }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i> New Category
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form id="filterForm" method="get">
                            <div class="form-group">
                                <label for="module_id" class="form-label">Filter by Module</label>
                                <select name="module_id" id="module_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">All Modules</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module->id }}" {{ $moduleId == $module->id ? 'selected' : '' }}>
                                            {{ $module->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="mb-3">Category Hierarchy</h6>
                        @if($categories->count() > 0)
                            <div class="dd" id="nestable">
                                <ol class="dd-list">
                                    @foreach($categories as $category)
                                        @include('category.partials.category-item', ['category' => $category])
                                    @endforeach
                                </ol>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="saveOrder" class="btn btn-primary btn-sm">Save Order</button>
                            </div>
                        @else
                            <div class="alert alert-info">
                                No categories found. <a href="{{ route('categories.create') }}">Create your first category</a>.
                            </div>
                        @endif
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="{{ route('categories.create') }}" class="btn btn-outline-primary">
                                        <i class="ti ti-plus me-1"></i> Add Top-Level Category
                                    </a>
                                    
                                    @if(isset($moduleId) && $moduleId)
                                        <a href="{{ route('categories.create', ['module_id' => $moduleId]) }}" class="btn btn-outline-primary">
                                            <i class="ti ti-plus me-1"></i> Add to Current Module
                                        </a>
                                    @endif
                                    
                                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'categories']) }}" class="btn btn-outline-secondary">
                                        <i class="ti ti-settings me-1"></i> Category Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/nestable/jquery.nestable.js') }}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize nestable
    $('#nestable').nestable({
        maxDepth: {{ $team->getSetting('categories_max_depth', 2) }}
    });
    
    // Save order
    $('#saveOrder').on('click', function() {
        const data = $('#nestable').nestable('serialize');
        const orderedCategories = flattenNestable(data);
        
        $.ajax({
            url: '{{ route("categories.order") }}',
            type: 'POST',
            data: {
                categories: orderedCategories,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                Swal.fire({
                    title: 'Success!',
                    text: response.success,
                    icon: 'success',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error!',
                    text: xhr.responseJSON?.error || 'Failed to update order',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
            }
        });
    });
    
    // Delete category
    $(document).on('click', '.delete-category', function(e) {
        e.preventDefault();
        
        const deleteUrl = $(this).data('url');
        const categoryName = $(this).data('name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete category "${categoryName}". This will also delete all subcategories.`,
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
                            location.reload();
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
    
    // Helper function to flatten nestable data
    function flattenNestable(items, order = 0) {
        let result = [];
        
        items.forEach((item, index) => {
            result.push({
                id: item.id,
                order: order + index
            });
            
            if (item.children && item.children.length > 0) {
                const children = flattenNestable(item.children, 0);
                result = result.concat(children);
            }
        });
        
        return result;
    }
});
</script>
@endsection

{{-- vendor scripts --}}
@section('vendor-script')
<script src="{{asset('vendors/data-tables/js/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
<script src="{{asset('vendors/fullcalendar/lib/moment.min.js')}}"></script>
<script src="{{asset('js/moment/' . app()->getLocale() . '.js')}}"></script>
@endsection