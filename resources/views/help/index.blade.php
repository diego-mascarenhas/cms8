@extends('layouts/layoutHelpSimple')

@section('title', __('Help & Documentation'))

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
                    <h4 class="card-title mb-0">{{ __('Welcome to Humano Help Center') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Welcome to the Humano application help center. Here you will find comprehensive documentation to help you make the most of our platform.') }}</p>

                            <div class="alert alert-primary mb-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-book me-2"></i>
                                    {{ __('User Manual') }}
                                </h6>
                                <p class="mb-0">{{ __('For a non-technical guide to what you can do in the platform (contacts, projects, tasks, billing, etc.), see the') }} <a href="{{ route('manual.index') }}" class="alert-link">{{ __('User Manual') }}</a>.</p>
                            </div>

                            <div class="card border-success mb-4">
                                <div class="card-header d-flex align-items-center">
                                    <i class="ti ti-rocket text-success me-2"></i>
                                    <h5 class="card-title mb-0">{{ __('Onboarding: registration and first steps') }}</h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3">{{ __('Onboarding help center intro') }}</p>
                                    <ol class="mb-0 ps-3">
                                        <li class="mb-3">
                                            <strong>{{ __('Onboarding step 1 title') }}</strong>
                                            <p class="mb-0 text-muted">{!! __('Onboarding step 1 body_html', ['url' => e(route('register'))]) !!}</p>
                                        </li>
                                        <li class="mb-3">
                                            <strong>{{ __('Onboarding step 2 title') }}</strong>
                                            <p class="mb-0 text-muted">{{ __('Onboarding step 2 body') }}</p>
                                        </li>
                                        @if ($showOnboardingRegistrationPaymentStep ?? false)
                                            <li class="mb-3">
                                                <strong>{{ __('Onboarding step 3 title') }}</strong>
                                                <p class="mb-0 text-muted">{!! __('Onboarding step 3 body_html', ['url' => e(route('registration.billing'))]) !!}</p>
                                            </li>
                                        @endif
                                        <li class="mb-3">
                                            <strong>{{ __('Onboarding step 4 title') }}</strong>
                                            <p class="mb-0 text-muted">{{ __('Onboarding step 4 body') }}</p>
                                        </li>
                                        <li class="mb-0">
                                            <strong>{{ __('Onboarding step 5 title') }}</strong>
                                            <p class="mb-0 text-muted">{{ __('Onboarding step 5 body') }}</p>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <i class="ti ti-users display-4 text-primary mb-3"></i>
                                            <h5 class="card-title">{{ __('Contact Management') }}</h5>
                                            <p class="card-text">{{ __('Learn how to manage your contacts, import data, and organize your customer relationships.') }}</p>
                                            <a href="{{ route('help.contacts') }}" class="btn btn-primary">{{ __('Get Started') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-info">
                                        <div class="card-body text-center">
                                            <i class="ti ti-api display-4 text-info mb-3"></i>
                                            <h5 class="card-title">{{ __('API Integration') }}</h5>
                                            <p class="card-text">{{ __('Integrate Humano with your systems using our REST API. Complete documentation and examples.') }}</p>
                                            <a href="{{ route('help.api') }}" class="btn btn-info">{{ __('View API Docs') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-warning">
                                        <div class="card-body text-center">
                                            <i class="ti ti-message-chatbot display-4 text-warning mb-3"></i>
                                            <h5 class="card-title">{{ __('Chat and Assistant') }}</h5>
                                            <p class="card-text">{{ __('Assistant chat, team flow prompts (routing keys), sidebar settings, and keyword vs AI flow selection.') }}</p>
                                            <a href="{{ route('help.chat-assistant') }}" class="btn btn-warning">{{ __('View documentation') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-secondary">
                                        <div class="card-body text-center">
                                            <i class="ti ti-settings display-4 text-secondary mb-3"></i>
                                            <h5 class="card-title">{{ __('Variables de Entorno') }}</h5>
                                            <p class="card-text">{{ __('Documentación de configuraciones por equipo: Google Analytics, Stripe, Email, Twilio y más.') }}</p>
                                            <a href="{{ route('help.environment-variables') }}" class="btn btn-secondary">{{ __('Ver configuraciones') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-danger">
                                        <div class="card-body text-center">
                                            <i class="ti ti-database display-4 text-danger mb-3"></i>
                                            <h5 class="card-title">{{ __('PostgreSQL: búsqueda y unaccent') }}</h5>
                                            <p class="card-text">{{ __('Extensión unaccent, comprobación y notas para Ubuntu. Relacionado con SearchNormalizer.') }}</p>
                                            <a href="{{ route('help.postgresql-search-unaccent') }}" class="btn btn-danger">{{ __('Ver documentación') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-dark">
                                        <div class="card-body text-center">
                                            <i class="ti ti-share display-4 text-body mb-3"></i>
                                            <h5 class="card-title">{{ __('Team social networks') }}</h5>
                                            <p class="card-text">{{ __('Meta / LinkedIn app keys in .env vs per-team OAuth tokens. Where admins connect accounts.') }}</p>
                                            <a href="{{ route('help.team-social-networks') }}" class="btn btn-dark">{{ __('View documentation') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <i class="ti ti-brand-wordpress display-4 text-success mb-3"></i>
                                            <h5 class="card-title">{{ __('WooCommerce') }}</h5>
                                            <p class="card-text">{{ __('Configure the connection with your WooCommerce store to manage products and orders from Humano.') }}</p>
                                            <a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-success">{{ __('View instructivo') }}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <i class="ti ti-brand-stripe display-4 text-primary mb-3"></i>
                                            <h5 class="card-title">{{ __('Stripe webhooks') }}</h5>
                                            <p class="card-text">{{ __('Stripe webhooks help card body') }}</p>
                                            <a href="{{ route('help.stripe-webhook') }}" class="btn btn-primary">{{ __('View documentation') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="cursor-mcp-setup" class="card border-primary mb-4 mt-4">
                                <div class="card-header d-flex align-items-center">
                                    <i class="ti ti-plug-connected text-primary me-2"></i>
                                    <h5 class="card-title mb-0">{{ __('Cursor MCP setup') }}</h5>
                                </div>
                                <div class="card-body">
                                    <p class="lead mb-3">{{ __('Help section MCP Cursor lead') }}</p>
                                    <p class="mb-3">{{ __('Help section MCP Cursor body') }}</p>
                                    <h6 class="mt-4 mb-2">{{ __('Install MCP in Cursor:') }}</h6>
                                    <ol class="mb-3 ps-3">
                                        <li class="mb-2">{!! __('Open Cursor MCP config file step') !!}</li>
                                        <li>{!! __('Add MCP server entry step') !!}</li>
                                    </ol>
<pre class="language-json mb-0"><code>{
  "idoneo-mcp": {
    "url": "https://mcp.idoneo.dev/mcp"
  }
}</code></pre>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-info-circle me-2"></i>
                                    {{ __('Getting Started') }}
                                </h6>
                                <p class="mb-0">{{ __('If you\'re new to Humano, we recommend starting with our "How to Use" guide to familiarize yourself with the platform.') }}</p>
                                <a href="{{ route('help.usage') }}" class="alert-link">{{ __('Read the guide →') }}</a>
                            </div>

                        </div>
                    </div>
            </div>
        </div>
    </div>
@endsection
