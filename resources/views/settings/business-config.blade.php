@extends('layouts/layoutMaster')

@section('title', __('Business Configuration'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/form-wizard-icons.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Settings') }}/</span> {{ __('Business Configuration') }}</h4>
        <p class="text-muted">{{ __('Configure your business details step by step') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> {{ __('Back to Settings') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
            <div class="bs-stepper-header">
                <div class="step" data-target="#account-details-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-account.svg#wizardAccount') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">{{ __('Account Details') }}</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#personal-info-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 58 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">{{ __('Personal Info') }}</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#address-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-address.svg#wizardAddress') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">{{ __('Address') }}</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#social-links-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-social-link.svg#wizardSocialLink') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">{{ __('Social Links') }}</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#review-submit-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-submit.svg#wizardSubmit') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">{{ __('Review & Submit') }}</span>
                    </button>
                </div>
            </div>
            <div class="bs-stepper-content">
                <form onSubmit="return false">
                    <!-- Account Details -->
                    <div id="account-details-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">{{ __('Account Details') }}</h6>
                            <small>{{ __('Enter Your Account Details.') }}</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="username-modern">{{ __('Username') }}</label>
                                <input type="text" id="username-modern" class="form-control" placeholder="johndoe" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="email-modern">{{ __('Email') }}</label>
                                <input type="email" id="email-modern" class="form-control" placeholder="john.doe@email.com" aria-label="john.doe" />
                            </div>
                            <div class="col-sm-6 form-password-toggle">
                                <label class="form-label" for="password-modern">{{ __('Password') }}</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password-modern" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password2-modern" />
                                    <span class="input-group-text cursor-pointer" id="password2-modern"><i class="ti ti-eye-off"></i></span>
                                </div>
                            </div>
                            <div class="col-sm-6 form-password-toggle">
                                <label class="form-label" for="confirm-password-modern">{{ __('Confirm Password') }}</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="confirm-password-modern" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="confirm-password2-modern" />
                                    <span class="input-group-text cursor-pointer" id="confirm-password2-modern"><i class="ti ti-eye-off"></i></span>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev" disabled> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">{{ __('Previous') }}</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">{{ __('Next') }}</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Personal Info -->
                    <div id="personal-info-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">{{ __('Personal Info') }}</h6>
                            <small>{{ __('Enter Your Personal Info.') }}</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="first-name-modern">{{ __('First Name') }}</label>
                                <input type="text" id="first-name-modern" class="form-control" placeholder="John" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="last-name-modern">{{ __('Last Name') }}</label>
                                <input type="text" id="last-name-modern" class="form-control" placeholder="Doe" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="country-modern">{{ __('Country') }}</label>
                                <select class="select2" id="country-modern">
                                    <option label=" "></option>
                                    <option>UK</option>
                                    <option>USA</option>
                                    <option>Spain</option>
                                    <option>France</option>
                                    <option>Italy</option>
                                    <option>Australia</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="language-modern">{{ __('Language') }}</label>
                                <select class="selectpicker w-auto" id="language-modern" data-style="btn-transparent" data-icon-base="ti" data-tick-icon="ti-check text-white" multiple>
                                    <option>English</option>
                                    <option>French</option>
                                    <option>Spanish</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">{{ __('Previous') }}</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">{{ __('Next') }}</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Address -->
                    <div id="address-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">{{ __('Address') }}</h6>
                            <small>{{ __('Enter Your Address.') }}</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="address-modern-input">{{ __('Address') }}</label>
                                <input type="text" class="form-control" id="address-modern-input" placeholder="98 Borough bridge Road, Birmingham">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="landmark-modern">{{ __('Landmark') }}</label>
                                <input type="text" class="form-control" id="landmark-modern" placeholder="Borough bridge">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="pincode-modern">{{ __('Pincode') }}</label>
                                <input type="text" class="form-control" id="pincode-modern" placeholder="658921">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="city-modern">{{ __('City') }}</label>
                                <input type="text" class="form-control" id="city-modern" placeholder="Birmingham">
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">{{ __('Previous') }}</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">{{ __('Next') }}</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Social Links -->
                    <div id="social-links-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">{{ __('Social Links') }}</h6>
                            <small>{{ __('Enter Your Social Links.') }}</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="twitter-modern">Twitter</label>
                                <input type="text" id="twitter-modern" class="form-control" placeholder="https://twitter.com/abc" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="facebook-modern">Facebook</label>
                                <input type="text" id="facebook-modern" class="form-control" placeholder="https://facebook.com/abc" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="google-modern">Google+</label>
                                <input type="text" id="google-modern" class="form-control" placeholder="https://plus.google.com/abc" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="linkedin-modern">LinkedIn</label>
                                <input type="text" id="linkedin-modern" class="form-control" placeholder="https://linkedin.com/abc" />
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">{{ __('Previous') }}</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">{{ __('Next') }}</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Review -->
                    <div id="review-submit-modern" class="content">
                        <p class="fw-medium mb-2">{{ __('Account') }}</p>
                        <ul class="list-unstyled">
                            <li>{{ __('Username') }}</li>
                            <li>exampl@email.com</li>
                        </ul>
                        <hr>
                        <p class="fw-medium mb-2">{{ __('Personal Info') }}</p>
                        <ul class="list-unstyled">
                            <li>{{ __('First Name') }}</li>
                            <li>{{ __('Last Name') }}</li>
                            <li>{{ __('Country') }}</li>
                            <li>{{ __('Language') }}</li>
                        </ul>
                        <hr>
                        <p class="fw-medium mb-2">{{ __('Address') }}</p>
                        <ul class="list-unstyled">
                            <li>{{ __('Address') }}</li>
                            <li>{{ __('Landmark') }}</li>
                            <li>{{ __('Pincode') }}</li>
                            <li>{{ __('City') }}</li>
                        </ul>
                        <hr>
                        <p class="fw-medium mb-2">{{ __('Social Links') }}</p>
                        <ul class="list-unstyled">
                            <li>https://twitter.com/abc</li>
                            <li>https://facebook.com/abc</li>
                            <li>https://plus.google.com/abc</li>
                            <li>https://linkedin.com/abc</li>
                        </ul>
                        <div class="col-12 d-flex justify-content-between">
                            <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                <span class="align-middle d-sm-inline-block d-none">{{ __('Previous') }}</span>
                            </button>
                            <button class="btn btn-success btn-submit">{{ __('Submit') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
