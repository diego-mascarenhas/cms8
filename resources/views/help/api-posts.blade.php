@extends('layouts/layoutHelpSimple')

@section('title', __('Posts API'))

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
                <h4 class="card-title mb-0">{{ __('Posts API Reference') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('WordPress-like content API for managing posts, pages and custom post types programmatically.') }}</p>

                <h5 class="mt-4">{{ __('Base URL') }}</h5>
                <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/posts</code>
                <p class="text-muted mt-2">{{ __('This endpoint uses team token authentication.') }}</p>

                <h5 class="mt-4">{{ __('Authentication') }}</h5>
                <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                <h5 class="mt-4">{{ __('List posts') }}</h5>
                <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/posts?post_type=post&post_status=publish&per_page=10" \
  -H "Authorization: Bearer {{ $apiToken }}"</code></pre>
                <p class="text-muted">{{ __('Filters: post_type, post_status, slug, parent, term, search, page, per_page.') }}</p>

                <h5 class="mt-4">{{ __('Get a single post') }}</h5>
                <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/posts/123" \
  -H "Authorization: Bearer {{ $apiToken }}"</code></pre>

                <h5 class="mt-4">{{ __('Create a post') }}</h5>
                <pre><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/posts" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -d '{
    "post_type": "post",
    "post_title": "Hello world",
    "post_content": "<p>My first post</p>",
    "post_status": "publish",
    "terms": [1, 2],
    "meta": {"_humano_subtitle_en": "Subtitle"}
  }'</code></pre>

                <h5 class="mt-4">{{ __('Public read-only API') }}</h5>
                <p class="text-muted">{{ __('Anonymous access (no token) for teams that enabled cms_public_enabled:') }}</p>
                <pre><code class="language-bash">curl -X GET "{{ url('/') }}/api/public/your-team-slug/posts?post_type=post"
curl -X GET "{{ url('/') }}/api/public/your-team-slug/posts/page/about-us"</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection
