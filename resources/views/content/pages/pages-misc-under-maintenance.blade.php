@php
	$customizerHidden = 'customizer-hide';
	$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', __('messages.under_maintenance'))

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-misc.css')}}">
@endsection

@section('content')
<!--Under Maintenance -->
<div class="container-xxl container-p-y">
	<div class="misc-wrapper">
		<h2 class="mb-1 mx-2">{{ __('messages.under_maintenance_title') }}</h2>
		<p class="mb-4 mx-2">
			{{ __('messages.under_maintenance_description') }}
		</p>
		<a href="{{url('/')}}" class="btn btn-primary mb-4">{{ __('messages.back_to_home') }}</a>
		<div class="mt-4">
			<img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}"
				alt="page-misc-under-maintenance" width="550" class="img-fluid">
		</div>
	</div>
</div>
<div class="container-fluid misc-bg-wrapper misc-under-maintenance-bg-wrapper">
	<img src="{{ asset('assets/img/illustrations/bg-shape-image-' . $configData['style'] . '.png') }}"
		alt="page-misc-under-maintenance" data-app-light-img="illustrations/bg-shape-image-light.png"
		data-app-dark-img="illustrations/bg-shape-image-dark.png">
</div>
<!-- /Under Maintenance -->
@endsection