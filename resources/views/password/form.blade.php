@extends('layouts/layoutMaster')

@section('title', 'Passwords')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Passwords/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Store credentials securely for your team.') }}</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Password record') }}</h5>
    <form class="card-body" method="POST" action="{{ isset($data->id) ? route('passwords.update', $data) : route('passwords.store') }}">
        @csrf
        @if(isset($data->id))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">{{ __('Name') }} (*)</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="enterprise_id" class="form-label">{{ __('Enterprise') }}</label>
                <select id="enterprise_id" name="enterprise_id" class="form-select @error('enterprise_id') is-invalid @enderror">
                    <option value="">{{ __('None') }}</option>
                    @foreach($enterprises as $id => $name)
                        <option value="{{ $id }}" {{ (string) old('enterprise_id', $data->enterprise_id ?? '') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @error('enterprise_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="username" class="form-label">{{ __('Username') }}</label>
                <input type="text" id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $data->username ?? '') }}">
                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ isset($data->id) ? __('Leave empty to keep current password.') : __('Optional: leave empty if this record is only notes/metadata.') }}</div>
            </div>

            <div class="col-md-12">
                <label for="url" class="form-label">{{ __('URL') }}</label>
                <input type="url" id="url" name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url', $data->url ?? '') }}">
                @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-12">
                <label for="notes" class="form-label">{{ __('Notes') }}</label>
                <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $data->notes ?? '') }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-2">{{ __('Save') }}</button>
            <a href="{{ route('passwords.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
