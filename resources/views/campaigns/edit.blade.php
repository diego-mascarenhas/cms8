@extends('layouts/layoutMaster')

@section('title', __('Editar campaña'))

@section('page-script')
<script>
    $(function ()
    {
        var select2Common = function ($el)
        {
            var placeholder = $el.data('placeholder') || '';

            return {
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $el.parent(),
                multiple: true,
                closeOnSelect: false,
                width: '100%'
            };
        };

        $('#exclude-offers').each(function ()
        {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2(select2Common($this));
        });

        $('#exclude-forms').each(function ()
        {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2(select2Common($this));
        });
    });
</script>
@endsection

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
@if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
<form action="{{ route('campaigns.update', $campaign) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Configuración de secuencia de correo') }}</h4>
            <p class="text-muted">{{ __('Edita y configura la secuencia de campaña seleccionada.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('campaigns.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Volver') }}
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Detalles de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Edita los detalles de la secuencia de correos.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="internal-title">{{ __('Nombre de la campaña') }}</label>
                    <input
                        id="internal-title"
                        name="title"
                        type="text"
                        class="form-control mb-2 @error('title') is-invalid @enderror"
                        value="{{ old('title', $campaign->name) }}"
                    />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">
                        {{ __('Este nombre es solo para tu panel y reportes; no lo ven los destinatarios.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Exclusiones de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Deja de enviar correos cuando se cumpla una de estas reglas.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <input type="hidden" name="sequence_exclusions_present" value="1">
                    <div class="mb-3">
                        <label class="form-label" for="exclude-offers">{{ __('No enviar correos a suscriptores que compraron estas ofertas') }}</label>
                        <select
                            id="exclude-offers"
                            name="exclude_offer_refs[]"
                            class="form-control select2 @error('exclude_offer_refs') is-invalid @enderror"
                            multiple
                            data-placeholder="{{ __('Selecciona productos, planes o suscripciones') }}"
                            data-allow-clear="true"
                        >
                            @if ($catalogProducts->isNotEmpty())
                                <optgroup label="{{ __('Productos') }}">
                                    @foreach ($catalogProducts as $product)
                                        <option
                                            value="product:{{ $product->id }}"
                                            @selected(in_array('product:'.$product->id, $selectedOfferRefs, true))
                                        >{{ $product->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if ($subscriptionProducts->isNotEmpty())
                                <optgroup label="{{ __('Suscripciones') }}">
                                    @foreach ($subscriptionProducts as $subscriptionProduct)
                                        <option
                                            value="subscription:{{ $subscriptionProduct->id }}"
                                            @selected(in_array('subscription:'.$subscriptionProduct->id, $selectedOfferRefs, true))
                                        >{{ $subscriptionProduct->name }}@if ($subscriptionProduct->recurring_interval) ({{ $subscriptionProduct->recurring_interval }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        @error('exclude_offer_refs')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if ($catalogProducts->isEmpty() && $subscriptionProducts->isEmpty())
                            <small class="text-muted">{{ __('No hay productos ni planes activos en el catálogo.') }}</small>
                        @endif
                    </div>
                    <div>
                        <label class="form-label" for="exclude-forms">{{ __('No enviar correos a suscriptores que completaron estos formularios') }}</label>
                        <select
                            id="exclude-forms"
                            name="exclude_content_ids[]"
                            class="form-control select2 @error('exclude_content_ids') is-invalid @enderror"
                            multiple
                            data-placeholder="{{ __('Selecciona contenidos o formularios') }}"
                            data-allow-clear="true"
                        >
                            @foreach ($formContentsForSelect as $row)
                                <option
                                    value="{{ $row['id'] }}"
                                    @selected(in_array((int) $row['id'], $selectedContentIds, true))
                                >{{ $row['label'] }}</option>
                            @endforeach
                        </select>
                        @error('exclude_content_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if ($formContentsForSelect->isEmpty())
                            <small class="text-muted">{{ __('No hay contenidos publicados disponibles.') }}</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Horario de envío') }}</h5>
            <p class="text-muted mb-0">{{ __('Configura la zona horaria predeterminada usada por esta secuencia.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="send-time-zone">{{ __('Zona horaria predeterminada') }}</label>
                    <select id="send-time-zone" name="send_time_zone" class="form-select @error('send_time_zone') is-invalid @enderror">
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected($value === $storedTimezone)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('send_time_zone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8 offset-lg-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Guardar') }}</button>
                <button type="button" class="btn btn-label-secondary waves-effect waves-light" onclick="location.href='{{ route('campaigns.index') }}'">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</form>
@endsection
