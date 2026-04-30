@extends('layouts/layoutMaster')

@section('title', __('Edit Campaign'))

@php
    $timezones = [
        'UTC' => '(GMT+0:00) UTC',
        'Europe/Madrid' => '(GMT+2:00) Madrid',
        'Europe/London' => '(GMT+1:00) London',
        'America/New_York' => '(GMT-4:00) America/New_York',
        'America/Chicago' => '(GMT-5:00) America/Chicago',
        'America/Los_Angeles' => '(GMT-7:00) America/Los_Angeles',
    ];
@endphp

@section('content')
<form action="#" method="POST">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Email Sequence Settings') }}</h4>
            <p class="text-muted">{{ __('Edit and configure the selected campaign sequence.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Email Sequence Details') }}</h5>
            <p class="text-muted mb-0">{{ __('Edit email sequence details.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="internal-title">{{ __('Internal Title') }}</label>
                    <input
                        id="internal-title"
                        name="title"
                        type="text"
                        class="form-control mb-2"
                        value="{{ str_replace('-', ' ', $campaign) }}"
                    />
                    <small class="text-muted">
                        {{ __('This title is internal for reports and is not shown to recipients.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Email Sequence Exclude') }}</h5>
            <p class="text-muted mb-0">{{ __('Stop emailing subscribers when one of these rules is met.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="exclude-offers">{{ __("Don't email subscribers who purchased these offers") }}</label>
                        <select id="exclude-offers" class="form-select" multiple>
                            <option>{{ __('Annual Plan') }}</option>
                            <option>{{ __('Premium Course') }}</option>
                            <option>{{ __('Coaching Pack') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="exclude-forms">{{ __("Don't email subscribers who submitted these forms") }}</label>
                        <select id="exclude-forms" class="form-select" multiple>
                            <option>{{ __('Webinar Registration') }}</option>
                            <option>{{ __('Upsell Checkout') }}</option>
                            <option>{{ __('Feedback Form') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Sending Time') }}</h5>
            <p class="text-muted mb-0">{{ __('Configure the default time zone used by this sequence.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="send-time-zone">{{ __('Default time zone') }}</label>
                    <select id="send-time-zone" name="send_time_zone" class="form-select">
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'Europe/Madrid')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Automations') }}</h5>
            <p class="text-muted mb-0">{{ __('Configure automations for this email sequence.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ __('Automations') }}</h5>
                    <a href="https://help.kajabi.com/hc/en-us/articles/360036990514" target="_blank" rel="noopener noreferrer" class="text-muted">
                        <i class="ti ti-help-circle"></i>
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        {{ __('Automations help you set repeating tasks and streamline your workflow with a few clicks.') }}
                    </p>
                    <a href="#" class="btn btn-label-primary">
                        <i class="ti ti-plus me-1"></i>{{ __('Add Automation') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4" />

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
    </div>
</form>
@endsection
