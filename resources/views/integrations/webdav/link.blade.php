@extends('layouts/layoutMaster')

@section('title', __('app.webdav_link_account_title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Settings') }}/</span>
            {{ __('app.webdav_link_account_title') }}
        </h4>
        <p class="text-muted">{{ __('app.webdav_link_account_help') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (! ($apiConfigured ?? true))
            <div class="alert alert-warning">{{ __('app.webdav_api_not_configured') }}</div>
        @endif

        <form method="POST" action="{{ route('integrations.webdav.link') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <x-input-general id="email" label="Email (*)" :value="old('email')" />
                </div>
                <div class="col-md-6">
                    <x-input-general id="password" label="{{ __('Password') }} (*)" type="password" :value="old('password')" />
                </div>
            </div>
            <div class="pt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" @disabled(! ($apiConfigured ?? true))>{{ __('app.webdav_link_account') }}</button>
                <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
