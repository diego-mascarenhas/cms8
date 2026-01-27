@extends('layouts/layoutHelpSimple')

@section('title', __('Products API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Products API Reference') }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <p class="lead">{{ __('Complete API reference for managing products programmatically.') }}</p>

                        <h5 class="mt-4">{{ __('Base URL') }}</h5>
                        <code class="d-block p-3 bg-light">{{ url('/') }}/api/products</code>

                        <h5 class="mt-4">{{ __('Authentication') }}</h5>
                        <p>{{ __('All requests require Bearer token authentication. Include the token in the Authorization header:') }}</p>
                        <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                        <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled mb-0">
                                            <li><a href="#list-products" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Products') }}</a></li>
                                            <li><a href="#get-product" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get Product') }}</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- List Products -->
                        <div class="card mt-4" id="list-products">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <span class="badge bg-primary me-2">GET</span>
                                    {{ __('List Products') }}
                                    <a href="#list-products" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                </h6>
                            </div>
                            <div class="card-body">
                                <code class="d-block mb-3">{{ url('/') }}/api/products</code>
                                <p>{{ __('Retrieve a paginated list of products.') }}</p>

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
                                        <tr>
                                            <td><code>status</code></td>
                                            <td>boolean</td>
                                            <td>{{ __('Filter by status (true for active, false for inactive)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><code>category_id</code></td>
                                            <td>integer</td>
                                            <td>{{ __('Filter by category ID') }}</td>
                                        </tr>
                                        <tr>
                                            <td><code>whatsapp_enabled</code></td>
                                            <td>boolean</td>
                                            <td>{{ __('Filter by WhatsApp enabled status') }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6>{{ __('Example Request') }}</h6>
                                <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/products?status=true&category_id=1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>

                                <h6>{{ __('Response') }}</h6>
                                <pre class="docs-code"><code class="language-json">{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Product Name",
        "description": "Product description",
        "price": 99.99,
        "currency": {
          "id": 840,
          "code": "USD"
        },
        "category": {
          "id": 1,
          "name": "Category Name"
        },
        "status": true,
        "whatsapp_enabled": true,
        "created_at": "2024-01-15T10:30:00Z"
      }
    ],
    "per_page": 20,
    "total": 50,
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
  }
}</code></pre>
                            </div>
                        </div>

                        <!-- Get Single Product -->
                        <div class="card mt-4" id="get-product">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <span class="badge bg-primary me-2">GET</span>
                                    {{ __('Get Product') }}
                                    <a href="#get-product" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                </h6>
                            </div>
                            <div class="card-body">
                                <code class="d-block mb-3">{{ url('/') }}/api/products/{id}</code>
                                <p>{{ __('Retrieve a single product by ID.') }}</p>

                                <h6>{{ __('Example Request') }}</h6>
                                <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/products/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4" role="alert">
                            <h6 class="alert-heading mb-2">
                                <i class="ti ti-info-circle me-2"></i>
                                {{ __('Rate Limiting') }}
                            </h6>
                            <p class="mb-0">{{ __('API requests are rate limited. The current limit is 60 requests per minute per token.') }}</p>
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
</div>
@endsection
