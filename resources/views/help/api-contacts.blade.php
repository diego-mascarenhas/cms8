@extends('layouts/layoutHelpSimple')

@section('title', __('Contacts API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-xl-3 col-lg-4 col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Documentation') }}</h5>
                </div>
                <div class="card-body">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="{{ route('help.index') }}">
                            <i class="ti ti-home me-2"></i>
                            {{ __('Introduction') }}
                        </a>
                        <a class="nav-link" href="{{ route('help.usage') }}">
                            <i class="ti ti-book me-2"></i>
                            {{ __('How to Use') }}
                        </a>

                        <div class="nav-divider my-2"></div>
                        <h6 class="text-muted mb-2">{{ __('Modules') }}</h6>

                        <a class="nav-link" href="{{ route('help.contacts') }}">
                            <i class="ti ti-users me-2"></i>
                            {{ __('Contact Management') }}
                        </a>

                        <div class="nav-divider my-2"></div>
                        <h6 class="text-muted mb-2">{{ __('API Documentation') }}</h6>

                        <a class="nav-link" href="{{ route('help.api') }}">
                            <i class="ti ti-api me-2"></i>
                            {{ __('API Overview') }}
                        </a>
                        <a class="nav-link" href="{{ route('help.api.authentication') }}">
                            <i class="ti ti-key me-2"></i>
                            {{ __('Authentication') }}
                        </a>
                        <a class="nav-link active" href="{{ route('help.api.contacts') }}">
                            <i class="ti ti-users me-2"></i>
                            {{ __('Contacts API') }}
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-xl-9 col-lg-8 col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ __('Contacts API Reference') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Complete API reference for managing contacts programmatically.') }}</p>

                            <h5 class="mt-4">{{ __('Base URL') }}</h5>
                            <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/contacts</code>

                            <h5 class="mt-4">{{ __('Authentication') }}</h5>
                            <p>{{ __('All requests require Bearer token authentication. Include the token in the Authorization header:') }}</p>
                            <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                            <!-- List Contacts -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Contacts') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts</code>
                                    <p>{{ __('Retrieve a paginated list of contacts.') }}</p>

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
                                                <td>{{ __('Items per page (default: 15)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>search</code></td>
                                                <td>string</td>
                                                <td>{{ __('Search term for name, email, or company') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Filter by category ID') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <div class="code-block-container">
                                <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contacts?page=1&per_page=10&search=john" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                                    </div>
                            </div>

                                    <h6>{{ __('Response') }}</h6>
                                    <div class="code-block-container">
                                        <pre><code class="language-json">{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890",
      "company": "Example Corp",
      "category": {
        "id": 1,
        "name": "Customer"
      },
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 25,
    "last_page": 3
  }
}</code></pre>
                                    </div>
                            </div>

                            <!-- Get Single Contact -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('Get Contact') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts/{id}</code>
                                    <p>{{ __('Retrieve a single contact by ID.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                            <div class="code-block-container">
                                <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contacts/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                                    </div>
                            </div>
                                </div>
                            </div>

                            <!-- Create Contact -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-success me-2">POST</span>
                                        {{ __('Create Contact') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts</code>
                                    <p>{{ __('Create a new contact.') }}</p>

                                    <h6>{{ __('Request Body') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Field') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Required') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>name</code></td>
                                                <td>string</td>
                                                <td>{{ __('Yes') }}</td>
                                                <td>{{ __('Contact full name') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>email</code></td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Contact email address') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>phone</code></td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Contact phone number') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>company</code></td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Company name') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Category ID') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <div class="code-block-container">
                                <pre><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/contacts" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+1987654321",
    "company": "Tech Solutions Inc",
    "category_id": 1
  }'</code></pre>
                                </div>
                            </div>

                            <!-- Update Contact -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-warning me-2">PUT</span>
                                        {{ __('Update Contact') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts/{id}</code>
                                    <p>{{ __('Update an existing contact. Only include fields you want to update.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                                    <div class="code-block-container">
                                        <pre><code class="language-bash">curl -X PUT "{{ url('/') }}/api/team/contacts/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "phone": "+1555123456",
    "company": "Updated Company Name"
  }'</code></pre>
                                </div>
                            </div>

                            <!-- Delete Contact -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-danger me-2">DELETE</span>
                                        {{ __('Delete Contact') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts/{id}</code>
                                    <p>{{ __('Delete a contact. This action cannot be undone.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                                    <div class="code-block-container">
                                        <pre><code class="language-bash">curl -X DELETE "{{ url('/') }}/api/team/contacts/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                                    </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Search Contacts -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-info me-2">GET</span>
                                        {{ __('Search Contacts') }}
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contacts/search</code>
                                    <p>{{ __('Advanced search functionality.') }}</p>

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
                                                <td><code>q</code></td>
                                                <td>string</td>
                                                <td>{{ __('Search query (name, email, company)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>limit</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Maximum results (default: 10)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                                    <div class="code-block-container">
                                        <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contacts/search?q=john&limit=5" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                                    </div>
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
    </div>
</div>
@endsection