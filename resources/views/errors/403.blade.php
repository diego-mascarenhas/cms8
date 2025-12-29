<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-menu-fixed customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets/') }}" data-base-url="{{ url('/') }}" data-framework="laravel" data-template="blank-menu-theme-default-light">

<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>

	<title>No Autorizado - {{ config('app.name') }}</title>
	<meta name="description" content="No tienes permisos para acceder a esta página"/>
	<meta name="keywords" content="no autorizado, sin permisos">
	<!-- laravel CRUD token -->
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<!-- Canonical SEO -->
	<link rel="canonical" href="{{ url('/') }}">
	<!-- Favicon -->
	<link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}"/>

	<!-- Include Styles -->
	<!-- BEGIN: Theme CSS-->
	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/fontawesome.css') }}"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}"/>
	<!-- Core CSS -->
	<link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" class="template-customizer-theme-css"/>
	<link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}"/>
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/typeahead-js/typeahead.css') }}"/>

	<!-- Vendor Styles -->

	<!-- Page Styles -->
	<!-- Page -->
	<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
</head>

<body>
	<!-- Layout Content -->
	<!-- Content -->
	<!-- Not Authorized -->
	<div class="container-xxl container-p-y">
		<div class="misc-wrapper text-center">
			<h2 class="mb-1 mx-2">¡No estás autorizado!</h2>
			<p class="mb-4 mx-2">
				No tienes permisos para acceder a esta página con las credenciales que has proporcionado.<br>
				Por favor, contacta al administrador del sistema.
			</p>
			<a href="{{ route('dashboard') }}" class="btn btn-primary mb-4">
				<i class="ti ti-arrow-left me-1"></i> Volver al inicio
			</a>
			<div class="mt-4">
				<img src="{{ asset('assets/img/illustrations/page-misc-you-are-not-authorized.png') }}"
					alt="page-misc-not-authorized" width="170" class="img-fluid">
			</div>
		</div>
	</div>
	<div class="container-fluid misc-bg-wrapper">
		<img src="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
			alt="page-misc-not-authorized" data-app-light-img="illustrations/bg-shape-image-light.png"
			data-app-dark-img="illustrations/bg-shape-image-dark.png">
	</div>
	<!-- /Not Authorized -->
	<!--/ Content -->

	<!--/ Layout Content -->

	<!-- Include Scripts -->
	<!-- BEGIN: Vendor JS-->
	<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
	<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
	<script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
	<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
	<!-- END: Page Vendor JS-->
</body>
</html>

