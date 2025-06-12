@extends('layouts/layoutMaster')

@section('title', 'Team Settings')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Team Settings</h4>
        <p class="text-muted">Configure your team settings and preferences</p>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Available Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="ti ti-brand-stripe mb-3" style="font-size: 2rem;"></i>
                                    <h5 class="card-title">Stripe Integration</h5>
                                    <p class="card-text">Configure Stripe API keys and webhook settings</p>
                                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'stripe']) }}" class="btn btn-primary">Configure</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="ti ti-category mb-3" style="font-size: 2rem;"></i>
                                    <h5 class="card-title">Categories</h5>
                                    <p class="card-text">Configure default category settings and preferences</p>
                                    <div class="btn-group">
                                        <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'categories']) }}" class="btn btn-primary">Configure</a>
                                        <a href="{{ route('categories.index') }}" class="btn btn-outline-primary">Manage</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="ti ti-bell mb-3" style="font-size: 2rem;"></i>
                                    <h5 class="card-title">Notifications</h5>
                                    <p class="card-text">Manage notification preferences for your team</p>
                                    <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'notifications']) }}" class="btn btn-primary">Configure</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="ti ti-star mb-3" style="font-size: 2rem;"></i>
                                    <h5 class="card-title">Valorations</h5>
                                    <p class="card-text">Manage contact valorations for your team</p>
                                    <a href="{{ route('team-settings.valorations', $team) }}" class="btn btn-primary">Manage</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($groupedSettings->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Current Settings</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Key</th>
                                <th>Value</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupedSettings as $group => $settings)
                                @foreach($settings as $setting)
                                <tr>
                                    <td>{{ ucfirst($setting->group) }}</td>
                                    <td>{{ $setting->key }}</td>
                                    <td>
                                        @if($setting->is_encrypted)
                                            <span class="badge bg-primary">Encrypted</span>
                                        @else
                                            @if(is_bool($setting->value))
                                                {{ $setting->value ? 'Enabled' : 'Disabled' }}
                                            @else
                                                {{ Str::limit($setting->value, 50) }}
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $setting->updated_at->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection 