@extends('layouts/layoutMaster')

@section('title', $type === 'posts' ? __('Posts (WordPress)') : __('Pages (WordPress)'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ $type === 'posts' ? __('Posts (WordPress)') : __('Pages (WordPress)') }}</h4>
            <p class="text-muted">{{ __('Content from your WordPress site') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="ti ti-world mb-3" style="font-size: 3rem;"></i>
            <h5 class="card-title">{{ __('WordPress not configured') }}</h5>
            <p class="card-text text-muted">{{ __('Configure the WordPress connection in Team Settings to manage posts and pages from Humano.') }}</p>
            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'wordpress']) }}" class="btn btn-primary">{{ __('Configure WordPress') }}</a>
        </div>
    </div>
@endsection
