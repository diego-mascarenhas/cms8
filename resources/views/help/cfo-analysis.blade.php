@extends('layouts/layoutHelpSimple')

@section('title', __('help_cfo.page_title'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="ti ti-chart-bar text-primary ti-md" aria-hidden="true"></i>
                <h4 class="card-title mb-0">{{ __('help_cfo.title') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('help_cfo.intro') }}</p>

                <h5 class="mt-4">{{ __('help_cfo.where_heading') }}</h5>
                <p class="mb-0">{{ __('help_cfo.where_body') }}</p>

                <h5 class="mt-4" id="subsistence">{{ __('help_cfo.subsistence_title') }}</h5>
                <p>{{ __('help_cfo.subsistence_body') }}</p>
                <ul class="mb-0">
                    <li>{{ __('help_cfo.subsistence_calls') }}</li>
                    <li>{{ __('help_cfo.subsistence_marketing') }}</li>
                    <li>{{ __('help_cfo.subsistence_conversion') }}</li>
                    <li>{{ __('help_cfo.subsistence_salary') }}</li>
                </ul>

                <h5 class="mt-4" id="lectura">{{ __('help_cfo.reading_title') }}</h5>
                <p>{{ __('help_cfo.reading_body') }}</p>
                <h6 class="mt-3">{{ __('help_cfo.reads_heading') }}</h6>
                <ul class="mb-0">
                    <li>{{ __('help_cfo.reads_invoices') }}</li>
                    <li>{{ __('help_cfo.reads_leads') }}</li>
                    <li>{{ __('help_cfo.reads_organization') }}</li>
                    <li>{{ __('help_cfo.reads_analytics') }}</li>
                    <li>{{ __('help_cfo.reads_publications') }}</li>
                    <li>{{ __('help_cfo.reads_projects') }}</li>
                    <li>{{ __('help_cfo.reads_renewals') }}</li>
                </ul>

                <h5 class="mt-4" id="projection">{{ __('help_cfo.projection_title') }}</h5>
                <p>{{ __('help_cfo.projection_body') }}</p>
                <ul class="mb-0">
                    <li>{{ __('help_cfo.projection_currency') }}</li>
                    <li>{{ __('help_cfo.projection_closed') }}</li>
                    <li>{{ __('help_cfo.projection_open') }}</li>
                    <li>{{ __('help_cfo.projection_finish') }}</li>
                    <li>{{ __('help_cfo.projection_plan') }}</li>
                    <li>{{ __('help_cfo.projection_renewal') }}</li>
                    <li>{{ __('help_cfo.projection_expense') }}</li>
                    <li>{{ __('help_cfo.projection_salary') }}</li>
                    <li>{{ __('help_cfo.projection_schedule') }}</li>
                    <li>{{ __('help_cfo.projection_empty') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
