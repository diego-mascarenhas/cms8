@extends('layouts/layoutMaster')

@section('title', $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">{{ $collaborator->name }}</h4>
                <div>
                    <a href="{{ route('collaborator.edit', $collaborator) }}" class="btn btn-primary waves-effect">
                        <i class="ti ti-edit me-1"></i>
                        {{ __('Edit') }}
                    </a>
                    <a href="{{ route('collaborator-list') }}" class="btn btn-label-secondary waves-effect">
                        <i class="ti ti-arrow-left me-1"></i>
                        {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">{{ __('Contact Information') }}</h5>
                    <dl class="row">
                        <dt class="col-sm-4">{{ __('Email') }}</dt>
                        <dd class="col-sm-8">{{ $collaborator->email }}</dd>

                        <dt class="col-sm-4">{{ __('Phone') }}</dt>
                        <dd class="col-sm-8">{{ $collaborator->phone }}</dd>

                        <dt class="col-sm-4">{{ __('Enterprise') }}</dt>
                        <dd class="col-sm-8">{{ $collaborator->enterprise->name ?? '-' }}</dd>

                        <dt class="col-sm-4">{{ __('Collaborator') }}</dt>
                        <dd class="col-sm-8">{{ $collaborator->responsible->name ?? '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3">{{ __('Categories') }}</h5>
                    <div class="mb-3">
                        @foreach($collaborator->categories as $category)
                            <span class="badge bg-label-primary me-1">{{ $category->name }}</span>
                        @endforeach
                    </div>

                    <h5 class="mb-3">{{ __('Social Networks') }}</h5>
                    <div class="mb-3">
                        @if($collaborator->facebook)
                            <a href="{{ $collaborator->facebook }}" target="_blank" class="btn btn-icon btn-outline-primary me-2">
                                <i class="ti ti-brand-facebook"></i>
                            </a>
                        @endif
                        @if($collaborator->instagram)
                            <a href="{{ $collaborator->instagram }}" target="_blank" class="btn btn-icon btn-outline-primary me-2">
                                <i class="ti ti-brand-instagram"></i>
                            </a>
                        @endif
                        @if($collaborator->linkedin)
                            <a href="{{ $collaborator->linkedin }}" target="_blank" class="btn btn-icon btn-outline-primary me-2">
                                <i class="ti ti-brand-linkedin"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection 