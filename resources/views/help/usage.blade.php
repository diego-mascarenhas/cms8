@extends('layouts/layoutHelpSimple')

@section('title', __('How to Use Humano'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('How to Use Humano') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('This Help center is for technical setup. Day-to-day product usage (what each role can do) lives in the User Manual.') }}</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-primary h-100">
                            <div class="card-body">
                                <h5>{{ __('User Manual') }}</h5>
                                <p>{{ __('Modules, Admin / Collaborator / Client roles, flow diagrams and form mockups.') }}</p>
                                <a href="{{ route('manual.index') }}" class="btn btn-primary btn-sm">{{ __('Open manual') }}</a>
                                <a href="{{ route('mockups.overview') }}" class="btn btn-label-primary btn-sm">{{ __('Flow diagrams') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-info h-100">
                            <div class="card-body">
                                <h5>{{ __('Technical Help') }}</h5>
                                <p>{{ __('API, environment variables, Stripe webhooks, usage rates, WooCommerce, plugins, Paid Ads OAuth, SPF/DNS.') }}</p>
                                <a href="{{ route('help.index') }}" class="btn btn-info btn-sm">{{ __('Help home') }}</a>
                                <a href="{{ route('help.api') }}" class="btn btn-label-info btn-sm">{{ __('API docs') }}</a>
                                <a href="{{ route('help.team-billing') }}" class="btn btn-label-info btn-sm">{{ __('help_team_billing.sidebar_title') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>{{ __('Roles at a glance') }}</h5>
                <ul>
                    <li><strong>Admin</strong> — {{ __('Team setup, users, billing, automations, infrastructure.') }}</li>
                    <li><strong>Collaborator</strong> — {{ __('CRM operations, tasks, chat; no billing menu.') }}</li>
                    <li><strong>Client</strong> — {{ __('End-user portal access: own projects/services, tickets, budget links.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Recommended path') }}</h5>
                <ol>
                    <li><a href="{{ route('help.onboarding') }}">{{ __('Post-payment onboarding') }}</a></li>
                    <li><a href="{{ route('manual.getting-started') }}">{{ __('Manual: primeros pasos') }}</a></li>
                    <li><a href="{{ route('mockups.roles-flow') }}">{{ __('Mockups: flujo por roles') }}</a></li>
                    <li><a href="{{ route('help.environment-variables') }}">{{ __('Team configuration / env') }}</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
