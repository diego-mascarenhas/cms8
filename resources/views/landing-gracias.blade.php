@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', '¡Gracias!')

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
@endsection

@section('content')
<div class="container-xxl container-p-y">
    <div class="misc-wrapper text-center">
        <h2 class="mb-1 mx-2 text-black">¡Gracias!</h2>
        <p class="mb-4 mx-2 text-black">
            Nos pondremos en contacto contigo.
        </p>
        <a href="{{ url('/') }}" class="btn btn-primary mb-4">
            <i class="ti ti-arrow-left me-1"></i> Volver al Home
        </a>
        <div class="mt-4">
            <img src="{{ asset('assets/img/illustrations/page-misc-launching-soon.png') }}"
                alt="page-misc-launching-soon" width="263" class="img-fluid">
        </div>
    </div>
</div>
<div class="container-fluid misc-bg-wrapper">
    <img src="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
        alt="page-misc-coming-soon" data-app-light-img="illustrations/bg-shape-image-light.png"
        data-app-dark-img="illustrations/bg-shape-image-dark.png">
</div>
@endsection

{{-- Conversion tracking: dataLayer (GTM) + custom event for analytics --}}
@push('scripts')
<script>
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('conversion') === 'profundizar' || params.get('from') === 'landing-widget') {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: 'conversion',
        conversionType: 'profundizar',
        conversionFrom: 'landing-widget'
      });
      window.dispatchEvent(new CustomEvent('landingConversion', {
        detail: { type: 'profundizar', from: 'landing-widget' }
      }));
    }
  })();
</script>
@endpush
