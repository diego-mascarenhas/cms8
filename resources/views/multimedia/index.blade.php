@extends('layouts/layoutMaster')

@section('title', __('Multimedia'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.Multimedia') }}</h4>
        <p class="text-muted">{{ __('app.Manage media files and galleries') }}</p>
    </div>
    @can('create', \App\Models\Multimedia::class)
    <div class="mt-3 mt-md-0">
        <a href="{{ route('multimedia.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('app.Add Media') }}
        </a>
    </div>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter_status">{{ __('app.Status') }}</label>
                <select id="filter_status" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_visibility">{{ __('app.Visibility') }}</label>
                <select id="filter_visibility" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($visibilityOptions as $visibility)
                        <option value="{{ $visibility->value }}" {{ request('visibility') == $visibility->value ? 'selected' : '' }}>
                            {{ $visibility->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_category">{{ __('app.Category') }}</label>
                <select id="filter_category" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filter_type">{{ __('app.Type') }}</label>
                <select id="filter_type" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>{{ __('app.Image') }}</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>{{ __('app.Video') }}</option>
                    <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>{{ __('app.Audio') }}</option>
                    <option value="document" {{ request('type') === 'document' ? 'selected' : '' }}>{{ __('app.Document') }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_tag">{{ __('app.Tags') }}</label>
                <select id="filter_tag" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_gallery">{{ __('app.Gallery') }}</label>
                <select id="filter_gallery" class="form-select select2">
                    <option value="">{{ __('app.All') }}</option>
                    @foreach($galleryTags as $tag)
                        <option value="{{ $tag->id }}" {{ request('gallery_tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                    <i class="ti ti-refresh me-1"></i> {{ __('app.Reset') }}
                </button>
                @if(request('gallery_tag_id') && auth()->user()->can('create', \App\Models\Multimedia::class))
                    <a href="{{ route('multimedia.gallery', request('gallery_tag_id')) }}" class="btn btn-outline-primary">
                        <i class="ti ti-sort-ascending me-1"></i> {{ __('app.Order Gallery') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script>
        $(document).ready(function () {
            $('.select2').select2({ width: '100%' });

            function bindFilters(table) {
                if (!table) {
                    return;
                }

                $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type')
                    .off('change.multimedia')
                    .on('change.multimedia', function () {
                        table.ajax.reload();
                    });

                $('#resetFilters')
                    .off('click.multimedia')
                    .on('click.multimedia', function () {
                        $('#filter_status, #filter_visibility, #filter_category, #filter_tag, #filter_gallery, #filter_type')
                            .val(null)
                            .trigger('change');
                    });

                table.off('preXhr.dt.multimedia').on('preXhr.dt.multimedia', function (e, settings, data) {
                    data.status = $('#filter_status').val();
                    data.visibility = $('#filter_visibility').val();
                    data.category_id = $('#filter_category').val();
                    data.tag_id = $('#filter_tag').val();
                    data.gallery_tag_id = $('#filter_gallery').val();
                    data.type = $('#filter_type').val();
                });
            }

            if ($.fn.dataTable.isDataTable('#multimedia-table')) {
                bindFilters($('#multimedia-table').DataTable());
            } else {
                $('#multimedia-table').on('init.dt', function () {
                    bindFilters($('#multimedia-table').DataTable());
                });
            }
        });

        function deleteRecord(id) {
            Swal.fire({
                title: '{{ __("app.Are you sure you want to delete this record?") }}',
                text: '{{ __("app.This action cannot be undone") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: '{{ __("app.Yes, delete") }}',
                cancelButtonText: '{{ __("app.Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('multimedia.destroy', ['multimedia' => ':ID']) }}".replace(':ID', id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(response => response.json()).then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("app.Deleted") }}',
                            text: data.success
                        });
                        $('#multimedia-table').DataTable().ajax.reload();
                    }).catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("app.Error") }}',
                            text: '{{ __("app.Failed to delete multimedia.") }}'
                        });
                    });
                }
            });
        }
    </script>
@endpush
