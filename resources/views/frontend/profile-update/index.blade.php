<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización del perfil | BBO</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/flag-icons@7.0.0/css/flag-icons.min.css" rel="stylesheet" />

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
                        <li><a href="#voice-acting">Locuciones</a></li>
                        <li><a href="#languages">Mis pares de idiomas</a></li>
                        <li><a href="#rates">Tarifas profesionales</a></li>
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
                                   value="{{ old('first_name', $existingData['contact_info']['first_name'] ?? '') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Apellido *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Apellido"
                                   value="{{ old('last_name', $existingData['contact_info']['last_name'] ?? '') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Correo electrónico *</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Correo electrónico"
                                   value="{{ old('email', $existingData['contact_info']['email'] ?? '') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Teléfono *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="Teléfono"
                                   value="{{ old('phone', $existingData['contact_info']['phone'] ?? '') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="country">País *</label>
                            <select id="country" name="country" class="form-control" required>
                                <option value="">Selecciona un país</option>
                                <option value="ES" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'ES') ? 'selected' : '' }}>España</option>
                                <option value="MX" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'MX') ? 'selected' : '' }}>México</option>
                                <option value="AR" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'AR') ? 'selected' : '' }}>Argentina</option>
                                <option value="CO" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'CO') ? 'selected' : '' }}>Colombia</option>
                                <option value="PE" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'PE') ? 'selected' : '' }}>Perú</option>
                                <option value="VE" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'VE') ? 'selected' : '' }}>Venezuela</option>
                                <option value="CL" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'CL') ? 'selected' : '' }}>Chile</option>
                                <option value="EC" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'EC') ? 'selected' : '' }}>Ecuador</option>
                                <option value="GT" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'GT') ? 'selected' : '' }}>Guatemala</option>
                                <option value="CU" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'CU') ? 'selected' : '' }}>Cuba</option>
                                <option value="BO" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'BO') ? 'selected' : '' }}>Bolivia</option>
                                <option value="DO" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'DO') ? 'selected' : '' }}>República Dominicana</option>
                                <option value="HN" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'HN') ? 'selected' : '' }}>Honduras</option>
                                <option value="PY" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'PY') ? 'selected' : '' }}>Paraguay</option>
                                <option value="SV" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'SV') ? 'selected' : '' }}>El Salvador</option>
                                <option value="NI" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'NI') ? 'selected' : '' }}>Nicaragua</option>
                                <option value="CR" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'CR') ? 'selected' : '' }}>Costa Rica</option>
                                <option value="PA" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'PA') ? 'selected' : '' }}>Panamá</option>
                                <option value="UY" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'UY') ? 'selected' : '' }}>Uruguay</option>
                                <option value="GQ" {{ (old('country', $existingData['contact_info']['country'] ?? '') == 'GQ') ? 'selected' : '' }}>Guinea Ecuatorial</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="timezone">Zona horaria *</label>
                            <select id="timezone" name="timezone" class="form-control" required>
                                <option value="">Selecciona una zona horaria</option>
                                <option value="Europe/Madrid" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'Europe/Madrid') ? 'selected' : '' }}>Madrid (UTC+1)</option>
                                <option value="America/Mexico_City" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Mexico_City') ? 'selected' : '' }}>Ciudad de México (UTC-6)</option>
                                <option value="America/Argentina/Buenos_Aires" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Argentina/Buenos_Aires') ? 'selected' : '' }}>Buenos Aires (UTC-3)</option>
                                <option value="America/Bogota" {{ (old('country', $existingData['contact_info']['timezone'] ?? '') == 'America/Bogota') ? 'selected' : '' }}>Bogotá (UTC-5)</option>
                                <option value="America/Lima" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Lima') ? 'selected' : '' }}>Lima (UTC-5)</option>
                                <option value="America/Caracas" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Caracas') ? 'selected' : '' }}>Caracas (UTC-4)</option>
                                <option value="America/Santiago" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Santiago') ? 'selected' : '' }}>Santiago (UTC-3)</option>
                                <option value="America/Guayaquil" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Guayaquil') ? 'selected' : '' }}>Guayaquil (UTC-5)</option>
                                <option value="America/Guatemala" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Guatemala') ? 'selected' : '' }}>Guatemala (UTC-6)</option>
                                <option value="America/Havana" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Havana') ? 'selected' : '' }}>La Habana (UTC-5)</option>
                                <option value="America/La_Paz" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/La_Paz') ? 'selected' : '' }}>La Paz (UTC-4)</option>
                                <option value="America/Santo_Domingo" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Santo_Domingo') ? 'selected' : '' }}>Santo Domingo (UTC-4)</option>
                                <option value="America/Tegucigalpa" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Tegucigalpa') ? 'selected' : '' }}>Tegucigalpa (UTC-6)</option>
                                <option value="America/Asuncion" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Asuncion') ? 'selected' : '' }}>Asunción (UTC-3)</option>
                                <option value="America/El_Salvador" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/El_Salvador') ? 'selected' : '' }}>San Salvador (UTC-6)</option>
                                <option value="America/Managua" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Managua') ? 'selected' : '' }}>Managua (UTC-6)</option>
                                <option value="America/Costa_Rica" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Costa_Rica') ? 'selected' : '' }}>San José (UTC-6)</option>
                                <option value="America/Panama" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Panama') ? 'selected' : '' }}>Panamá (UTC-5)</option>
                                <option value="America/Montevideo" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'America/Montevideo') ? 'selected' : '' }}>Montevideo (UTC-3)</option>
                                <option value="Africa/Malabo" {{ (old('timezone', $existingData['contact_info']['timezone'] ?? '') == 'Africa/Malabo') ? 'selected' : '' }}>Malabo (UTC+1)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Resume -->
                    <div class="form-section" id="resume">
                        <h3>Curriculum</h3>
                        <p>Información sobre tu experiencia y formación profesional</p>

                        <div class="form-group">
                            <label for="freelance_certificate">Certificado de autónomo *</label>
                            <input type="file" id="freelance_certificate" name="freelance_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>

                        <div class="form-group">
                            <label for="resume">Curriculum vitae *</label>
                            <input type="file" id="resume" name="resume" class="form-control" accept=".pdf,.doc,.docx" required>
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
                            <label for="software">Software que manejas</label>
                            <textarea id="software" name="software" class="form-control" rows="3" placeholder="Lista el software que manejas">{{ old('software', $existingData['more_info']['software'] ?? '') }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="certification">Certificaciones</label>
                            <textarea id="certification" name="certification" class="form-control" rows="3" placeholder="Tus certificaciones profesionales">{{ old('certification', $existingData['more_info']['certification'] ?? '') }}</textarea>
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
                        <h3>Tarifas profesionales</h3>
                        <p>Define tus tarifas por tipo de servicio</p>

                        <div class="form-group">
                            <label for="rate_audiovisual">Tarifa traducción audiovisual (por minuto)</label>
                            <input type="number" id="rate_audiovisual" name="rate_audiovisual" class="form-control" placeholder="0.00" step="0.01" min="0"
                                   value="{{ old('rate_audiovisual', $existingData['rates']['audiovisual'] ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label for="rate_general">Tarifa traducción general (por palabra)</label>
                            <input type="number" id="rate_general" name="rate_general" class="form-control" placeholder="0.00" step="0.01" min="0"
                                   value="{{ old('rate_general', $existingData['rates']['general'] ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label for="rate_accessibility">Tarifa accesibilidad (por hora)</label>
                            <input type="number" id="rate_accessibility" name="rate_accessibility" class="form-control" placeholder="0.00" step="0.01" min="0"
                                   value="{{ old('rate_accessibility', $existingData['rates']['accessibility'] ?? '') }}">
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="form-section" id="availability">
                        <h3>Disponibilidad</h3>
                        <p>Define tu disponibilidad de trabajo</p>

                        <div class="form-group">
                            <label for="availability_status">Estado de disponibilidad</label>
                            <select id="availability_status" name="availability_status" class="form-control">
                                <option value="available" {{ (old('availability_status', $existingData['availability']['status'] ?? '') == 'available') ? 'selected' : '' }}>Disponible</option>
                                <option value="busy" {{ (old('availability_status', $existingData['availability']['status'] ?? '') == 'busy') ? 'selected' : '' }}>Ocupado/a</option>
                                <option value="unavailable" {{ (old('availability_status', $existingData['availability']['status'] ?? '') == 'unavailable') ? 'selected' : '' }}>No disponible</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="availability_notes">Notas de disponibilidad</label>
                            <textarea id="availability_notes" name="availability_notes" class="form-control" rows="3" placeholder="Información adicional sobre tu disponibilidad">{{ old('availability_notes', $existingData['availability']['notes'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
        $(document).ready(function() {
            // Initialize Select2 for basic fields
            $('#country, #timezone').select2({
                placeholder: 'Selecciona una opción',
                allowClear: true
            });

            // Initialize Select2 for language selectors with custom template
            $('.select2').select2({
                templateResult: formatLanguageOption,
                templateSelection: formatLanguageOption
            });

            // Add language pair button handler
            $('#add_language_pair').on('click', function() {
                const sourceLanguage = $('#source_language').select2('data')[0];
                const targetLanguage = $('#target_language').select2('data')[0];

                // Validate source language
                if (!sourceLanguage || !$('#source_language').val()) {
                    alert('Error: El idioma origen es requerido');
                    return;
                }

                // Validate target language
                if (!targetLanguage || !$('#target_language').val()) {
                    alert('Error: El idioma destino es requerido');
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
                    alert('Error: Los idiomas origen y destino no pueden ser iguales');
                    return;
                }

                // Check if this pair already exists
                const pairExists = checkIfPairExists(sourceValue, targetValue);
                if (pairExists) {
                    alert('Error: Este par de idiomas ya existe');
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
        });
    </script>
</body>
</html>
