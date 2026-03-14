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
                                            <p class="card-text">{{ __('How the assistant chat works and its relation with the terminal command php artisan chat:simulate.') }}</p>
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
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <i class="ti ti-brand-wordpress display-4 text-success mb-3"></i>
                                            <h5 class="card-title">{{ __('WooCommerce') }}</h5>
                                            <p class="card-text">{{ __('Configure the connection with your WooCommerce store to manage products and orders from Humano.') }}</p>
                                            <a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-success">{{ __('View instructivo') }}</a>
                                        </div>
                                    </div>
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
