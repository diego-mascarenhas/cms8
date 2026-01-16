@extends('layouts/layoutHelpSimple')

@section('title', __('Contact Management'))

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

                        <a class="nav-link active" href="{{ route('help.contacts') }}">
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
                        <a class="nav-link" href="{{ route('help.api.contacts') }}">
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
                    <h4 class="card-title mb-0">{{ __('Contact Management') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Learn how to effectively manage your contacts and customer relationships in Humano.') }}</p>

                            <h5 class="mt-4">{{ __('Contact Overview') }}</h5>
                            <p>{{ __('Contacts in Humano represent your customers, clients, leads, and business relationships. Each contact can have multiple pieces of information including personal details, company information, and communication history.') }}</p>

                            <h6 class="mt-4">{{ __('1. Creating Contacts') }}</h6>
                            <p>{{ __('You can create contacts manually or import them from external sources:') }}</p>
                            <ul>
                                <li><strong>{{ __('Manual Creation') }}</strong>: {{ __('Use the "Create Contact" button to add individual contacts with all their details') }}</li>
                                <li><strong>{{ __('Bulk Import') }}</strong>: {{ __('Import contacts from CSV files or external systems') }}</li>
                                <li><strong>{{ __('API Integration') }}</strong>: {{ __('Use our API to create contacts programmatically') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('2. Contact Information') }}</h6>
                            <p>{{ __('Each contact can store the following information:') }}</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>{{ __('Personal Information') }}</h6>
                                    <ul>
                                        <li>{{ __('Name and surname') }}</li>
                                        <li>{{ __('Email address') }}</li>
                                        <li>{{ __('Phone numbers') }}</li>
                                        <li>{{ __('Date of birth') }}</li>
                                        <li>{{ __('Profile photo') }}</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>{{ __('Business Information') }}</h6>
                                    <ul>
                                        <li>{{ __('Company name') }}</li>
                                        <li>{{ __('Job title') }}</li>
                                        <li>{{ __('Industry') }}</li>
                                        <li>{{ __('Contact preferences') }}</li>
                                        <li>{{ __('Tags and categories') }}</li>
                                    </ul>
                                </div>
                            </div>

                            <h6 class="mt-4">{{ __('3. Contact Categories') }}</h6>
                            <p>{{ __('Organize your contacts using categories:') }}</p>
                            <ul>
                                <li><strong>{{ __('Customers') }}</strong>: {{ __('Active paying customers') }}</li>
                                <li><strong>{{ __('Leads') }}</strong>: {{ __('Potential customers') }}</li>
                                <li><strong>{{ __('Prospects') }}</strong>: {{ __('Contacts in early stages') }}</li>
                                <li><strong>{{ __('Partners') }}</strong>: {{ __('Business partners and suppliers') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('4. Contact Actions') }}</h6>
                            <p>{{ __('For each contact you can perform various actions:') }}</p>
                            <ul>
                                <li><strong>{{ __('Communication') }}</strong>: {{ __('Send emails, SMS, or WhatsApp messages') }}</li>
                                <li><strong>{{ __('Task Creation') }}</strong>: {{ __('Create tasks related to the contact') }}</li>
                                <li><strong>{{ __('Project Assignment') }}</strong>: {{ __('Link contacts to projects') }}</li>
                                <li><strong>{{ __('Note Taking') }}</strong>: {{ __('Add internal notes and comments') }}</li>
                                <li><strong>{{ __('File Attachments') }}</strong>: {{ __('Attach documents and files') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('5. Contact Search and Filtering') }}</h6>
                            <p>{{ __('Find contacts quickly using the search and filter features:') }}</p>
                            <ul>
                                <li>{{ __('Search by name, email, or company') }}</li>
                                <li>{{ __('Filter by category, status, or tags') }}</li>
                                <li>{{ __('Advanced filters for custom fields') }}</li>
                                <li>{{ __('Quick access through the global search') }}</li>
                            </ul>

                            <h6 class="mt-4">{{ __('6. Contact Import') }}</h6>
                            <p>{{ __('Import contacts from external sources:') }}</p>
                            <ul>
                                <li>{{ __('CSV file import with field mapping') }}</li>
                                <li>{{ __('Excel file support') }}</li>
                                <li>{{ __('Duplicate detection and merging') }}</li>
                                <li>{{ __('Import history and error reporting') }}</li>
                            </ul>

                            <div class="alert alert-success mt-4" role="alert">
                                <h6 class="alert-heading mb-2">
                                    <i class="ti ti-check-circle me-2"></i>
                                    {{ __('Best Practices') }}
                                </h6>
                                <ul class="mb-0">
                                    <li>{{ __('Keep contact information up to date') }}</li>
                                    <li>{{ __('Use consistent naming conventions') }}</li>
                                    <li>{{ __('Regularly clean up inactive contacts') }}</li>
                                    <li>{{ __('Categorize contacts appropriately') }}</li>
                                    <li>{{ __('Add notes for important interactions') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection