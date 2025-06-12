@extends('layouts/layoutMaster')

@section('title', isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                <span class="text-muted fw-light">{{ __('Collaborators') }}/</span> 
                {{ isset($collaborator) ? __('Edit') : __('Create') }}
            </h4>
            <p class="text-muted">{{ isset($collaborator) ? __('Update collaborator information') : __('Add a new collaborator') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @if(isset($collaborator))
                @can('collaborator.show')
                <a href="{{ route('collaborator.show', $collaborator->id) }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-eye me-1"></i>{{ __('View Collaborator') }}
                </a>
                @endcan
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <h5 class="card-header">{{ isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator') }}</h5>
        <div class="card-body">
            <form action="{{ isset($collaborator) ? route('collaborator.update', $collaborator) : route('collaborator.store') }}" method="POST">
                @csrf
                @if(isset($collaborator))
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">{{ __('Name') }}</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $collaborator->name ?? '') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $collaborator->email ?? '') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="pt-4">
                    <div class="col-12 d-flex">
                        <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ isset($collaborator) ? __('Update') : __('Create') }}</button>
                        <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('collaborator-list') }}'">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        $(document).ready(function() {
            $('#enterprise_id, #responsible_id').select2();
        });
    </script>
@endsection 