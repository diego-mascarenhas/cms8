@php
  use App\Support\CmsHomeAsset;
  use App\Support\SlashHomeAsset;

  $features = trans('cms_landing.features.items');
  $pageUrl = route('cms.landing');
  $ogImage = url('/'.ltrim(config('variables.ogImage', 'assets/logo.png'), '/'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="{{ __('cms_landing.meta_description') }}">
  <meta name="color-scheme" content="dark">
  <title>{{ __('cms_landing.page_title') }}</title>
  <link rel="canonical" href="{{ $pageUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ $pageUrl }}">
  <meta property="og:title" content="{{ __('cms_landing.page_title') }}">
  <meta property="og:description" content="{{ __('cms_landing.meta_description') }}">
  <meta property="og:image" content="{{ $ogImage }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}">
  <link rel="stylesheet" href="{{ CmsHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}">
</head>
<body class="cms-page" @if (session('slash_lead_sent')) data-slash-show-reward-modal @endif>

  <header class="cms-nav">
    <div class="cms-container cms-nav-inner">
      <a href="{{ route('humano') }}" class="cms-nav-brand" aria-label="{{ config('app.name') }}">
        <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
      </a>
      <ul class="cms-nav-links">
        <li><a href="#admin">{{ __('cms_landing.nav.admin') }}</a></li>
        <li><a href="#usuario">{{ __('cms_landing.nav.user') }}</a></li>
        <li><a href="#funciones">{{ __('cms_landing.nav.features') }}</a></li>
        <li><a href="{{ $presentationUrl }}">{{ __('cms_landing.nav.guide') }}</a></li>
      </ul>
      <div class="cms-nav-actions">
        <a href="{{ route('login') }}" class="cms-btn cms-btn-ghost">{{ __('cms_landing.nav.login') }}</a>
        <a href="#empezar" class="cms-btn cms-btn-primary">{{ __('cms_landing.nav.cta') }}</a>
      </div>
    </div>
  </header>

  <main>
    <section id="empezar" class="cms-hero">
      <span class="cms-hero-glow cms-hero-glow-a" aria-hidden="true"></span>
      <span class="cms-hero-glow cms-hero-glow-b" aria-hidden="true"></span>
      <div class="cms-container cms-hero-grid">
        <div>
          <span class="cms-eyebrow">{{ __('cms_landing.hero.eyebrow') }}</span>
          <h1 class="cms-h1">{{ __('cms_landing.hero.title') }}</h1>
          <p class="cms-lead">{{ __('cms_landing.hero.lead') }}</p>
          <form class="slash-hero-form cms-hero-form" action="{{ route('cms.lead.store') }}" method="POST" data-slash-lead-form novalidate>
            @csrf
            <input type="hidden" name="source" value="hero">
            <input
              type="email"
              name="email"
              value="{{ old('email') }}"
              placeholder="{{ __('slash_landing.hero.email_placeholder') }}"
              aria-label="Email"
              autocomplete="email"
              inputmode="email"
              spellcheck="false"
              data-slash-email-input
              aria-describedby="cms-lead-feedback-hero"
            >
            <button type="submit" class="slash-btn slash-btn-accent">{{ __('slash_landing.hero.cta') }}</button>
            <p
              class="slash-form-feedback"
              id="cms-lead-feedback-hero"
              data-slash-form-feedback
              role="alert"
              @if ($errors->has('email') && old('source', 'hero') === 'hero') data-slash-form-feedback-visible @endif
            >@if ($errors->has('email') && old('source', 'hero') === 'hero'){{ $errors->first('email') }}@endif</p>
          </form>
          <p class="slash-hero-note cms-hero-note">{{ __('slash_landing.hero.note') }}</p>
          <div class="cms-hero-actions">
            <a href="{{ $presentationUrl }}" class="cms-btn cms-btn-ghost">{{ __('cms_landing.hero.cta_secondary') }}</a>
            <a href="{{ route('cms.newsletter') }}" class="cms-btn cms-btn-ghost">{{ __('cms_landing.hero.cta_newsletter') }}</a>
          </div>
        </div>
        <div class="cms-hero-visual" aria-hidden="true">
          <div class="cms-browser">
            <div class="cms-browser-bar">
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
            </div>
            <div class="cms-browser-body">
              <div class="cms-browser-side">
                <span class="active"></span>
                <span></span>
                <span></span>
                <span></span>
              </div>
              <div class="cms-browser-main">
                <h4>Nueva entrada</h4>
                <div class="cms-fake-input">Lanzamiento de nuestra nueva web</div>
                <div class="cms-fake-input">lanzamiento-de-nuestra-nueva-web</div>
                <div class="cms-fake-editor">Presentamos el blog integrado con WordPress…</div>
              </div>
              <div class="cms-browser-aside">
                <strong style="font-size:9px;display:block;margin-bottom:6px;">Categorías</strong>
                <div class="cms-check"><i class="ti ti-check"></i> Noticias</div>
                <div class="cms-check"><i class="ti ti-check"></i> Producto</div>
                <div class="cms-check"><i class="ti ti-square"></i> Empresa</div>
              </div>
            </div>
          </div>
          <div class="cms-phone">
            <div class="cms-phone-screen">
              <div class="cms-wa-head">
                <span class="cms-wa-avatar"></span>
                Humano · Equipo
              </div>
              <div class="cms-wa-body">
                <div class="cms-bubble cms-bubble-out">¿Qué páginas del CMS tenemos?</div>
                <div class="cms-bubble cms-bubble-in">Páginas (3):<br>· Contacto<br>· Sobre nosotros<br>· Página de ejemplo</div>
                <div class="cms-bubble cms-bubble-out">Publica la de Contacto mañana</div>
                <div class="cms-bubble cms-bubble-in">Listo. La página Contacto queda programada.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container">
        <div class="cms-section-head">
          <span class="cms-eyebrow">{{ __('cms_landing.roles.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('cms_landing.roles.title') }}</h2>
          <p class="cms-lead">{{ __('cms_landing.roles.lead') }}</p>
        </div>
        <div class="cms-role-grid">
          <article class="cms-role-card">
            <span class="cms-badge cms-badge-admin">{{ __('cms_landing.roles.admin_badge') }}</span>
            <h3>{{ __('cms_landing.roles.admin_title') }}</h3>
            <p>{{ __('cms_landing.roles.admin_text') }}</p>
          </article>
          <article class="cms-role-card">
            <span class="cms-badge cms-badge-user">{{ __('cms_landing.roles.user_badge') }}</span>
            <h3>{{ __('cms_landing.roles.user_title') }}</h3>
            <p>{{ __('cms_landing.roles.user_text') }}</p>
          </article>
        </div>
      </div>
    </section>

    <section id="admin" class="cms-section">
      <div class="cms-container cms-flow">
        <div class="cms-flow-copy">
          <span class="cms-eyebrow">{{ __('cms_landing.admin_section.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('cms_landing.admin_section.title') }}</h2>
          <p class="cms-lead">{{ __('cms_landing.admin_section.lead') }}</p>
        </div>
        <div class="cms-flow-visual">
          <div class="cms-phone" style="max-width:300px;margin:0 auto;">
            <div class="cms-phone-screen">
              <div class="cms-wa-head"><span class="cms-wa-avatar"></span> Administrador</div>
              <div class="cms-wa-body">
                <div class="cms-bubble cms-bubble-out">Lista las entradas publicadas del CMS</div>
                <div class="cms-bubble cms-bubble-in">Entradas (5):<br>· Lanzamiento web<br>· Cómo empezar con el CMS<br>· CF Sync Demo…</div>
                <div class="cms-bubble cms-bubble-out">Cambia el título del primero a "Web renovada"</div>
                <div class="cms-bubble cms-bubble-in">Se actualizó la entrada id 5 "Web renovada".</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="usuario" class="cms-section">
      <div class="cms-container cms-flow cms-flow-reverse">
        <div class="cms-flow-copy">
          <span class="cms-eyebrow">{{ __('cms_landing.user_section.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('cms_landing.user_section.title') }}</h2>
          <p class="cms-lead">{{ __('cms_landing.user_section.lead') }}</p>
        </div>
        <div class="cms-flow-visual">
          <div class="cms-site-mock">
            <div class="cms-site-nav">
              <span>Inicio</span>
              <span>Servicios</span>
              <span>Contacto</span>
            </div>
            <div class="cms-site-hero">
              <h4>Bienvenido a tu sitio</h4>
              <p>Contenido sincronizado desde Humano y WordPress.</p>
            </div>
            <div class="cms-chat-widget">
              <div class="cms-bubble cms-bubble-out">¿Tienen página de contacto?</div>
              <div class="cms-bubble cms-bubble-in">Sí. La página "Contacto" está publicada con horario y formulario.</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container cms-flow">
        <div class="cms-flow-copy">
          <span class="cms-eyebrow">{{ __('cms_landing.panel_section.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('cms_landing.panel_section.title') }}</h2>
          <p class="cms-lead">{{ __('cms_landing.panel_section.lead') }}</p>
        </div>
        <div class="cms-flow-visual">
          <div class="cms-browser">
            <div class="cms-browser-bar">
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
            </div>
            <div class="cms-browser-body" style="grid-template-columns:52px 1fr 120px;min-height:200px;">
              <div class="cms-browser-side">
                <span class="active"></span>
                <span></span>
                <span></span>
              </div>
              <div class="cms-browser-main">
                <h4>Entradas · CMS</h4>
                <div class="cms-fake-input" style="margin-bottom:6px;">CF Sync Demo · publicada</div>
                <div class="cms-fake-input" style="margin-bottom:6px;">Cómo empezar · publicada</div>
                <div class="cms-fake-input">Borrador: Aviso legal</div>
              </div>
              <div class="cms-browser-aside">
                <strong style="font-size:9px;">Sync</strong>
                <p style="margin:6px 0 0;font-size:9px;color:#71dd37;">● WordPress OK</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="funciones" class="cms-section">
      <div class="cms-container">
        <div class="cms-section-head">
          <span class="cms-eyebrow">{{ __('cms_landing.features.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('cms_landing.features.title') }}</h2>
        </div>
        <div class="cms-feature-grid">
          @foreach ($features as $index => $feature)
            <article class="cms-feature-card">
              <div class="cms-feature-icon">
                @php
                  $icons = ['ti-refresh', 'ti-puzzle', 'ti-message-chatbot', 'ti-shield-check'];
                @endphp
                <i class="ti {{ $icons[$index] ?? 'ti-star' }}"></i>
              </div>
              <h3>{{ $feature['title'] }}</h3>
              <p>{{ $feature['text'] }}</p>
            </article>
          @endforeach
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container">
        <div class="cms-cta">
          <h2 class="cms-h2">{{ __('cms_landing.cta.title') }}</h2>
          <p class="cms-lead">{{ __('cms_landing.cta.lead') }}</p>
          <form class="slash-hero-form cms-hero-form cms-cta-form" action="{{ route('cms.lead.store') }}" method="POST" data-slash-lead-form novalidate>
            @csrf
            <input type="hidden" name="source" value="cta">
            <input
              type="email"
              name="email"
              value="{{ old('email') }}"
              placeholder="{{ __('slash_landing.hero.email_placeholder') }}"
              aria-label="Email"
              autocomplete="email"
              inputmode="email"
              spellcheck="false"
              data-slash-email-input
              aria-describedby="cms-lead-feedback-cta"
            >
            <button type="submit" class="slash-btn slash-btn-accent">{{ __('cms_landing.cta.button') }}</button>
            <p
              class="slash-form-feedback"
              id="cms-lead-feedback-cta"
              data-slash-form-feedback
              role="alert"
              @if ($errors->has('email') && old('source') === 'cta') data-slash-form-feedback-visible @endif
            >@if ($errors->has('email') && old('source') === 'cta'){{ $errors->first('email') }}@endif</p>
          </form>
          <p class="slash-hero-note cms-hero-note">{{ __('slash_landing.hero.note') }}</p>
          <div class="cms-cta-actions">
            <a href="{{ $presentationUrl }}" class="cms-btn cms-btn-ghost">{{ __('cms_landing.cta.secondary') }}</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="cms-footer">
    <div class="cms-container humano-brand-footer">
      @include('homes.partials.brand-footer-bottom')
    </div>
  </footer>

  @include('homes.slash.partials.lead-modal', ['leadStoreUrl' => route('cms.lead.store')])
  @include('homes.slash.partials.reward-modal', ['rewardCtaUrl' => route('pricing')])

  <script src="{{ SlashHomeAsset::url('js/landing.js') }}"></script>
</body>
</html>
