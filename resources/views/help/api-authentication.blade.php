@extends('layouts/layoutHelpSimple')

@section('title', __('API Authentication'))

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
                    <h4 class="card-title mb-0">{{ __('API Authentication') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Learn how to authenticate your API requests using tokens.') }}</p>

                            <h5 class="mt-4">{{ __('Token-Based Authentication') }}</h5>
                            <p>{{ __('Humano uses token-based authentication for API access. Each API request must include a valid token in the Authorization header.') }}</p>

                            <h6 class="mt-4">{{ __('1. Generating API Tokens') }}</h6>
                            <p>{{ __('To get started with the API, you need to generate an API token:') }}</p>
                            <ol>
                                <li>{{ __('Log in to your Humano account') }}</li>
                                <li>{{ __('Go to Team Settings → API Tokens') }}</li>
                                <li>{{ __('Click “Generate API Token” (opens the generate form)') }}</li>
                                <li>{{ __('Give your token a descriptive name') }}</li>
                                <li>{{ __('Copy the generated token (you won\'t see it again)') }}</li>
                            </ol>
                            @auth
                                @if(auth()->user()->currentTeam)
                                    <p class="mb-0">
                                        <a href="{{ route('team-settings.api-tokens', auth()->user()->currentTeam) }}" class="btn btn-sm btn-primary">{{ __('Open team API tokens') }}</a>
                                    </p>
                                @endif
                            @endauth

                            <div class="alert alert-warning" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-alert-triangle me-2"></i>
                                    {{ __('Security Warning') }}
                                </h6>
                                <p class="mb-0">{{ __('Keep your API tokens secure. They have the same permissions as the user who created them. Never share them publicly or commit them to version control.') }}</p>
                            </div>

                            <h6 class="mt-4">{{ __('2. Using API Tokens') }}</h6>
                            <p>{{ __('Include the token in the Authorization header of all API requests:') }}</p>

                            <h6>{{ __('Header Format') }}</h6>
                            <pre class="docs-code"><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <h6 class="mt-4">{{ __('Example Request') }}</h6>
                            <pre class="docs-code"><code class="language-bash">curl -X GET "{{ url('/') }}/api/team/contacts" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>

                            <h6 class="mt-4">{{ __('3. Team Context') }}</h6>
                            <p>{{ __('API requests are automatically scoped to the team that owns the API token. You cannot access data from other teams with the same token.') }}</p>

                            <h6 class="mt-4">{{ __('4. Token Permissions') }}</h6>
                            <p>{{ __('API tokens inherit the permissions of the user who created them. Make sure the user has appropriate permissions for the operations you need to perform.') }}</p>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="card-title text-success">
                                                <i class="ti ti-check-circle me-2"></i>
                                                {{ __('What\'s Allowed') }}
                                            </h6>
                                            <ul class="mb-0 small">
                                                <li>{{ __('Read your team\'s data') }}</li>
                                                <li>{{ __('Create new records') }}</li>
                                                <li>{{ __('Update existing records') }}</li>
                                                <li>{{ __('Delete records (with permission)') }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-danger">
                                        <div class="card-body">
                                            <h6 class="card-title text-danger">
                                                <i class="ti ti-x me-2"></i>
                                                {{ __('What\'s Not Allowed') }}
                                            </h6>
                                            <ul class="mb-0 small">
                                                <li>{{ __('Access other teams\' data') }}</li>
                                                <li>{{ __('Bypass user permissions') }}</li>
                                                <li>{{ __('Access system administration') }}</li>
                                                <li>{{ __('Modify token permissions') }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-4">{{ __('5. Error Responses') }}</h6>
                            <p>{{ __('Common authentication errors:') }}</p>

                            <h6>{{ __('Invalid Token') }}</h6>
                            <pre><code class="language-json">{
  "message": "Unauthenticated.",
  "status": 401
}</code></pre>

                            <h6>{{ __('Missing Token') }}</h6>
                            <pre><code class="language-json">{
  "message": "Authorization header missing.",
  "status": 401
}</code></pre>

                            <h6 class="mt-4">{{ __('6. Token Management') }}</h6>
                            <p>{{ __('You can manage your API tokens from the team settings:') }}</p>
                            <ul>
                                <li>{{ __('View all your tokens') }}</li>
                                <li>{{ __('Revoke tokens you no longer need') }}</li>
                                <li>{{ __('See when tokens were last used') }}</li>
                                <li>{{ __('Generate new tokens as needed') }}</li>
                            </ul>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('Best Practices') }}
                                </h6>
                                <ul class="mb-0">
                                    <li>{{ __('Use descriptive names for your tokens') }}</li>
                                    <li>{{ __('Rotate tokens regularly') }}</li>
                                    <li>{{ __('Use different tokens for different applications') }}</li>
                                    <li>{{ __('Monitor token usage in team settings') }}</li>
                                    <li>{{ __('Revoke tokens immediately if compromised') }}</li>
                                </ul>
                            </div>

                            <div class="d-flex gap-3 mt-4">
                                <a href="{{ route('help.api.contacts') }}" class="btn btn-primary">
                                    <i class="ti ti-users me-2"></i>
                                    {{ __('Next: Contacts API') }}
                                </a>
                                <a href="{{ route('help.api') }}" class="btn btn-secondary">
                                    <i class="ti ti-arrow-left me-2"></i>
                                    {{ __('Back to API Overview') }}
                                </a>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
@endsection