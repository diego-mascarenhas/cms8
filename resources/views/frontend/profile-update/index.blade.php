<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Actualización del perfil | BBO</title>
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<!-- SweetAlert2 for notifications -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
	<!-- Select2 CSS -->
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	<link href="https://cdn.jsdelivr.net/npm/flag-icons@7.0.0/css/flag-icons.min.css" rel="stylesheet" />

	<!-- Vuexy Template CSS -->
	<link href="{{ asset('assets/vendor/css/core.css') }}" rel="stylesheet" />
	<link href="{{ asset('assets/vendor/css/theme-default.css') }}" rel="stylesheet" />
	<link href="{{ asset('assets/css/demo.css') }}" rel="stylesheet" />
	<link href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" rel="stylesheet" />
	<link href="{{ asset('assets/vendor/libs/select2/select2.css') }}" rel="stylesheet" />

	<style>
		:root {
			--bbo-red: #E7241E;
			--bbo-green: #4CAF50;
			--bbo-light-gray: #f5f5f5;
			--bbo-dark-gray: #333333;
			--bbo-error-bg: #fff2f2;
			--font-family: 'Museo Sans', sans-serif;
		}

		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: 'Nunito', sans-serif;
			font-size: 18px;
			margin: 0;
			padding: 0;
			background-color: #FDFDFC;
			color: #1b1b18;
			line-height: 1.7em;
			font-weight: 500;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			min-width: 1400px;
		}

		/* Header */
		.header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 15px 30px;
			border-bottom: 1px solid #eee;
			background-color: white;
			-webkit-box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
			box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
		}

		.logo {
			height: 40px;
			width: auto;
			-webkit-transition: all 0.4s ease-in-out;
			transition: all 0.4s ease-in-out;
			max-height: 54%;
		}

		.header-right {
			display: flex;
			align-items: center;
			gap: 20px;
		}

		.language-selector {
			display: flex;
			gap: 10px;
		}

		.language-btn {
			border: none;
			background: none;
			font-weight: 500;
			cursor: pointer;
			font-family: var(--font-family);
		}

		.language-btn.active {
			color: var(--bbo-red);
		}

		.profile-container {
			display: flex;
			align-items: center;
			gap: 10px;
			cursor: pointer;
		}

		.profile-avatar {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			background-color: #eee;
			display: flex;
			justify-content: center;
			align-items: center;
			font-weight: 500;
		}

		/* Main Container */
		.main-container {
			max-width: 1600px;
			margin: 0 auto;
			padding: 40px 20px;
		}

		.profile-update-container {
			display: flex;
			justify-content: center;
			gap: 30px;
			margin-top: 20px;
		}

		.sidebar {
			width: 260px;
			position: sticky;
			top: 100px;
			align-self: flex-start;
			margin-left: -180px;
		}

		.sidebar-menu {
			padding-left: 0;
			list-style-type: none;
			padding: 0;
			margin: 0;
		}

		.sidebar-menu li {
			margin-bottom: 15px;
			position: relative;
		}

		.sidebar-menu li a {
			display: flex;
			align-items: center;
			text-decoration: none;
			color: #333;
			font-weight: 500;
			padding: 10px 15px;
			border-radius: 8px;
			transition: all 0.3s ease;
		}

		.sidebar-menu li.active a {
			background-color: var(--bbo-green);
			color: white;
		}

		.sidebar-menu li a:hover {
			background-color: #f0f0f0;
		}

		.menu-arrow {
			margin-right: 10px;
			font-size: 12px;
		}

		/* Content */
		.content {
			flex: 1;
			max-width: 1000px;
			background: white;
			border-radius: 12px;
			padding: 40px;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
		}

		/* Section Title */
		.section-title {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 30px;
			font-size: 28px;
			font-weight: 700;
			color: #1b1b18;
		}

		.update-btn {
			background-color: var(--bbo-green);
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 8px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s ease;
		}

		.update-btn:hover {
			background-color: #45a049;
			transform: translateY(-2px);
		}

		/* Alert Box */
		.alert-box {
			background-color: var(--bbo-error-bg);
			border: 2px solid var(--bbo-red);
			border-radius: 8px;
			padding: 20px;
			margin-bottom: 30px;
			color: #721c24;
			font-weight: 500;
		}

		/* Form Sections */
		.form-section {
			margin-bottom: 40px;
			padding: 40px;
			border: 1px solid #e0e0e0;
			border-radius: 12px;
			background-color: #fafafa;
			min-width: 100%;
			box-sizing: border-box;
		}

		.form-section h3 {
			font-size: 22px;
			font-weight: 600;
			margin-bottom: 10px;
			color: #1b1b18;
		}

		.form-section p {
			color: #666;
			margin-bottom: 25px;
			font-size: 16px;
		}

		/* Form Groups */
		.form-group {
			margin-bottom: 20px;
		}

		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #333;
		}

		.form-control {
			width: 100%;
			padding: 12px 16px;
			border: 2px solid #e0e0e0;
			border-radius: 8px;
			font-size: 16px;
			transition: border-color 0.3s ease;
		}

		.form-control:focus {
			outline: none;
			border-color: var(--bbo-green);
			box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
		}

		.form-control::placeholder {
			color: #999;
		}

		/* Password hint */
		.password-hint {
			font-size: 14px;
			color: #666;
			margin-top: 8px;
			font-style: italic;
		}

						/* Language pairs styles */
		.language-selection-row {
			display: flex;
			gap: 20px;
			margin-bottom: 25px;
		}

		.language-select-group {
			flex: 1;
		}

		.language-select-group .form-label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #333;
			font-size: 14px;
		}

		.language-pairs-container {
			margin-bottom: 15px;
		}

		.language-pairs-list {
			display: flex;
			flex-direction: column;
			gap: 12px;
			margin-bottom: 20px;
		}

		/* Ensure styles are applied with higher specificity */
		.language-pairs-list .language-pair-card {
			display: flex !important;
			align-items: center !important;
			justify-content: space-between !important;
			padding: 18px 24px !important;
			background: #ffffff !important;
			border: 1px solid #e1e5e9 !important;
			border-radius: 8px !important;
			margin-bottom: 12px !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
			transition: all 0.2s ease !important;
			width: 100% !important;
			box-sizing: border-box !important;
		}

		.language-pairs-list .language-pair-card:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
		}

		.language-pairs-list .language-pair-info {
			display: flex !important;
			align-items: center !important;
			flex: 1 !important;
			gap: 16px !important;
			margin-right: 16px !important;
		}

		.language-pairs-list .language-item {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			font-weight: 500 !important;
			color: #374151 !important;
			font-size: 15px !important;
			padding: 6px 12px !important;
			background: #f9fafb !important;
			border-radius: 6px !important;
			border: 1px solid #f3f4f6 !important;
		}

		.language-pairs-list .language-item .flag-icon {
			width: 20px !important;
			height: 15px !important;
			border-radius: 2px !important;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
		}

		.language-pairs-list .arrow-icon {
			color: #6b7280 !important;
			font-size: 16px !important;
			margin: 0 8px !important;
		}

		.language-pairs-list .remove-pair {
			color: #ffffff !important;
			background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
			border: none !important;
			cursor: pointer !important;
			padding: 0 !important;
			border-radius: 50% !important;
			transition: all 0.3s ease !important;
			font-size: 14px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			width: 28px !important;
			height: 28px !important;
			min-width: 28px !important;
			min-height: 28px !important;
			box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3) !important;
			position: relative !important;
			overflow: hidden !important;
		}

		.language-pairs-list .remove-pair::before {
			content: '×' !important;
			font-size: 18px !important;
			font-weight: bold !important;
			line-height: 1 !important;
		}

		.language-pairs-list .remove-pair:hover {
			background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
			transform: scale(1.1) !important;
			box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4) !important;
		}

		.language-pairs-list .remove-pair:active {
			transform: scale(0.95) !important;
		}

		.language-pair-card {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 16px 20px;
			background: #ffffff;
			border: 1px solid #e1e5e9;
			border-radius: 8px;
			margin-bottom: 12px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
			transition: all 0.2s ease;
		}

		.language-pair-card:hover {
			border-color: #d1d5db;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
		}

		.language-pair-info {
			display: flex;
			align-items: center;
			flex: 1;
			gap: 16px;
		}

		.language-item {
			display: flex;
			align-items: center;
			gap: 8px;
			font-weight: 500;
			color: #374151;
			font-size: 15px;
			padding: 6px 12px;
			background: #f9fafb;
			border-radius: 6px;
			border: 1px solid #f3f4f6;
		}

		.language-item .flag-icon {
			width: 20px;
			height: 15px;
			border-radius: 2px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
		}

		.arrow-icon {
			color: #6b7280;
			font-size: 16px;
			margin: 0 8px;
		}

		.remove-pair {
			color: #ef4444;
			background: none;
			border: none;
			cursor: pointer;
			padding: 8px;
			border-radius: 6px;
			transition: all 0.2s ease;
			font-size: 18px;
			display: flex;
			align-items: center;
			justify-content: center;
			width: 36px;
			height: 36px;
		}

		.remove-pair:hover {
			background-color: #fef2f2;
			color: #dc2626;
		}

		.add-pair-button-container {
			margin-top: 20px;
			text-align: center;
		}

		.add-pair-btn {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 8px;
			font-weight: 600;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			transition: all 0.3s ease;
			font-size: 15px;
			box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
		}

		.add-pair-btn:hover {
			background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
			transform: translateY(-1px);
			box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
		}

		.add-pair-btn i {
			font-size: 14px;
		}

		/* Additional improvements for better visual consistency */
		.language-selection-row {
			background: #f8fafc;
			padding: 25px;
			border-radius: 8px;
			border: 1px solid #e2e8f0;
			margin-bottom: 25px;
			width: 100%;
			box-sizing: border-box;
		}

		.language-select-group .select2-container {
			width: 100% !important;
		}

		.language-select-group .select2-container .select2-selection--single {
			height: 46px;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			background-color: #ffffff;
		}

		.language-select-group .select2-container--default .select2-selection--single .select2-selection__rendered {
			line-height: 44px;
			padding-left: 12px;
			color: #374151;
		}

		.language-select-group .select2-container--default .select2-selection--single .select2-selection__arrow {
			height: 44px;
		}

		/* Ensure flag icons display correctly */
		.fi {
			display: inline-block;
			width: 1.33333333em;
			text-align: center;
		}

		/* Improve spacing and alignment */
		.form-section {
			position: relative;
		}

		.form-section:not(:last-child) {
			margin-bottom: 40px;
		}

				/* Select2 Custom Styles */
		.select2-container--bootstrap-5 .select2-selection {
			border: 1px solid #ced4da;
			border-radius: 0.375rem;
			min-height: 38px;
			background-color: #fff;
		}

		.select2-container--bootstrap-5 .select2-selection--single {
			height: 38px;
			padding: 0.375rem 0.75rem;
		}

		.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
			line-height: 1.5;
			padding-left: 0;
			color: #212529;
		}

		.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
			height: 36px;
			right: 8px;
		}

		.select2-container--bootstrap-5 .select2-dropdown {
			border: 1px solid #ced4da;
			border-radius: 0.375rem;
			box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
		}

		.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
			border: 1px solid #ced4da;
			border-radius: 0.375rem;
			padding: 0.375rem 0.75rem;
		}

		.select2-container--bootstrap-5 .select2-results__option {
			padding: 0.375rem 0.75rem;
		}

		.select2-container--bootstrap-5 .select2-results__option--highlighted {
			background-color: #0d6efd;
			color: white;
		}

		.select2-container--bootstrap-5 .select2-results__group {
			font-weight: 600;
			color: #6c757d;
			background-color: #f8f9fa;
			padding: 0.5rem 0.75rem;
			border-bottom: 1px solid #dee2e6;
		}

		.select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
			background-color: #e9ecef;
		}

		/* Focus states */
		.select2-container--bootstrap-5.select2-container--focus .select2-selection {
			border-color: #86b7fe;
			box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
		}

		/* Software badges */
		.badge.bg-label-primary {
			background-color: rgba(105, 108, 255, 0.16) !important;
			color: #696cff !important;
		}

		.badge.rounded-pill {
			border-radius: 50rem !important;
			padding: 0.35em 0.8em !important;
			font-size: 0.75em !important;
			font-weight: 500 !important;
		}

		/* Rates section styles */
		.btn-group .btn {
			transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
		}

		.btn-group .btn.opacity-50 {
			opacity: 0.5;
		}

		.btn-group .btn:not(.opacity-50) {
			opacity: 1;
		}

		/* Ensure active buttons have proper styling */
		.btn-group .btn.active {
			background-color: var(--bs-primary) !important;
			border-color: var(--bs-primary) !important;
			color: white !important;
			opacity: 1 !important;
		}

		/* Same rates mode - all buttons should appear active */
		.btn-group .btn.same-rates-mode {
			background-color: var(--bs-primary);
			border-color: var(--bs-primary);
			color: white;
			opacity: 1;
		}

		/* Responsive */
		@media (max-width: 768px) {
			.profile-update-container {
				flex-direction: column;
			}

			.sidebar {
				width: 100%;
				margin-left: 0;
				position: static;
			}

			.content {
				padding: 20px;
			}

			.form-section {
				padding: 25px;
			}

			.language-selection-row {
				flex-direction: column;
				gap: 15px;
			}

			.language-pairs-list .language-pair-info {
				flex-direction: column;
				gap: 8px;
				align-items: flex-start;
			}

			.language-pairs-list .arrow-icon {
				transform: rotate(90deg);
				margin: 4px 0;
			}

			.section-title {
				flex-direction: column;
				gap: 15px;
				text-align: center;
			}

			.language-selection-row {
				flex-direction: column;
				gap: 15px;
			}

						.language-pair-card {
				padding: 10px 12px;
			}

			.language-pair-info {
				gap: 8px;
			}

			.language-item {
				font-size: 13px;
			}

			.language-item .flag-icon {
				width: 16px;
				height: 12px;
			}
		}
	</style>
