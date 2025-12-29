@extends('layouts/layoutMaster')

@section('title', __('Información de Facturación'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Información de Facturación') }}</h4>
        <p class="text-muted">{{ __('Complete sus datos para proceder con el pago') }}</p>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7 col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ __('Datos de Facturación') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('subscription.save-billing-info') }}" method="POST">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $plan }}">

                    <div class="row g-3">
                        <!-- Individual Name -->
                        <div class="col-md-6">
                            <label class="form-label" for="individual_name">{{ __('Nombre Completo') }} (*)</label>
                            <input type="text" 
                                class="form-control @error('individual_name') is-invalid @enderror" 
                                id="individual_name" 
                                name="individual_name" 
                                value="{{ old('individual_name', $customerData['individual_name']) }}" 
                                placeholder="Juan Pérez">
                            @error('individual_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Business Name -->
                        <div class="col-md-6">
                            <label class="form-label" for="business_name">{{ __('Razón Social') }}</label>
                            <input type="text" 
                                class="form-control @error('business_name') is-invalid @enderror" 
                                id="business_name" 
                                name="business_name" 
                                value="{{ old('business_name', $customerData['business_name']) }}" 
                                placeholder="Mi Empresa S.A.">
                            @error('business_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Opcional - Si no se completa, se usará el Nombre Completo') }}</small>
                        </div>

                        <!-- Country -->
                        <div class="col-md-6">
                            <label class="form-label" for="country">{{ __('País') }} (*)</label>
                            <select class="form-select @error('country') is-invalid @enderror" 
                                id="country" 
                                name="country">
                                <option value="">{{ __('Seleccionar país') }}</option>
                                <option value="AR" {{ old('country', $customerData['country']) == 'AR' ? 'selected' : '' }}>Argentina</option>
                                <option value="ES" {{ old('country', $customerData['country']) == 'ES' ? 'selected' : '' }}>España</option>
                                <option value="MX" {{ old('country', $customerData['country']) == 'MX' ? 'selected' : '' }}>México</option>
                                <option value="CL" {{ old('country', $customerData['country']) == 'CL' ? 'selected' : '' }}>Chile</option>
                                <option value="CO" {{ old('country', $customerData['country']) == 'CO' ? 'selected' : '' }}>Colombia</option>
                                <option value="PE" {{ old('country', $customerData['country']) == 'PE' ? 'selected' : '' }}>Perú</option>
                                <option value="UY" {{ old('country', $customerData['country']) == 'UY' ? 'selected' : '' }}>Uruguay</option>
                                <option value="US" {{ old('country', $customerData['country']) == 'US' ? 'selected' : '' }}>Estados Unidos</option>
                            </select>
                            @error('country')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label" for="phone">{{ __('WhatsApp') }} (*)</label>
                            <input type="text" 
                                class="form-control @error('phone') is-invalid @enderror" 
                                id="phone" 
                                name="phone" 
                                value="{{ old('phone', $customerData['phone']) }}" 
                                placeholder="9 11 0000-0000">
                            @error('phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tax ID -->
                        <div class="col-md-12">
                            <label class="form-label" for="tax_id">{{ __('Identificación Fiscal') }} (*)</label>
                            <input type="text" 
                                class="form-control @error('tax_id') is-invalid @enderror" 
                                id="tax_id" 
                                name="tax_id" 
                                value="{{ old('tax_id', $customerData['tax_id']) }}" 
                                placeholder="CUIT, CIF, NIF, RFC, etc.">
                            @error('tax_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Formato según su país: CUIT (Argentina), CIF/NIF (España), RFC (México), etc.') }}</small>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="ti ti-arrow-right me-1"></i>{{ __('Continuar al Pago') }}
                        </button>
                        <a href="{{ route('subscription.index') }}" class="btn btn-label-secondary">
                            {{ __('Cancelar') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Plan Summary -->
    <div class="col-xl-4 col-lg-5 col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ __('Resumen del Plan') }}</h5>
            </div>
            <div class="card-body">
                @php
                    $planDetails = [
                        'basic' => [
                            'name' => 'Basic',
                            'price' => $prices['basic']['amount'] ?? '15.99',
                            'currency' => $prices['basic']['currency'] ?? 'EUR',
                            'emails_month' => '10,000',
                            'emails_day' => '500',
                            'contacts' => '3,000',
                        ],
                        'foundation' => [
                            'name' => 'Foundation',
                            'price' => $prices['foundation']['amount'] ?? '35.99',
                            'currency' => $prices['foundation']['currency'] ?? 'EUR',
                            'emails_month' => '50,000',
                            'emails_day' => '2,000',
                            'contacts' => '20,000',
                        ],
                        'scale' => [
                            'name' => 'Scale',
                            'price' => $prices['scale']['amount'] ?? '119.99',
                            'currency' => $prices['scale']['currency'] ?? 'EUR',
                            'emails_month' => '100,000',
                            'emails_day' => 'Ilimitados',
                            'contacts' => '50,000',
                        ],
                    ];
                    $currentPlan = $planDetails[$plan] ?? $planDetails['basic'];
                @endphp

                <div class="mb-3">
                    <h4 class="mb-1">{{ $currentPlan['name'] }}</h4>
                    <div class="d-flex align-items-baseline">
                        <span class="h2 mb-0">{{ number_format($currentPlan['price'], 2) }} €</span>
                        <span class="text-muted ms-2">/mes</span>
                    </div>
                    <small class="text-muted">+ I.V.A.</small>
                </div>

                <hr>

                <div class="mb-3">
                    <h6 class="mb-3">{{ __('Características Incluidas') }}</h6>
                    
                    <div class="d-flex mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        <div>
                            <strong>{{ $currentPlan['emails_month'] }}</strong> emails por mes
                        </div>
                    </div>

                    <div class="d-flex mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        <div>
                            <strong>{{ $currentPlan['emails_day'] }}</strong> emails por día
                        </div>
                    </div>

                    <div class="d-flex mb-2">
                        <i class="ti ti-check text-success me-2"></i>
                        <div>
                            Hasta <strong>{{ $currentPlan['contacts'] }}</strong> contactos
                        </div>
                    </div>
                </div>

                <hr>

                <div class="alert alert-info mb-0">
                    <div class="d-flex">
                        <i class="ti ti-info-circle me-2"></i>
                        <div>
                            <small>{{ __('Facturación mensual. Puedes cancelar en cualquier momento.') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

