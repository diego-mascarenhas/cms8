@extends('layouts/layoutMaster')

@section('title', __('app.Categories'))

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
                <h5 class="mb-0">{{ __('app.Categories') }}</h5>
                <a href="{{ route('categories.create', array_filter(['module_id' => $moduleId ?? null])) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i> {{ __('app.New Category') }}
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form id="filterForm" method="get">
                            <div class="form-group">
                                <label for="categories_filter_module_id" class="form-label">{{ __('app.Filter by module') }}</label>
                                <select name="module_id" id="categories_filter_module_id" class="form-select">
                                    <option value="" disabled {{ empty($moduleId) ? 'selected' : '' }}>{{ __('app.Select module to list categories') }}</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module->id }}" {{ (int) ($moduleId ?? 0) === (int) $module->id ? 'selected' : '' }}>
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
                        <h6 class="mb-3">{{ __('app.Category hierarchy') }}</h6>
                        @if(empty($moduleId))
                            <div class="alert alert-info mb-0">
                                {{ __('app.Select module to list categories hint') }}
                            </div>
                        @elseif($categories->count() > 0)
                            <div class="dd" id="nestable">
                                <ol class="dd-list">
                                    @foreach($categories as $category)
                                        @include('category.partials.category-item', [
                                            'category' => $category,
                                            'showModuleBadge' => false,
                                            'indexModuleFilterId' => $moduleId,
                                        ])
                                    @endforeach
                                </ol>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="saveOrder" class="btn btn-primary btn-sm">{{ __('app.Save order') }}</button>
                            </div>
                        @else
                            <div class="alert alert-info">
                                {{ __('app.No categories in this module') }}
                                <a href="{{ route('categories.create', ['module_id' => $moduleId]) }}">{{ __('app.Create your first category') }}</a>.
                            </div>
                        @endif
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">{{ __('app.Quick Actions') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="{{ route('categories.create', array_filter(['module_id' => $moduleId ?? null])) }}" class="btn btn-outline-primary">
                                        <i class="ti ti-plus me-1"></i> {{ __('app.Add top-level category') }}
                                    </a>
                                    
                                    @if(isset($moduleId) && $moduleId)
                                        <a href="{{ route('categories.create', ['module_id' => $moduleId]) }}" class="btn btn-outline-primary">
                                            <i class="ti ti-plus me-1"></i> {{ __('app.Add to current module') }}
                                        </a>
                                    @endif
                                    
                                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'categories']) }}" class="btn btn-outline-secondary">
                                        <i class="ti ti-settings me-1"></i> {{ __('app.Category settings') }}
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
    var $filterModule = $('#categories_filter_module_id');
    if ($filterModule.length && typeof $.fn.select2 === 'function')
    {
        $filterModule.select2({
            placeholder: @json(__('app.Select module to list categories')),
            width: '100%',
            allowClear: false,
            dropdownParent: $filterModule.closest('.card-body'),
        });
        $filterModule.on('change', function ()
        {
            $(this).closest('form').trigger('submit');
        });
    }

    @if(! empty($moduleId) && $categories->count() > 0)
    // Initialize nestable (only when a module is selected and the tree exists)
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
                    title: @json(__('app.Success')),
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
                    title: @json(__('app.Error')),
                    text: xhr.responseJSON?.error || @json(__('app.Failed to update category order')),
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
            }
        });
    });
    @endif

    $(document).on('click', '.toggle-category-status', function (e) {
        e.preventDefault();
        const $btn = $(this);
        $.ajax({
            url: $btn.data('url'),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function (response) {
                const active = parseInt(response.status, 10) === 1;
                $btn.data('active', active ? '1' : '0');
                $btn.attr('title', active ? @json(__('app.Deactivate category')) : @json(__('app.Activate category')));
                $btn.find('i').attr('class', active ? 'ti ti-eye' : 'ti ti-eye-off');
                $btn.removeClass('btn-outline-secondary btn-outline-success btn-outline-danger');
                $btn.addClass(active ? 'btn-outline-success' : 'btn-outline-danger');

                const $handle = $btn.closest('.dd-item').children('.dd-handle').first();
                $handle.find('.badge-inactive-status').remove();
                if (! active)
                {
                    $handle.append('<span class="badge bg-label-warning ms-1 badge-inactive-status">' + @json(__('app.Inactive')) + '</span>');
                }

                Swal.fire({
                    text: response.message,
                    icon: 'success',
                    timer: 1600,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'p-3'
                    }
                });
            },
            error: function (xhr) {
                Swal.fire({
                    title: @json(__('app.Error')),
                    text: xhr.responseJSON?.message || xhr.responseJSON?.error || @json(__('app.Request failed')),
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
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