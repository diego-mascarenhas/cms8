@extends('layouts/layoutMaster')

@section('title', __('Style Book Details'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}">
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Style Books') }}/</span> {{ $stylebook->name }}</h4>
        <p class="text-muted">{{ __('Style Book Details') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('stylebook.edit')
            <a href="{{ route('stylebook.edit', $stylebook->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
            </a>
        @endcan
        <a href="{{ route('stylebook.index') }}" class="btn btn-label-secondary waves-effect">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Style Book Information') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="fw-semibold">{{ __('Name') }}</h6>
                            <p>{{ $stylebook->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h6 class="fw-semibold">{{ __('Language') }}</h6>
                            <p>
                                @php
                                    $languageName = $stylebook->languageRelation ? $stylebook->languageRelation->name : strtoupper($stylebook->language);
                                    $countryCode = \App\Helpers\Helpers::getLanguageFlag($stylebook->language);
                                @endphp
                                <span class="fi fi-{{ strtolower($countryCode) }} me-2"></span>
                                {{ $languageName }}
                            </p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <h6 class="fw-semibold">{{ __('Date') }}</h6>
                            <p>{{ $stylebook->date ? $stylebook->date->format('d/m/Y') : '' }}</p>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <h6 class="fw-semibold">{{ __('File') }}</h6>
                            <a href="{{ asset('storage/' . $stylebook->file) }}" target="_blank" class="btn btn-label-primary">
                                <i class="ti ti-file-download me-1"></i>{{ __('Download File') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 