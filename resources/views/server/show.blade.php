@extends('layouts.contentNavbarLayout')

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>Server Details</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('server.index') }}" class="btn btn-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back to Servers
            </a>
            <a href="{{ route('server.edit', $server->id) }}" class="btn btn-primary">
                <i class="ti ti-edit me-1"></i> Edit Server
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Server Information</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th style="width: 30%;">Name</th>
                            <td>{{ $server->name }}</td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td>{{ $server->ip ?: 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <th>Server URL</th>
                            <td>{{ $server->server_url }}</td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td>{{ $server->username }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-{{ $server->status_id->color() }}">
                                    {{ $server->status_id->name() }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td>{{ $server->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated</th>
                            <td>{{ $server->updated_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="#" class="btn btn-outline-primary mb-3" id="test-connection-btn">
                        <i class="ti ti-world me-1"></i> Test Connection
                    </a>
                    
                    <form action="{{ route('server.destroy', $server->id) }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this server?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="ti ti-trash me-1"></i> Delete Server
                        </button>
                    </form>
                </div>
            </div>

            @if($server->domains()->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Domains</h5>
                </div>
                <div class="card-body">
                    <p>This server has {{ $server->domains()->count() }} domain(s):</p>
                    <ul class="list-group">
                        @foreach($server->domains()->take(5)->get() as $domain)
                            <li class="list-group-item">
                                <a href="{{ route('domain.show', $domain->id) }}">{{ $domain->domain }}</a>
                            </li>
                        @endforeach
                        
                        @if($server->domains()->count() > 5)
                            <li class="list-group-item text-center">
                                <em>And {{ $server->domains()->count() - 5 }} more...</em>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 