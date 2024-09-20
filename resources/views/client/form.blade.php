@extends('layouts/layoutMaster')

@section('title', ' Clients')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bs-stepper/bs-stepper.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
<script src="{{asset('assets/js/cms-form-client.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Clientes/</span> {{ isset($data->id) ? 'Editar' : 'Crear' }}</h4>
        <p class="text-muted">Gestiona y personaliza a tus clientes</p>
    </div>
</div>

<!-- Modern -->
<div class="row">
  <!-- Modern Icons Wizard -->
  <div class="col-12 mb-4">
    <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
      <div class="bs-stepper-header">
        <div class="step" data-target="#account-details-modern">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 54 54">
                <use xlink:href="{{asset('assets/svg/icons/form-wizard-account.svg#wizardAccount')}}"></use>
              </svg>
            </span>
            <span class="bs-stepper-label">Detalle de la Empresa</span>
          </button>
        </div>
        <div class="line">
          <i class="ti ti-chevron-right"></i>
        </div>
        <div class="step" data-target="#personal-info-modern">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 58 54">
                <use xlink:href="{{asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal')}}"></use>
              </svg>
            </span>
            <span class="bs-stepper-label">Información Personal</span>
          </button>
        </div>
        <div class="line">
          <i class="ti ti-chevron-right"></i>
        </div>
        <div class="step" data-target="#address-modern">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 54 54">
                <use xlink:href="{{asset('assets/svg/icons/form-wizard-address.svg#wizardAddress')}}"></use>
              </svg>
            </span>
            <span class="bs-stepper-label">Domicilio</span>
          </button>
        </div>
        <div class="line">
          <i class="ti ti-chevron-right"></i>
        </div>
        <div class="step" data-target="#social-links-modern">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-icon">
              <svg viewBox="0 0 54 54">
                <use xlink:href="{{asset('assets/svg/icons/form-wizard-social-link.svg#wizardSocialLink')}}"></use>
              </svg>
            </span>
            <span class="bs-stepper-label">Redes Sociales</span>
          </button>
        </div>
      </div>
      <div class="bs-stepper-content">
		<form class="card-body" action="{{ route('client.store') }}" method="POST" onSubmit="return true">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">	
          <!-- Account Details -->
          <div id="account-details-modern" class="content">
            <div class="content-header mb-3">
              <h6 class="mb-0">Detalle de la Empresa</h6>
              <small>Datos de la empresa</small>
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
                <x-input-general id="usuario" label="Usuario (*)" value="{{ old('usuario', $data->usuario?? '') }}" />
              </div>
              <div class="col-sm-6 form-password-toggle">
                <label class="form-label" for="password-modern">Contraseña</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="password-modern" class="form-control" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password2-modern" />
                  <span class="input-group-text cursor-pointer" id="password2-modern"><i class="ti ti-eye-off"></i></span>
                </div>
              </div>
              <div class="col-sm-6">
                <x-input-general id="email" label="Email (*)" value="{{ old('email', $data->email?? '') }}" />
              </div>
			  <div class="col-sm-6">
                <x-input-general id="whatsapp" label="WhatsApp" value="{{ old('whatsapp', $data->whatsapp?? '') }}" />
              </div>
			  <div class="col-sm-6">
                <x-input-general id="phone" label="Teléfono" value="{{ old('phone', $data->phone?? '') }}" />
              </div>
              <div class="col-sm-6">
                <x-input-general id="website" label="Website" value="{{ old('website', $data->website?? '') }}" />
              </div>
			  <div class="col-12 d-flex">
			    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
			    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
              </div>
            </div>
          </div>
          <!-- Personal Info -->
          <div id="personal-info-modern" class="content">
            <div class="content-header mb-3">
              <h6 class="mb-0">Información Personal</h6>
              <small>Ingresa tu información personal</small>
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
                <x-input-general id="name" label="Nombre (*)" value="{{ old('name', $data->name?? '') }}" />
              </div>
             <div class="col-sm-6">
                  <x-enterprise-status-select 
                      :value="old('status_id', $data->status_id ?? '')"
                  />
              </div>
              <div class="col-sm-6">
                @php
					$paises = ['España', 'Francia', 'Italia', 'Portugal', 'Resto del mundo'];
				@endphp
				<x-input-select id="pais" label="País" :options="$paises" value="{{ old('pais', $data->pais?? '') }}" />
              </div>
              <div class="col-sm-6">
                @php
					$idiomas = ['Englés', 'Español', 'Francés', 'Italiano', 'Portugués'];
				@endphp
				<x-input-select id="idioma" label="Idioma" :options="$idiomas" value="{{ old('idioma', $data->idioma?? '') }}" />
              </div>
			  <div class="col-12 d-flex">
			    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
			    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
              </div>
            </div>
          </div>
          <!-- Address -->
          <div id="address-modern" class="content">
            <div class="content-header mb-3">
              <h6 class="mb-0">Domicilio</h6>
              <small>Ingresa tu domicilio</small>
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
				<x-input-general id="address" label="Dirección" value="{{ old('address', $data->address?? '') }}" />
              </div>
              <div class="col-sm-6">
                <x-input-general id="postal_code" label="Código Postal (*)" value="{{ old('postal_code', $data->postal_code?? '') }}" />
              </div>
              <div class="col-sm-6">
                <x-input-general id="locality" label="Población (*)" value="{{ old('locality', $data->locality?? '') }}" />
              </div>
              <div class="col-sm-6">
                <x-input-general id="province" label="Provincia (*)" value="{{ old('province', $data->province?? '') }}" />
              </div>
			  <div class="col-12 d-flex">
			    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
			    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
              </div>
            </div>
          </div>
          <!-- Social Links -->
          <div id="social-links-modern" class="content">
            <div class="content-header mb-3">
              <h6 class="mb-0">Redes Sociales</h6>
              <small>Ingresa el link de tus redes sociales</small>
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
				<x-input-general id="twitter" label="Twitter" value="{{ old('twitter', $data->twitter?? '') }}" />
              </div>
              <div class="col-sm-6">
				<x-input-general id="facebook" label="Facebook" value="{{ old('facebook', $data->facebook?? '') }}" />
              </div>
              <div class="col-sm-6">
				<x-input-general id="google" label="Google+" value="{{ old('google', $data->google?? '') }}" />
              </div>
              <div class="col-sm-6">
				<x-input-general id="linkedin" label="Linkedin" value="{{ old('linkedin', $data->linkedin?? '') }}" />
              </div>
              <div class="col-12 d-flex">
			    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
			    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('client-list') }}'">Cancelar</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Modern Icons Wizard -->
</div>
@endsection