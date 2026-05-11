<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cuéntanos tu problema de negocio</title>
  <link rel="stylesheet" href="{{ asset('landing-widget/styles.css') }}">
</head>
<body>
  <div class="landing-widget">
    <section class="humano-embed-demo card" aria-label="{{ __('Real-time embed demo') }}">
      <h2>{{ __('Real-time widgets (demo)') }}</h2>
      <p class="description">
        {{ __('Placeholders loaded by JavaScript — same pattern you can paste into static HTML on cPanel. API:') }}
        <code>{{ url('/api/embed/demo') }}</code>
      </p>
      <div class="humano-embed-grid">
        <div data-humano-widget="calendar" data-site="demo"></div>
        <div data-humano-widget="assistant" data-site="demo"></div>
      </div>
    </section>

    <div class="card">
      <h1>Cuéntanos tu problema de negocio</h1>
      <p class="description">
        Inteligencia artificial adaptada a tu operativa de negocio.
        Describe tu situación y te sugeriremos qué hacer.
      </p>
      <textarea id="prompt-input" placeholder="Ej: necesito organizar facturas por cliente y que cada uno vea solo las suyas; quiero automatizar respuestas a leads que llegan por el formulario de la web; tengo datos en Excel que no sé cómo explotar y me gustaría un panel donde ver ventas por producto y por periodo; dependo de una persona para los informes y quiero poder generarlos yo mismo..."></textarea>
      <div style="margin-top: 16px;">
        <button type="button" id="btn-send" class="btn btn-primary">
          <span class="btn-icon" id="btn-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 8.936a2 2 0 0 0 1.437-1.437l1.582-6.135a.5.5 0 0 1 .963 0l1.582 6.135a2 2 0 0 0 1.437 1.437l6.135 1.582a.5.5 0 0 1 0 .963l-6.135 1.582a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M14 9l2 2"/><path d="M16 3v2a2 2 0 0 1-2 2H12"/></svg>
          </span>
          <span class="btn-spinner" id="btn-spinner" aria-hidden="true" style="display: none;">
            <svg class="spinner-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10" stroke-dasharray="12 52"/></svg>
          </span>
          <span id="btn-text">Obtener sugerencias</span>
        </button>
      </div>
      @if(!config('services.landing_widget.team_token'))
      <p class="error-msg" style="margin-top: 12px; font-size: 0.875rem;">Para usar el widget, configura <code>TEAM_TOKEN</code> en tu <code>.env</code> (token de API del equipo en Ajustes).</p>
      @endif
      <p id="error-msg" class="error-msg" style="display: none;"></p>
    </div>

    <div id="result-section" class="card" style="display: none;">
      <h2>Sugerencias</h2>
      <div id="result-content" class="result-content"></div>
      <div class="profundizar" id="profundizar-section">
        <h3>¿Te gustaría profundizar en alguno de estos puntos?</h3>
        <form id="form-profundizar">
          <div class="form-row">
            <label for="name">Nombre *</label>
            <input type="text" id="name" name="name" required>
          </div>
          <div class="form-row">
            <label for="surname">Apellidos</label>
            <input type="text" id="surname" name="surname">
          </div>
          <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
          </div>
          <div class="form-row">
            <label for="phone">Teléfono</label>
            <input type="tel" id="phone" name="phone">
          </div>
          <p style="font-size: 0.875rem; color: #64748b;">Indica al menos email o teléfono.</p>
          <button type="submit" id="btn-profundizar" class="btn btn-primary">Enviar</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.HUMANO_WIDGETS_API_BASE = @json(rtrim(url('/api/embed/demo'), '/'));
  </script>
  <script src="{{ asset('js/humano-widgets.js') }}" defer></script>
  <script>
    window.LANDING_API_BASE_URL = @json(rtrim(config('services.landing_widget.api_url'), '/'));
    window.LANDING_TEAM_TOKEN = @json(config('services.landing_widget.team_token'));
    window.LANDING_PROMPT_NAME = @json(config('services.landing_widget.prompt_name', 'landing'));
    window.LANDING_SUCCESS_URL = @json(config('services.landing_widget.success_url') ?: url()->route('landing.gracias'));
  </script>
  <script src="{{ asset('landing-widget/config.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="{{ asset('landing-widget/app.js') }}"></script>
</body>
</html>
