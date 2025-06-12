@extends('layouts/layoutMaster')

@section('title', isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator') }}</h4>
        </div>
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

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary me-2">
                        {{ isset($collaborator) ? __('Update') : __('Create') }}
                    </button>
                    <a href="{{ route('collaborator-list') }}" class="btn btn-label-secondary">
                        {{ __('Cancel') }}
                    </a>
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