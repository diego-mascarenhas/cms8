@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'No Autorizado')

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-misc.css')}}">
@endsection


@section('content')
<!-- Not Authorized -->
<div class="misc-wrapper text-center">
  <h2 class="mb-1 mx-2">
    @if (session('unauthorized_message'))
      {{ __('Módulo no disponible') }}
    @else
      ¡No estás autorizado!
    @endif
  </h2>
  <p class="mb-4 mx-2">
    @if (session('unauthorized_message'))
      {{ session('unauthorized_message') }}
    @else
      No tienes permisos para acceder a esta página con las credenciales que has proporcionado.<br>Por favor, contacta al administrador del sistema.
    @endif
  </p>
  <a href="{{url('/dashboard')}}" class="btn btn-primary mb-4">Volver al inicio</a>
  <div class="mt-4">
    <img src="{{ asset('assets/img/illustrations/page-misc-you-are-not-authorized.png') }}" alt="page-misc-not-authorized" width="170" class="img-fluid">
  </div>
</div>
<div class="container-fluid misc-bg-wrapper">
  <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="page-misc-not-authorized" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
</div>
<!-- /Not Authorized -->
@endsection
