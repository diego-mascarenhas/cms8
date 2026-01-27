@extends('layouts/layoutHelpSimple')

@section('title', __('Enterprises API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Main Content - Full Width since sidebar is in layout -->
    <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ __('Enterprises API Reference') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Complete API reference for managing enterprises and clients programmatically.') }}</p>

                            <h5 class="mt-4">{{ __('Base URL') }}</h5>
                            <code class="d-block p-3 bg-light">{{ url('/') }}/api/enterprises</code>

                            <h5 class="mt-4">{{ __('Authentication') }}</h5>
                            <p>{{ __('All requests require Bearer token authentication. Include the token in the Authorization header:') }}</p>
                            <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                            <!-- Quick Navigation -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li><a href="#list-enterprises" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Enterprises') }}</a></li>
                                                <li><a href="#get-enterprise" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get Enterprise') }}</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- List Enterprises -->
                            <div class="card mt-4" id="list-enterprises">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Enterprises') }}
                                        <a href="#list-enterprises" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/enterprises</code>
                                    <p>{{ __('Retrieve a paginated list of enterprises.') }}</p>

                                    <h6>{{ __('Query Parameters') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Parameter') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>page</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Page number (default: 1)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>per_page</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Items per page (default: 20)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/enterprises?page=1&per_page=10" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                            </div>

                                    <h6>{{ __('Response') }}</h6>
                                    <pre class="docs-code"><code class="language-json">{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Example Corp",
        "email": "info@example.com",
        "phone": "+1234567890",
        "status": {
          "id": 1,
          "name": "Active"
        },
        "enterprise_billing_addresses": [
          {
            "id": 1,
            "name": "Razón Social",
            "identification_number": "20-12345678-9"
          }
        ],
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
      }
    ],
    "per_page": 10,
    "total": 25,
    "last_page": 3
  },
  "team": {
    "id": 1,
    "name": "My Team"
  },
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "admin"
  },
  "access_level": "full"
}</code></pre>
                            </div>

                            <!-- Get Single Enterprise -->
                            <div class="card mt-4" id="get-enterprise">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('Get Enterprise') }}
                                        <a href="#get-enterprise" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/enterprises/{id}</code>
                                    <p>{{ __('Retrieve a single enterprise by ID.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/enterprises/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                            </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('Rate Limiting') }}
                                </h6>
                                <p class="mb-0">{{ __('API requests are rate limited. The current limit is 60 requests per minute per token. If you exceed this limit, you\'ll receive a 429 Too Many Requests response.') }}</p>
                            </div>

                            <div class="d-flex gap-3 mt-4">
                                <a href="{{ route('help.api.authentication') }}" class="btn btn-secondary">
                                    <i class="ti ti-arrow-left me-2"></i>
                                    {{ __('Back to Authentication') }}
                                </a>
                                <a href="{{ route('help.api') }}" class="btn btn-secondary">
                                    {{ __('Back to API Overview') }}
                                </a>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
@endsection
