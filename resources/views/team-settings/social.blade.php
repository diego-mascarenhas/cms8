@extends('layouts/layoutMaster')

@section('title', __('Social networks'))

@section('content')
@include('team-settings.partials.header', [
    'team' => $team,
    'title' => __('Social networks'),
    'subtitle' => __('Connect Meta, LinkedIn, Bluesky and other accounts for this team. Stats use these connections on the dashboard.'),
])

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Connected accounts') }}</h5>
        <p class="text-muted mb-0">{{ __('OAuth and Bluesky credentials are stored per team. Configure Meta / LinkedIn app keys in .env on the server.') }}</p>
    </div>
    <div class="card-body">
        @if ($connections->isEmpty())
            <p class="text-muted mb-0">{{ __('No social connections yet. Use the buttons below when OAuth is enabled for your environment.') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Connected by') }}</th>
                            <th>{{ __('Updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($connections as $connection)
                            <tr>
                                <td><code>{{ $connection->provider }}</code></td>
                                <td>{{ $connection->source?->name ?? '—' }}</td>
                                <td><span class="badge bg-label-primary">{{ $connection->status }}</span></td>
                                <td>{{ $connection->user?->name ?? '—' }}</td>
                                <td>{{ $connection->updated_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Add connections') }}</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ __('Meta (Facebook / Instagram) and LinkedIn use Laravel Socialite. Bluesky can use a handle (and optional app password) once the form is wired.') }}</p>
        <div class="d-flex flex-wrap gap-2">
            <span class="btn btn-label-secondary disabled">{{ __('Connect Meta') }}</span>
            <span class="btn btn-label-secondary disabled">{{ __('Connect LinkedIn') }}</span>
            <span class="btn btn-label-secondary disabled">{{ __('Connect Bluesky') }}</span>
        </div>
        <p class="small text-muted mt-3 mb-0">{{ __('These actions will be enabled when integration routes are configured.') }}</p>
    </div>
</div>
@endsection