</head>
<body>
	<!-- Header -->
	<header class="header">
		<a href="/">
			<img src="https://bbo.revisionalpha.net/images/logo-bbo.svg" alt="BBO Logo" class="logo" onerror="this.src='data:image/svg+xml;charset=utf-8,%3Csvg xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 width%3D%2270%22 height%3D%2240%22%3E%3Crect width%3D%2270%22 height%3D%2240%22 fill%3D%22%23E7241E%22 rx%3D%2220%22%3E%3C%2Frect%3E%3Ctext x%3D%2235%22 y%3D%2225%22 font-family%3D%22Open Sans%22 font-size%3D%2218%22 fill%3D%22white%22 text-anchor%3D%22middle%22%3Ebbo%3C%2Ftext%3E%3C%2Fsvg%3E'; this.style.height='30px';">
		</a>
		<div class="header-right">
			<div class="language-selector">
				<button class="language-btn active">ES</button>
				<button class="language-btn">EN</button>
			</div>
			<div class="profile-container">
				<div class="profile-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
			</div>
		</div>
	</header>

	<!-- Main Content -->
	<div class="main-container">

		<form action="{{ route('profile-update.store') }}" method="POST" enctype="multipart/form-data">
			@csrf

			<div class="section-title">
				Actualización del perfil
				<button type="submit" class="update-btn">Actualizar registro</button>
			</div>

			<div class="alert-box">
				<strong>Importante:</strong> Cuando pulses "Actualizar registro" NO podrás volver a editar los datos hasta que bbo los valide. Una vez hecho esto nos pondremos en contacto contigo.
			</div>

			<div class="profile-update-container">
				<div class="sidebar">
					<ul class="sidebar-menu">
						<li class="active"><a href="#password"><span class="menu-arrow">▶</span>Contraseña</a></li>
						<li><a href="#contact-info">Datos de contacto</a></li>
						<li><a href="#resume">Curriculum</a></li>
						<li><a href="#more-info">Más información</a></li>
						<li><a href="#software">Software</a></li>
						<li><a href="#voice-acting">Locuciones</a></li>
						<li><a href="#languages">Mis pares de idiomas</a></li>
						<li><a href="#rates">Tarifas</a></li>
						<li><a href="#availability">Disponibilidad</a></li>
					</ul>
				</div>

				<div class="content">
					<!-- Password -->
					<div class="form-section" id="password">
						<h3>Contraseña</h3>
						<p>Define tu contraseña de acceso a la plataforma</p>

						<div class="form-group">
							<label for="password">Contraseña</label>
							<input type="password" id="password" name="password" class="form-control" placeholder="Contraseña">
						</div>

						<div class="form-group">
							<label for="password_confirmation">Repite la contraseña</label>
							<input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Repite la contraseña">
							<div class="password-hint">Pon al menos una mayúscula, un número y un carácter especial</div>
						</div>
					</div>

					<!-- Contact Info -->
					<div class="form-section" id="contact-info">
						<h3>Datos de contacto</h3>
						<p>Rellena los detalles necesarios para poder contactar contigo.</p>

						<div class="form-group">
							<label for="first_name">Nombre *</label>
							<input type="text" id="first_name" name="first_name" class="form-control" placeholder="Nombre"
								   value="{{ old('first_name', $existingData['contact_info']['first_name'] ?? '') }}">
						</div>

						<div class="form-group">
							<label for="last_name">Apellido *</label>
							<input type="text" id="last_name" name="last_name" class="form-control" placeholder="Apellido"
								   value="{{ old('last_name', $existingData['contact_info']['last_name'] ?? '') }}">
						</div>

						<div class="form-group">
							<label for="email">Correo electrónico *</label>
							<input type="text" id="email" name="email" class="form-control" placeholder="Correo electrónico"
								   value="{{ old('email', $existingData['contact_info']['email'] ?? '') }}">
						</div>

						<div class="form-group">
							<label for="phone">Teléfono *</label>
							<input type="text" id="phone" name="phone" class="form-control" placeholder="Teléfono"
								   value="{{ old('phone', $existingData['contact_info']['phone'] ?? '') }}">
						</div>

						<div class="form-group">
							<x-country-select
								name="country"
								id="country"
								label="País *"
								:value="old('country', $existingData['contact_info']['country'] ?? '')"
							/>
						</div>

						<div class="form-group">
							<x-timezone-select
								name="timezone"
								id="timezone"
								label="Zona horaria *"
								:value="old('timezone', $existingData['contact_info']['timezone'] ?? '')"
							/>
						</div>
					</div>

					<!-- Resume -->
					<div class="form-section" id="resume">
						<h3>Curriculum</h3>
						<p>Información sobre tu experiencia y formación profesional</p>

						<div class="form-group">
							<label for="freelance_certificate">Certificado de autónomo *</label>
							<input type="file" id="freelance_certificate" name="freelance_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
						</div>

						<div class="form-group">
							<label for="resume">Curriculum vitae *</label>
							<input type="file" id="resume" name="resume" class="form-control" accept=".pdf,.doc,.docx">
						</div>

						<div class="form-group">
							<label for="project_title">Título del proyecto</label>
							<input type="text" id="project_title" name="project_title" class="form-control" placeholder="Título del proyecto"
								   value="{{ old('project_title', $existingData['resume']['project_title'] ?? '') }}">
						</div>

						<div class="form-group">
							<label for="project_role">Rol en el proyecto</label>
							<input type="text" id="project_role" name="project_role" class="form-control" placeholder="Rol en el proyecto"
								   value="{{ old('project_role', $existingData['resume']['project_role'] ?? '') }}">
						</div>

						<div class="form-group">
							<label for="project_year">Año del proyecto</label>
							<input type="number" id="project_year" name="project_year" class="form-control" placeholder="Año" min="1900" max="2030"
								   value="{{ old('project_year', $existingData['resume']['project_year'] ?? '') }}">
						</div>
					</div>

					<!-- More Info -->
					<div class="form-section" id="more-info">
						<h3>Más información</h3>
						<p>Información adicional sobre tu perfil profesional</p>

						<div class="form-group">
							<label for="certification">Certificaciones</label>
							<textarea id="certification" name="certification" class="form-control" rows="3" placeholder="Tus certificaciones profesionales">{{ old('certification', $existingData['more_info']['certification'] ?? '') }}</textarea>
						</div>
					</div>

					<!-- Software -->
					<div class="form-section" id="software">
						<div class="card-header border-bottom d-flex justify-content-between align-items-center">
							<h5 class="mb-0">Software de trabajo</h5>
							<a href="javascript:void(0)" id="toggleSoftwareEdit" class="text-secondary">
								<i class="ti ti-edit ti-sm"></i>
							</a>
						</div>
						<div class="card-body pt-4">



						<!-- Read-only view -->
						<div id="software-display">
							@if(isset($existingData['software']) && is_array($existingData['software']) && count($existingData['software']) > 0)
								@foreach($existingData['software'] as $software)
									<span class="badge bg-label-primary rounded-pill me-1 mb-1">
										{{ $software['name'] }}{{ isset($software['category_name']) ? ' (' . $software['category_name'] . ')' : '' }}
									</span>
								@endforeach
							@else
								<div class="mt-2">
									<span class="text-muted">No hay software asignado</span>
								</div>
							@endif
						</div>

						<!-- Edit form -->
						<form id="software-edit-form" class="mt-3 d-none">
							@csrf
							<x-software-select
								id="software_ids"
								label="Software que dominas"
								:selected="old('software_ids', $existingData['software_ids'] ?? [])"
							/>
							<div class="mt-3">
								<button type="button" id="saveSoftware" class="btn btn-primary btn-sm">Guardar</button>
								<button type="button" id="cancelSoftwareEdit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
							</div>
						</form>

						</div>
					</div>

					<!-- Voice Acting -->
					<div class="form-section" id="voice-acting">
						<h3>Locuciones</h3>
						<p>Muestra de voz y archivos de locución</p>

						<div class="form-group">
							<label for="voice_sample">Muestra de voz</label>
							<input type="file" id="voice_sample" name="voice_sample" class="form-control" accept=".mp3,.wav,.m4a">
						</div>
					</div>

										<!-- Languages -->
					<div class="form-section" id="languages">
						<h3>Pares de idiomas</h3>
						<p>Configura tus combinaciones de idiomas de trabajo</p>

												<div class="language-selection-row">
							<div class="language-select-group">
								<x-variant-language-select
									name="source_language"
									id="source_language"
									label="Idioma origen"
									:required="false"
								/>
							</div>
							<div class="language-select-group">
								<x-variant-language-select
									name="target_language"
									id="target_language"
									label="Idioma destino"
									:required="false"
								/>
							</div>
						</div>

						<div class="language-pairs-container">
							<div class="language-pairs-list"
								 data-existing-pairs='@json($existingData['language_pairs'] ?? [])'>
								<!-- Language pairs will be loaded here -->
							</div>
						</div>

						<div class="add-pair-button-container">
							<button type="button" class="add-pair-btn" id="add_language_pair">
								<i class="ti ti-plus"></i>Añadir par de idiomas
							</button>
						</div>
					</div>

					<!-- Rates -->
					<div class="form-section" id="rates">
						<div class="card-header border-bottom d-flex justify-content-between align-items-center">
							<h5 class="mb-0">Tarifas profesionales</h5>
						</div>
						<div class="card-body pt-4">
							<!-- Language selection -->
							<div class="mb-3">
								<h5 class="mb-3">Combinaciones de idiomas</h5>

								@if($collaboratorLanguageVariants && $collaboratorLanguageVariants->count() > 0)
									<div class="d-flex flex-wrap gap-2 mb-3">
										@foreach($collaboratorLanguageVariants as $index => $variant)
											@php
												$sourceFlag = strtolower($variant->sourceLanguage ? $variant->sourceLanguage->country_code ?? '' : '');
												if (empty($sourceFlag) && $variant->sourceLanguage) {
													$sourceFlag = strtolower($variant->source_language_code);
												}

												$targetFlag = strtolower($variant->targetLanguage ? $variant->targetLanguage->country_code ?? '' : '');
												if (empty($targetFlag) && $variant->targetLanguage) {
													$targetFlag = strtolower($variant->target_language_code);
												}

												$isActive = $index === 0; // First combination active by default
											@endphp

											<div class="btn-group me-2">
												<button type="button" class="btn btn-outline-primary {{ $isActive ? 'active' : '' }} px-3"
														data-source="{{ $variant->source_language_code }}"
														data-target="{{ $variant->target_language_code }}">
													@if(!empty($sourceFlag))
														<span class="fi fi-{{ $sourceFlag }} me-1"></span>
													@endif
													{{ $variant->sourceLanguage ? $variant->sourceLanguage->name : $variant->source_language_code }}
													<span class="mx-1"><i class="ti ti-arrow-right text-muted"></i></span>
													@if(!empty($targetFlag))
														<span class="fi fi-{{ $targetFlag }} me-1"></span>
													@endif
													{{ $variant->targetLanguage ? $variant->targetLanguage->name : $variant->target_language_code }}
												</button>
											</div>
										@endforeach
									</div>

									<div class="form-check mt-2">
										<input class="form-check-input" type="checkbox" id="sameRates" name="same_rates">
										<label class="form-check-label" for="sameRates">
											Usar las mismas tarifas para todas las combinaciones
										</label>
									</div>

									<input type="hidden" name="current_language_pair" id="current_language_pair" value="">
								@else
									<div class="alert alert-warning">
										<div class="d-flex align-items-center">
											<i class="ti ti-alert-triangle me-2"></i>
											<span>No hay combinaciones de idiomas registradas para este colaborador.</span>
										</div>
									</div>
								@endif
							</div>

							<!-- Currency selection -->
							<div class="mb-3 row">
								<label class="col-form-label col-md-2">Divisa (*)</label>
								<div class="col-md-4">
									<select class="form-select" name="currency" required>
										<option value="EUR" {{ old('currency', $currentCurrency) === 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
										<option value="USD" {{ old('currency', $currentCurrency) === 'USD' ? 'selected' : '' }}>USD - Dólar estadounidense</option>
										<option value="GBP" {{ old('currency', $currentCurrency) === 'GBP' ? 'selected' : '' }}>GBP - Libra esterlina</option>
									</select>
								</div>
							</div>
							<hr>

							<!-- Dynamic Fares by Type -->
							@if(isset($fares) && $fares->count() > 0 && $collaboratorLanguageVariants && $collaboratorLanguageVariants->count() > 0)
								@foreach($fares->groupBy('type.name') as $typeName => $fares)
									<h5 class="mt-4 mb-3">{{ $typeName ?: 'Sin categoría' }}</h5>

									@php
										$fareChunks = $fares->chunk(2);
									@endphp

									@foreach($fareChunks as $fareChunk)
										<div class="row mb-3">
											@foreach($fareChunk as $fare)
												@php
													$currentPrice = old("rates.{$fare->id}", $currentRatesData[$fare->id]['price'] ?? 0);
													$currentUnitId = old("units.{$fare->id}", $currentRatesData[$fare->id]['unit_id'] ?? ($fare->units->count() > 0 ? $fare->units->first()->id : null));
												@endphp
												<div class="col-md-6">
													<label class="form-label">{{ $fare->name }}</label>
													<div class="input-group input-group-sm">
														<span class="input-group-text currency-symbol"></span>
														<input type="number"
															   class="form-control fare-input"
															   data-fare-id="{{ $fare->id }}"
															   name="rates[{{ $fare->id }}]"
															   value="{{ number_format($currentPrice, 2, '.', '') }}"
															   step="0.01"
															   min="0"
															   placeholder="0.00">

														@if($fare->units && $fare->units->count() > 1)
															<select class="form-select unit-select"
																	data-fare-id="{{ $fare->id }}"
																	name="units[{{ $fare->id }}]"
																	style="max-width: 120px;"
																	required>
																@foreach($fare->units as $unit)
																	<option value="{{ $unit->id }}"
																		{{ $currentUnitId == $unit->id ? 'selected' : '' }}>
																		/{{ $unit->type }}
																	</option>
																@endforeach
															</select>
														@elseif($fare->units && $fare->units->count() == 1)
															<span class="input-group-text">/{{ $fare->units->first()->type }}</span>
															<input type="hidden" name="units[{{ $fare->id }}]" value="{{ $fare->units->first()->id }}">
														@else
															<span class="input-group-text">/unidad</span>
														@endif
													</div>
												</div>
											@endforeach
										</div>
									@endforeach
								@endforeach
							@else
								<div class="alert alert-info">
									<div class="d-flex align-items-center">
										<i class="ti ti-info-circle me-2"></i>
										<span>
											@if(!$collaboratorLanguageVariants || $collaboratorLanguageVariants->count() == 0)
												No hay combinaciones de idiomas registradas para este colaborador.
											@else
												No hay tarifas disponibles para configurar.
											@endif
										</span>
									</div>
								</div>
							@endif
						</div>
					</div>

										<!-- Availability -->
					<div class="form-section" id="availability">
						<h3>Períodos de no disponibilidad</h3>
						<p>Selecciona los períodos en los que el colaborador no estará disponible para aceptar proyectos.</p>
						<p>Esto te ayudará a contactarle solo cuando realmente pueda colaborar.</p>

						<!-- Calendar de disponibilidad -->
						@if(isset($contact) && isset($weeklyAvailability) && isset($absences) && isset($months))
							@include('components.availability-calendar', [
								'collaborator' => $contact,
								'weeklyAvailability' => $weeklyAvailability,
								'absences' => $absences,
								'months' => $months
							])
						@endif
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- Select2 JS -->
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

		<script>
		$(document).ready(function() {
			// Format timezone options with offset and current time
			function formatTimezoneOption(option) {
				if (!option.id) {
					return option.text;
				}

				// Simplified for debugging
				return option.text;
			}

			// Format selected timezone
			function formatTimezoneSelection(option) {
				if (!option.id) {
					return option.text;
				}

				// Simplified for debugging
				return option.text;
			}



			// Initialize Select2 for language selectors with custom template (exclude timezone)
			$('.select2:not(#timezone)').select2({
				theme: 'bootstrap-5',
				templateResult: formatLanguageOption,
				templateSelection: formatLanguageOption
			});

						// Initialize timezone select with enhanced features
			$('#timezone').select2({
				theme: 'bootstrap-5',
				placeholder: 'Selecciona una zona horaria',
				allowClear: true,
				width: '100%',
				searchInputPlaceholder: 'Buscar zonas horarias...',
				templateResult: formatTimezoneOption,
				templateSelection: formatTimezoneSelection,
				escapeMarkup: function(markup) {
					return markup;
				}
			});

			// Update current time when timezone changes
			$('#timezone').on('change', function() {
				const selectedTimezone = $(this).val();
				const $currentTimeSpan = $('#timezone-current-time');
				if (selectedTimezone && $currentTimeSpan.length) {
					updateCurrentTime(selectedTimezone);
				}
			});

			// Software management
			$('#toggleSoftwareEdit').on('click', function() {
				$('#software-display').addClass('d-none');
				$('#software-edit-form').removeClass('d-none');
				$('#software-edit-button').addClass('d-none');
			});

			$('#cancelSoftwareEdit').on('click', function() {
				$('#software-edit-form').addClass('d-none');
				$('#software-display').removeClass('d-none');
				$('#software-edit-button').removeClass('d-none');
			});

			$('#saveSoftware').on('click', function() {
				const softwareIds = $('#software_ids').val() || [];

				// Update the display with selected software
				let badgesHtml = '';
				if (softwareIds.length > 0) {
					// Get selected options and create badges
					$('#software_ids option:selected').each(function() {
						const softwareText = $(this).text();
						badgesHtml += `<span class="badge bg-label-primary rounded-pill me-1 mb-1">${softwareText}</span>`;
					});
				} else {
					badgesHtml = '<div class="mt-2"><span class="text-muted">No hay software asignado</span></div>';
				}

				$('#software-display').html(badgesHtml);

				// Return to read-only view
				$('#software-edit-form').addClass('d-none');
				$('#software-display').removeClass('d-none');
				$('#software-edit-button').removeClass('d-none');

				// Show success notification
				if (typeof Swal !== 'undefined') {
					Swal.fire({
						icon: 'success',
						title: '¡Éxito!',
						text: 'Software actualizado correctamente',
						timer: 2000,
						showConfirmButton: false
					});
				} else {
					alert('Software actualizado correctamente');
				}
			});

			// Add language pair button handler
			$('#add_language_pair').on('click', function() {
				const sourceLanguage = $('#source_language').select2('data')[0];
				const targetLanguage = $('#target_language').select2('data')[0];

				// Check if languages are selected (no validation alerts)
				if (!sourceLanguage || !$('#source_language').val()) {
					return;
				}

				if (!targetLanguage || !$('#target_language').val()) {
					return;
				}

				// Get text and values
				const sourceText = sourceLanguage.text;
				const targetText = targetLanguage.text;
				const sourceValue = $('#source_language').val();
				const targetValue = $('#target_language').val();
				const sourceFlag = $('#source_language option:selected').data('flag');
				const targetFlag = $('#target_language option:selected').data('flag');

				// Check if source and target are the same
				if (sourceValue === targetValue) {
					return;
				}

				// Check if this pair already exists
				const pairExists = checkIfPairExists(sourceValue, targetValue);
				if (pairExists) {
					return;
				}

				// Get flag codes safely
				const sourceFlagCode = sourceFlag || (sourceValue.split('-').length > 1 ?
					sourceValue.split('-')[1].toLowerCase() :
					sourceValue.split('-')[0].toLowerCase());

				const targetFlagCode = targetFlag || (targetValue.split('-').length > 1 ?
					targetValue.split('-')[1].toLowerCase() :
					targetValue.split('-')[0].toLowerCase());

				// Create new pair badge
				const newPair = $(`
					<div class="language-pair-card">
						<div class="language-pair-info">
							<div class="language-item">
								<i class="fi fi-${sourceFlagCode} flag-icon"></i>
								<span>${sourceText}</span>
							</div>
							<i class="ti ti-arrow-right arrow-icon"></i>
							<div class="language-item">
								<i class="fi fi-${targetFlagCode} flag-icon"></i>
								<span>${targetText}</span>
							</div>
						</div>
						<button type="button" class="remove-pair"></button>
						<input type="hidden" name="language_pairs[]" value="${sourceValue}|${targetValue}">
					</div>
				`);

				// Add to container
				$('.language-pairs-list').append(newPair);

				// Reset selections
				$('#source_language').val(null).trigger('change');
				$('#target_language').val(null).trigger('change');
			});

			// Remove language pair
			$(document).on('click', '.remove-pair', function() {
				$(this).closest('.language-pair-card').remove();
			});

			// Format language options with flags
			function formatLanguageOption(option) {
				if (!option.id) {
					return option.text;
				}

				const flag = $(option.element).data('flag');
				return $(`<span><i class="fi fi-${flag} me-2"></i>${option.text}</span>`);
			}

			// Update current time display (client-side only)
			function updateCurrentTime(timezone) {
				try {
					const now = new Date();
					const options = {
						weekday: 'long',
						year: 'numeric',
						month: 'long',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						timeZone: timezone,
						timeZoneName: 'short'
					};

					const formattedTime = new Intl.DateTimeFormat('en-US', options).format(now);
					$('#timezone-current-time').text(formattedTime);
				} catch (error) {
					$('#timezone-current-time').text('Unable to display time');
				}
			}

			// Auto-refresh current time every minute
			const $currentTimeSpan = $('#timezone-current-time');
			if ($currentTimeSpan.length) {
				setInterval(function() {
					const selectedTimezone = $('#timezone').val();
					if (selectedTimezone) {
						updateCurrentTime(selectedTimezone);
					}
				}, 60000); // Update every minute
			}

			// Check if language pair already exists
			function checkIfPairExists(source, target) {
				let exists = false;
				$('input[name="language_pairs[]"]').each(function() {
					const value = $(this).val();
					if (!value) return;

					const parts = value.split('|');
					if (parts.length !== 2) return;

					const [existingSource, existingTarget] = parts;

					if (existingSource === source && existingTarget === target) {
						exists = true;
						return false; // break the loop
					}
				});

				return exists;
			}

						// Clear example pairs on load
			$('.language-pairs-list').empty();

			// Load existing pairs from data attribute
			var existingPairs = $('.language-pairs-list').data('existing-pairs');
			if (existingPairs && existingPairs.length > 0) {
				existingPairs.forEach(function(pair) {
					if (pair.source && pair.target) {
						// Extract flag codes safely
						var sourceParts = pair.source.split('-');
						var targetParts = pair.target.split('-');
						var sourceFlag = sourceParts.length > 1 ? sourceParts[1].toLowerCase() : sourceParts[0].toLowerCase();
						var targetFlag = targetParts.length > 1 ? targetParts[1].toLowerCase() : targetParts[0].toLowerCase();

						// Create new pair badge
						var savedPair = $('<div class="language-pair-card">' +
							'<div class="language-pair-info">' +
								'<div class="language-item">' +
									'<i class="fi fi-' + sourceFlag + ' flag-icon"></i>' +
									'<span>' + pair.source_text + '</span>' +
								'</div>' +
								'<i class="ti ti-arrow-right arrow-icon"></i>' +
								'<div class="language-item">' +
									'<i class="fi fi-' + targetFlag + ' flag-icon"></i>' +
									'<span>' + pair.target_text + '</span>' +
								'</div>' +
							'</div>' +
							'<button type="button" class="remove-pair"></button>' +
							'<input type="hidden" name="language_pairs[]" value="' + pair.source + '|' + pair.target + '">' +
						'</div>');

						$('.language-pairs-list').append(savedPair);
					}
				});
			}

			// Smooth scrolling for navigation
			document.querySelectorAll('.sidebar-menu a').forEach(link => {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					const targetId = this.getAttribute('href').substring(1);
					const targetElement = document.getElementById(targetId);

					if (targetElement) {
						targetElement.scrollIntoView({ behavior: 'smooth' });

						// Update active state
						document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
						this.parentElement.classList.add('active');
					}
				});
			});

			// Rates functionality
			// Currency symbol mapping
			const currencySymbols = {
				'EUR': '€',
				'USD': '$',
				'GBP': '£'
			};

			// Store rates data for each language combination
			let ratesData = {};

			// Function to save current form state
			function saveCurrentRatesState() {
				const currentPair = $('#current_language_pair').val();
				if (!currentPair) return;

				const [sourceCode, targetCode] = currentPair.split('|');
				const key = `${sourceCode}|${targetCode}`;

				ratesData[key] = {
					currency: $('select[name="currency"]').val(),
					rates: {},
					units: {}
				};

				$('.fare-input').each(function() {
					const fareId = $(this).data('fare-id');
					ratesData[key].rates[fareId] = $(this).val();

					const unitSelect = $(`.unit-select[data-fare-id="${fareId}"]`);
					if (unitSelect.length) {
						ratesData[key].units[fareId] = unitSelect.val();
					}

					const unitHidden = $(`input[type="hidden"][name="units[${fareId}]"]`);
					if (unitHidden.length) {
						ratesData[key].units[fareId] = unitHidden.val();
					}
				});
			}

			// Function to restore form state
			function restoreRatesState(sourceCode, targetCode) {
				const key = `${sourceCode}|${targetCode}`;

				if (ratesData[key]) {
					// Restore rates and units only, keep current currency selection
					$('.fare-input').each(function() {
						const fareId = $(this).data('fare-id');
						const rate = ratesData[key].rates[fareId] || '0.00';
						$(this).val(rate);

						const unitSelect = $(`.unit-select[data-fare-id="${fareId}"]`);
						if (unitSelect.length && ratesData[key].units[fareId]) {
							unitSelect.val(ratesData[key].units[fareId]);
						}
					});
				}
			}

			// Function to update currency symbols without triggering events
			function updateCurrencySymbols(currency) {
				const symbol = currencySymbols[currency] || '€';
				$('.currency-symbol').text(symbol);
			}

			// Language combination button click handler
			$('[data-source][data-target]').on('click', function() {
				const sourceCode = $(this).data('source');
				const targetCode = $(this).data('target');
				const isSameRates = $('#sameRates').is(':checked');

				// If same rates mode, don't allow switching between combinations
				if (isSameRates) {
					return;
				}

				// Save current state before switching
				saveCurrentRatesState();

				// Update active state - act like radio buttons
				$('[data-source][data-target]').removeClass('active').addClass('opacity-50');
				$(this).removeClass('opacity-50').addClass('active');

				if (sourceCode && targetCode) {
					// Update hidden field
					$('#current_language_pair').val(sourceCode + '|' + targetCode);

					// Load rates for this combination
					restoreRatesState(sourceCode, targetCode);
				}
			});

			// Same rates checkbox handler
			$('#sameRates').on('change', function() {
				const isChecked = $(this).is(':checked');

				if (isChecked) {
					// Same rates for all combinations - show all as selected
					$('[data-source][data-target]').removeClass('opacity-50').addClass('active');
				} else {
					// Different rates for each combination - show only current active one
					$('[data-source][data-target]').removeClass('active').addClass('opacity-50');

					// Activate only the current language pair
					const currentPair = $('#current_language_pair').val();
					if (currentPair) {
						const [sourceCode, targetCode] = currentPair.split('|');
						const activeBtn = $(`[data-source="${sourceCode}"][data-target="${targetCode}"]`);
						activeBtn.removeClass('opacity-50').addClass('active');
					}
				}
			});

			// Handle currency change
			$('select[name="currency"]').on('change', function() {
				const selectedCurrency = $(this).val();
				const symbol = currencySymbols[selectedCurrency] || '€';

				// Update all currency symbols in the form
				$('.currency-symbol').text(symbol);

				// Update stored data for ALL language combinations
				for (let key in ratesData) {
					if (ratesData[key]) {
						ratesData[key].currency = selectedCurrency;
					}
				}
			});

			// Set initial language combination
			const activeBtn = $('[data-source][data-target].active');
			if (activeBtn.length) {
				const sourceCode = activeBtn.data('source');
				const targetCode = activeBtn.data('target');
				if (sourceCode && targetCode) {
					$('#current_language_pair').val(sourceCode + '|' + targetCode);
				}
			} else {
				// If no active button, activate the first one
				const firstBtn = $('[data-source][data-target]').first();
				if (firstBtn.length) {
					firstBtn.addClass('active');
					const sourceCode = firstBtn.data('source');
					const targetCode = firstBtn.data('target');
					if (sourceCode && targetCode) {
						$('#current_language_pair').val(sourceCode + '|' + targetCode);
					}
				}
			}

			// Initialize currency symbols
			const currentCurrency = $('select[name="currency"]').val();
			updateCurrencySymbols(currentCurrency);

			// Initialize checkbox state
			setTimeout(() => {
				$('#sameRates').trigger('change');
			}, 10);
		});
	</script>

	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
