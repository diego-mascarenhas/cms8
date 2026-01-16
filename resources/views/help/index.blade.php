@extends('layouts/layoutHelpSimple')

@section('title', __('Help & Documentation'))

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
                        <a class="nav-link {{ request()->routeIs('help.index') ? 'active' : '' }}" href="{{ route('help.index') }}">
                            <i class="ti ti-home me-2"></i>
                            {{ __('Introduction') }}
                        </a>
                        <a class="nav-link {{ request()->routeIs('help.usage') ? 'active' : '' }}" href="{{ route('help.usage') }}">
                            <i class="ti ti-book me-2"></i>
                            {{ __('How to Use') }}
                        </a>

                        <div class="nav-divider my-2"></div>
                        <h6 class="text-muted mb-2">{{ __('Modules') }}</h6>

                        <a class="nav-link {{ request()->routeIs('help.contacts') ? 'active' : '' }}" href="{{ route('help.contacts') }}">
                            <i class="ti ti-users me-2"></i>
                            {{ __('Contact Management') }}
                        </a>

                        <div class="nav-divider my-2"></div>
                        <h6 class="text-muted mb-2">{{ __('API Documentation') }}</h6>

                        <a class="nav-link {{ request()->routeIs('help.api') ? 'active' : '' }}" href="{{ route('help.api') }}">
                            <i class="ti ti-api me-2"></i>
                            {{ __('API Overview') }}
                        </a>
                        <a class="nav-link {{ request()->routeIs('help.api.authentication') ? 'active' : '' }}" href="{{ route('help.api.authentication') }}">
                            <i class="ti ti-key me-2"></i>
                            {{ __('Authentication') }}
                        </a>
                        <a class="nav-link {{ request()->routeIs('help.api.contacts') ? 'active' : '' }}" href="{{ route('help.api.contacts') }}">
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
    </div>
</div>
@endsection