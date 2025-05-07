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

                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">{{ __('Phone') }}</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $collaborator->phone ?? '') }}">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="enterprise_id" class="form-label">{{ __('Enterprise') }}</label>
                        <select class="form-select @error('enterprise_id') is-invalid @enderror" id="enterprise_id" name="enterprise_id">
                            <option value="">{{ __('Select Enterprise') }}</option>
                            @foreach($enterprises as $enterprise)
                                <option value="{{ $enterprise->id }}" {{ old('enterprise_id', $collaborator->enterprise_id ?? '') == $enterprise->id ? 'selected' : '' }}>
                                    {{ $enterprise->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('enterprise_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="responsible_id" class="form-label">{{ __('Collaborator') }}</label>
                        <select class="form-select @error('responsible_id') is-invalid @enderror" id="responsible_id" name="responsible_id">
                            <option value="">{{ __('Select Collaborator') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('responsible_id', $collaborator->responsible_id ?? '') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('responsible_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">{{ __('Categories') }}</label>
                        <div class="row">
                            @foreach($categories as $category)
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}" id="category_{{ $category->id }}"
                                            {{ isset($collaborator) && $collaborator->categories->contains($category->id) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="category_{{ $category->id }}">
                                            {{ $category->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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