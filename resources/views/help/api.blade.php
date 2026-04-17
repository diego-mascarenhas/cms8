@extends('layouts/layoutHelpSimple')

@section('title', __('API Documentation'))

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
                    <h4 class="card-title mb-0">{{ __('API Documentation Overview') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Integrate Humano with your applications using our comprehensive REST API.') }}</p>

                            <h5 class="mt-4">{{ __('API Overview') }}</h5>
                            <p>{{ __('The Humano API allows you to programmatically interact with your data. You can create, read, update, and delete records across all modules, as well as access reporting and analytics features.') }}</p>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card border-primary h-100">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary">
                                                <i class="ti ti-globe me-2"></i>
                                                {{ __('Base URL') }}
                                            </h6>
                                            <code class="d-block">{{ url('/') }}/api/team</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-info h-100">
                                        <div class="card-body">
                                            <h6 class="card-title text-info">
                                                <i class="ti ti-file-type-json me-2"></i>
                                                {{ __('Response Format') }}
                                            </h6>
                                            <code class="d-block">application/json</code>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-4">{{ __('API Features') }}</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="ti ti-key display-6 text-primary mb-2"></i>
                                            <h6>{{ __('Secure') }}</h6>
                                            <p class="small">{{ __('Token-based authentication with team-level access control') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="ti ti-api display-6 text-success mb-2"></i>
                                            <h6>{{ __('RESTful') }}</h6>
                                            <p class="small">{{ __('Standard REST endpoints with proper HTTP methods') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="ti ti-code display-6 text-info mb-2"></i>
                                            <h6>{{ __('Comprehensive') }}</h6>
                                            <p class="small">{{ __('Access to all major business entities and operations') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('API Authentication Types') }}
                                </h6>
                                <p class="mb-2">{{ __('This API uses team-based authentication. Generate tokens in your team settings to access team-scoped data. Some endpoints may require user authentication instead.') }}</p>
                                <p class="mb-0 small">{{ __('Team tokens: open Team Settings → API Tokens and click “Generate API Token”.') }}
                                    @auth
                                        @if(auth()->user()->currentTeam)
                                            <a href="{{ route('team-settings.api-tokens', auth()->user()->currentTeam) }}">{{ __('Open API tokens') }}</a>
                                        @endif
                                    @endauth
                                </p>
                            </div>

                            <h6 class="mt-4">{{ __('Available Endpoints') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('Module') }}</th>
                                            <th>{{ __('Endpoint') }}</th>
                                            <th>{{ __('Description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>{{ __('Contacts') }}</strong></td>
                                            <td><code>/api/team/contacts</code></td>
                                            <td>{{ __('Manage customer and contact information') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Projects') }}</strong></td>
                                            <td><code>/api/team/projects</code></td>
                                            <td>{{ __('Project management and tracking') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Tasks') }}</strong></td>
                                            <td><code>/api/tasks</code></td>
                                            <td>{{ __('Task creation and management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Invoices') }}</strong></td>
                                            <td><code>/api/invoices</code></td>
                                            <td>{{ __('Billing and invoice management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Messages') }}</strong></td>
                                            <td><code>/api/message</code></td>
                                            <td>{{ __('Email and SMS campaign management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Contents') }}</strong></td>
                                            <td><code>/api/team/contents</code></td>
                                            <td>{{ __('Website content management with multi-language support (team token auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Enterprises') }}</strong></td>
                                            <td><code>/api/enterprises</code></td>
                                            <td>{{ __('Enterprise and client management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Payments') }}</strong></td>
                                            <td><code>/api/payments</code></td>
                                            <td>{{ __('Payment and transaction management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Products') }}</strong></td>
                                            <td><code>/api/products</code></td>
                                            <td>{{ __('Product catalog management (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Orders') }}</strong></td>
                                            <td><code>/api/orders</code></td>
                                            <td>{{ __('Order management and tracking (user auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Prompts') }}</strong></td>
                                            <td><code>/api/team/prompts</code></td>
                                            <td>{{ __('AI-powered prompts for content generation (team token auth)') }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>{{ __('Assistant Chat') }}</strong></td>
                                            <td><code>POST /api/team/assistant/chat</code></td>
                                            <td>{{ __('Chat with the full assistant (router + flows). Body: message, optional prompt_key. Team token auth.') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="mt-4">{{ __('Getting Started with the API') }}</h6>
                            <ol>
                                <li><strong>{{ __('Generate an API Token') }}</strong>: {{ __('Go to your team settings and create an API token') }}</li>
                                <li><strong>{{ __('Review Authentication') }}</strong>: {{ __('Learn how to authenticate your API requests') }}</li>
                                <li><strong>{{ __('Choose an Endpoint') }}</strong>: {{ __('Start with the Contacts API for basic operations') }}</li>
                                <li><strong>{{ __('Test Your Integration') }}</strong>: {{ __('Use tools like Postman or curl to test your requests') }}</li>
                            </ol>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('Important Notes') }}
                                </h6>
                                <ul class="mb-0">
                                    <li>{{ __('All API requests are scoped to your current team') }}</li>
                                    <li>{{ __('API tokens have the same permissions as the user who created them') }}</li>
                                    <li>{{ __('Rate limiting applies to API requests') }}</li>
                                    <li>{{ __('All responses are in JSON format') }}</li>
                                </ul>
                            </div>

                            <div class="d-flex flex-wrap gap-3 mt-4">
                                <a href="{{ route('help.api.authentication') }}" class="btn btn-primary">
                                    <i class="ti ti-key me-2"></i>
                                    {{ __('Learn Authentication') }}
                                </a>
                                <a href="{{ route('help.api.contacts') }}" class="btn btn-success">
                                    <i class="ti ti-users me-2"></i>
                                    {{ __('Contacts API') }}
                                </a>
                                <a href="{{ route('help.api.tasks') }}" class="btn btn-warning">
                                    <i class="ti ti-list-check me-2"></i>
                                    {{ __('Tasks API') }}
                                </a>
                                <a href="{{ route('help.api.prompts') }}" class="btn btn-purple">
                                    <i class="ti ti-sparkles me-2"></i>
                                    {{ __('Prompts API') }}
                                </a>
                                <a href="{{ route('help.api.contents') }}" class="btn btn-info">
                                    <i class="ti ti-file-text me-2"></i>
                                    {{ __('Contents API') }}
                                </a>
                                <a href="{{ route('help.api.enterprises') }}" class="btn btn-secondary">
                                    <i class="ti ti-building me-2"></i>
                                    {{ __('Enterprises API') }}
                                </a>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
@endsection
