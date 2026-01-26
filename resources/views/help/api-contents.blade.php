@extends('layouts/layoutHelpSimple')

@section('title', __('Contents API'))

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
                    <h4 class="card-title mb-0">{{ __('Contents API Reference') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Complete API reference for managing website contents programmatically with multi-language support.') }}</p>

                            <h5 class="mt-4">{{ __('Base URL') }}</h5>
                            <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/contents</code>
                            <p class="text-muted mt-2">{{ __('Note: This endpoint uses team token authentication. Use your team API token instead of user Sanctum token.') }}</p>

                            <h5 class="mt-4">{{ __('Authentication') }}</h5>
                            <p>{{ __('All requests require Bearer token authentication. Include the token in the Authorization header:') }}</p>
                            <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <div class="alert alert-info mt-3" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('Multi-Language Support') }}
                                </h6>
                                <p class="mb-0">{{ __('All content fields support multiple languages. Use locale-specific field names (e.g., title_es, title_en) or the locale query parameter to retrieve content in a specific language. Supported locales: es, en, it, pt, fr, de.') }}</p>
                            </div>

                            <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                            <!-- Quick Navigation -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li><a href="#list-contents" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Contents') }}</a></li>
                                                <li><a href="#get-content" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get Content') }}</a></li>
                                                <li><a href="#create-content" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Create Content') }}</a></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li><a href="#update-content" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Update Content') }}</a></li>
                                                <li><a href="#delete-content" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Delete Content') }}</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- List Contents -->
                            <div class="card mt-4" id="list-contents">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Contents') }}
                                        <a href="#list-contents" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contents</code>
                                    <p>{{ __('Retrieve a paginated list of contents.') }}</p>

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
                                                <td><code>locale</code></td>
                                                <td>string</td>
                                                <td>{{ __('Language code for translatable fields (es, en, it, pt, fr, de)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>section_category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Filter by section category ID') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Filter by category ID') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>status</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Filter by status (1=Draft, 2=Pending, 3=Published, 4=Archived)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>featured</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('Filter by featured status') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>search</code></td>
                                                <td>string</td>
                                                <td>{{ __('Search term for title or subtitle') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contents?page=1&per_page=10&locale=es&status=3" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                            </div>

                                    <h6>{{ __('Response') }}</h6>
                                    <pre class="docs-code"><code class="language-json">{
  "success": true,
  "message": "Contents retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Bienvenido",
      "subtitle": "Subtítulo del contenido",
      "url": "/bienvenido",
      "content": "<p>Contenido HTML...</p>",
      "section_category": {
        "id": 1,
        "name": "Home"
      },
      "category": {
        "id": 2,
        "name": "Principal"
      },
      "status": 3,
      "featured": true,
      "featured_slide": false,
      "featured_modal": false,
      "order": 1,
      "template": null,
      "seo_title": "Título SEO",
      "seo_keywords": "palabras, clave",
      "seo_description": "Descripción SEO",
      "data": {},
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 25,
    "last_page": 3
  }
}</code></pre>
                            </div>

                            <!-- Get Single Content -->
                            <div class="card mt-4" id="get-content">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('Get Content') }}
                                        <a href="#get-content" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contents/{id}</code>
                                    <p>{{ __('Retrieve a single content by ID.') }}</p>

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
                                                <td><code>locale</code></td>
                                                <td>string</td>
                                                <td>{{ __('Language code for translatable fields (es, en, it, pt, fr, de)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contents/1?locale=en" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>
                            </div>
                                </div>
                            </div>

                            <!-- Create Content -->
                            <div class="card mt-4" id="create-content">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-success me-2">POST</span>
                                        {{ __('Create Content') }}
                                        <a href="#create-content" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contents</code>
                                    <p>{{ __('Create a new content. All translatable fields support multiple languages using locale-specific field names.') }}</p>

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
                                                <td><code>section_category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Yes') }}</td>
                                                <td>{{ __('Section category ID') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>category_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Category ID') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>status</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Yes') }}</td>
                                                <td>{{ __('Status (1=Draft, 2=Pending, 3=Published, 4=Archived)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>order</code></td>
                                                <td>integer</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Display order (0-255)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>featured</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Featured content flag') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>featured_slide</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Featured in slide flag') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>featured_modal</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Featured in modal flag') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>template</code></td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Template name (max 50 chars)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>title_es</code>, <code>title_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Title in specific language (es, en, it, pt, fr, de)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>subtitle_es</code>, <code>subtitle_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Subtitle in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>url_es</code>, <code>url_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('URL in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>content_es</code>, <code>content_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Content HTML in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>seo_title_es</code>, <code>seo_title_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('SEO title in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>seo_keywords_es</code>, <code>seo_keywords_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('SEO keywords in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>seo_description_es</code>, <code>seo_description_en</code>, etc.</td>
                                                <td>string</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('SEO description in specific language') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>multimedia</code></td>
                                                <td>array</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Array of multimedia IDs to associate') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>data.{field_key}</code></td>
                                                <td>mixed</td>
                                                <td>{{ __('No') }}</td>
                                                <td>{{ __('Additional fields based on section configuration') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/contents" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "section_category_id": 1,
    "status": 3,
    "order": 1,
    "featured": true,
    "title_es": "Bienvenido",
    "title_en": "Welcome",
    "subtitle_es": "Subtítulo en español",
    "subtitle_en": "English subtitle",
    "content_es": "<p>Contenido en español</p>",
    "content_en": "<p>English content</p>",
    "seo_title_es": "Título SEO",
    "seo_description_es": "Descripción SEO",
    "multimedia": [1, 2, 3]
  }'</code></pre>

                                    <div class="alert alert-info mt-3" role="alert">
                                        <strong>{{ __('Note:') }}</strong> {{ __('You can provide content in multiple languages by including fields for each locale (es, en, it, pt, fr, de). Only include the languages you want to set.') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Update Content -->
                            <div class="card mt-4" id="update-content">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-warning me-2">PUT</span>
                                        {{ __('Update Content') }}
                                        <a href="#update-content" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contents/{id}</code>
                                    <p>{{ __('Update an existing content. Only include fields you want to update. Multi-language fields will merge with existing translations.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                                    <pre class="docs-code"><code class="language-bash">curl -X PUT "{{ url('/') }}/api/team/contents/1" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title_es": "Título actualizado",
    "status": 3,
    "featured": false
  }'</code></pre>

                                    <div class="alert alert-warning mt-3" role="alert">
                                        <strong>{{ __('Note:') }}</strong> {{ __('When updating multi-language fields, only the specified locales will be updated. Other locales will remain unchanged.') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Content -->
                            <div class="card mt-4" id="delete-content">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-danger me-2">DELETE</span>
                                        {{ __('Delete Content') }}
                                        <a href="#delete-content" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/contents/{id}</code>
                                    <p>{{ __('Delete a content. This action performs a soft delete and cannot be undone.') }}</p>

                                    <h6>{{ __('Example Request') }}</h6>
                                    <pre class="docs-code"><code class="language-bash">curl -X DELETE "{{ url('/') }}/api/team/contents/1" \
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
