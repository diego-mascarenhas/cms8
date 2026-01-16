@extends('layouts/layoutHelpSimple')

@section('title', __('How to Use Humano'))

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
                    <h4 class="card-title mb-0">{{ __('How to Use Humano') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h5>{{ __('Getting Started') }}</h5>
                            <p>{{ __('Humano is a comprehensive business management platform designed to help you manage your contacts, projects, tasks, and more. This guide will walk you through the basic usage of the platform.') }}</p>

                            <h6 class="mt-4">{{ __('1. Dashboard Overview') }}</h6>
                            <p>{{ __('The dashboard provides an overview of your business activities. You can see recent contacts, pending tasks, project status, and key metrics.') }}</p>

                            <h6 class="mt-4">{{ __('2. Navigation') }}</h6>
                            <p>{{ __('Use the sidebar menu to navigate between different sections of the application. Each section is organized by functionality:') }}</p>
                            <ul>
                                <li><strong>{{ __('Contacts') }}</strong>: {{ __('Manage your customer and contact database') }}</li>
                                <li><strong>{{ __('Projects') }}</strong>: {{ __('Track and manage your projects') }}</li>
                                <li><strong>{{ __('Tasks') }}</strong>: {{ __('Create and assign tasks to team members') }}</li>
                                <li><strong>{{ __('Services') }}</strong>: {{ __('Manage the services you offer') }}</li>
                                <li><strong>{{ __('Billing') }}</strong>: {{ __('Handle invoices, payments, and financials') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('3. User Management') }}</h6>
                            <p>{{ __('Humano supports multiple user roles within teams:') }}</p>
                            <ul>
                                <li><strong>{{ __('Root') }}</strong>: {{ __('System administrator with full access') }}</li>
                                <li><strong>{{ __('Admin') }}</strong>: {{ __('Team administrator') }}</li>
                                <li><strong>{{ __('Collaborator') }}</strong>: {{ __('Team member who can work on projects') }}</li>
                                <li><strong>{{ __('Employee') }}</strong>: {{ __('Internal employee') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('4. Team Management') }}</h6>
                            <p>{{ __('Each user belongs to a team. Teams allow you to organize your business data and control access to information. You can:') }}</p>
                            <ul>
                                <li>{{ __('Create multiple teams') }}</li>
                                <li>{{ __('Invite users to teams') }}</li>
                                <li>{{ __('Manage team settings and permissions') }}</li>
                                <li>{{ __('Switch between teams') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('5. API Integration') }}</h6>
                            <p>{{ __('Humano provides a REST API for integration with external systems. You can generate API tokens in your team settings to authenticate API requests.') }}</p>

                            <div class="alert alert-info mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-lightbulb me-2"></i>
                                    {{ __('Pro Tip') }}
                                </h6>
                                <p class="mb-0">{{ __('Start by exploring the Contacts section to understand how data is organized in Humano. This will give you a good foundation for using other modules.') }}</p>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
@endsection