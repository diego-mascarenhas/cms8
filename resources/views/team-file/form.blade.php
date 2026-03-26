@extends('layouts/layoutMaster')

@section('title', __('Team files'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
    $(function() {
        if ($.fn.select2) {
            $('#visibility, #category_id').select2();
        }
    });
</script>
@if(isset($data->id))
@can('delete', $data)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('team-file-delete-btn');
        var form = document.getElementById('team-file-delete-form');
        if (!btn || !form) {
            return;
        }
        btn.addEventListener('click', function () {
            Swal.fire({
                title: @json(__('Are you sure?')),
                text: @json(__('This action cannot be undone')),
                icon: 'warning',
                showCancelButton: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger me-2',
                    cancelButton: 'btn btn-label-secondary'
                },
                confirmButtonText: @json(__('Yes, delete')),
                cancelButtonText: @json(__('Cancel')),
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endcan
@endif

@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Team files') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Upload a file and set visibility for your team.') }}</p>
    </div>
    @if(isset($data->id))
    @can('delete', $data)
    <div class="mt-3 mt-md-0">
        <form id="team-file-delete-form" method="POST" action="{{ route('team-file.destroy', $data) }}" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="button" class="btn btn-label-danger waves-effect" id="team-file-delete-btn">
                <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
            </button>
        </form>
    </div>
    @endcan
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('File') }}</h5>
    <form class="card-body" method="POST" action="{{ isset($data->id) ? route('team-file.update', $data) : route('team-file.store') }}" enctype="multipart/form-data">
        @csrf
        @if(isset($data->id))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-8">
                <x-input-general id="title" label="{{ __('Title') }} (*)" value="{{ old('title', $data->title ?? '') }}" />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="visibility">{{ __('Visibility') }} (*)</label>
                <select name="visibility" id="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
                    @foreach($visibilityOptions as $visibility)
                        <option value="{{ $visibility->value }}" {{ (int) old('visibility', $data->visibility?->value ?? 1) === $visibility->value ? 'selected' : '' }}>
                            {{ $visibility->label() }}
                        </option>
                    @endforeach
                </select>
                @error('visibility')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-12">
                <x-module-categories-select
                    id="category_id"
                    label="{{ __('Category') }}"
                    moduleKey="team_files"
                    :allowEmpty="true"
                    :selected="old('category_id', $data->category_id ?? '')"
                />
            </div>
            <div class="col-md-12">
                <x-input-textarea id="description" label="{{ __('Description') }}" value="{{ old('description', $data->description ?? '') }}" />
            </div>
            <div class="col-md-12">
                <label class="form-label" for="file">{{ __('File') }} @if(!isset($data->id))(*)@else <span class="text-muted">({{ __('optional — leave empty to keep current') }})</span>@endif</label>
                <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" @if(!isset($data->id)) required @endif />
                @error('file')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if(isset($data->id) && $data->getFirstMedia('file'))
                    <p class="text-muted small mt-1 mb-0">{{ __('Current file') }}: {{ $data->getFirstMedia('file')->file_name }}</p>
                @endif
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('team-file.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>
</div>

@endsection
