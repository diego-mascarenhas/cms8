@php
  use App\Support\SlashHomeAsset;

  $slashImg = static fn (string $path): string => SlashHomeAsset::url('img/'.$path);

  $planImages = [
    'assistant' => $slashImg('plans/assistant.png'),
    'hunter' => $slashImg('plans/hunter.png'),
    'business' => $slashImg('plans/business.png'),
    'mentor' => $slashImg('plans/mentor.png'),
  ];

  $features = [
    ['icon' => 'laptop.png', 'title' => 'Sin el mega excel', 'text' => 'Dejá macros opacos y permisos confusos. Toda la empresa ve la misma información con roles claros.'],
    ['icon' => 'check.png', 'title' => 'Los datos son tuyos', 'text' => 'Servidores en Europa. Exportá y llevate tus datos cuando quieras, sin límites.'],
    ['icon' => 'rocket.png', 'title' => 'Menos gestión, más vida', 'text' => 'Procesos probados sin inventarlos vos. Humano.app acelera la operativa desde el primer día.'],
    ['icon' => 'paper.png', 'title' => 'Sistema en la nube', 'text' => 'Accedé desde móvil, tablet u ordenador desde cualquier parte del mundo.'],
    ['icon' => 'user.png', 'title' => 'Control por WhatsApp', 'text' => 'Gestioná tu negocio desde WhatsApp. Vos das las órdenes, Humano.app responde.'],
    ['icon' => 'keyboard.png', 'title' => 'Consultor IA personalizado', 'text' => 'Respuestas útiles para tu negocio y tu equipo, con tu tono de marca.'],
  ];

  $capabilities = [
    ['title' => 'WhatsApp integrado', 'text' => 'Conectá tu línea, respondé desde el panel y automatizá lo repetitivo sin salir de Humano.'],
    ['title' => 'Roles y permisos', 'text' => 'Definí quién ve qué: ventas, operaciones, finanzas. Sin macros ni hojas compartidas a ciegas.'],
    ['title' => 'Multi-equipo', 'text' => 'Cambiá entre espacios de trabajo como entre servidores: cada negocio con su marca, datos y flujos.'],
  ];

  $tools = [
    ['title' => 'Panel Hoy', 'text' => 'Vista diaria de pendientes, citas y conversaciones activas.'],
    ['title' => 'Prospección', 'text' => 'Buscá perfiles por cargo y ubicación e importalos a tu agenda.'],
    ['title' => 'Tareas y tablero', 'text' => 'Lista y kanban por estado, responsables y fechas.'],
    ['title' => 'Landings', 'text' => 'Convertí visitas en contactos con páginas enlazadas al CRM.'],
    ['title' => 'Facturación', 'text' => 'Emití y seguí cobros sin cambiar de herramienta.'],
    ['title' => 'Exportación', 'text' => 'Tus datos son tuyos: exportá cuando quieras, sin límites.'],
  ];

  $trustCards = [
    [
      'quote' => 'Dejamos el Excel compartido y por fin todo el equipo ve lo mismo. La configuración inicial nos llevó una tarde.',
      'name' => 'María G.',
      'role' => 'Directora, estudio creativo',
      'initials' => 'MG',
      'stats' => [['value' => '24/7', 'label' => 'acceso en la nube'], ['value' => '−40%', 'label' => 'tiempo admin']],
    ],
    [
      'quote' => 'WhatsApp conectado al panel cambió cómo respondemos. La IA mantiene nuestro tono sin sonar genérica.',
      'name' => 'Carlos R.',
      'role' => 'Fundador, agencia digital',
      'initials' => 'CR',
      'stats' => [['value' => '1', 'label' => 'plataforma unificada'], ['value' => '0', 'label' => 'macros confusos']],
    ],
    [
      'quote' => 'Facturas, contactos y campañas en un solo lugar. Dejamos de saltar entre cinco herramientas cada mañana.',
      'name' => 'Laura P.',
      'role' => 'COO, consultora B2B',
      'initials' => 'LP',
      'stats' => [['value' => 'EU', 'label' => 'datos en Europa'], ['value' => '100%', 'label' => 'exportables']],
    ],
  ];

  $testimonials = [
    ['text' => 'Las necesidades de un negocio moderno cambian rápido, y plataformas como Humano son las que pueden responder a eso.', 'author' => 'Equipo fundador', 'company' => 'Humano.app'],
    ['text' => 'Siguen lanzando mejoras que realmente uso cada día: chat, tareas y contactos en el mismo flujo.', 'author' => 'Usuario Business', 'company' => 'Plan Business'],
    ['text' => 'Con Humano veo todo lo que pasa en el negocio. Hace que mirar los números y el equipo sea mucho más claro.', 'author' => 'Usuario Mentor', 'company' => 'Plan Mentor'],
  ];

  $securityItems = [
    ['title' => 'Infraestructura europea', 'text' => 'La plataforma se aloja en servidores en Europa, con controles de acceso y respaldo operativo.'],
    ['title' => 'Autenticación segura', 'text' => 'Cuentas y acciones sensibles protegidas con inicio de sesión y permisos granulares por rol.'],
    ['title' => 'Tus datos, tu propiedad', 'text' => 'Exportá tu información cuando quieras. Sin bloqueos ni dependencia de formatos propietarios.'],
    ['title' => 'Permisos por equipo', 'text' => 'Definí quién edita, quién ve y quién opera en cada módulo según la gobernanza de tu empresa.'],
  ];

  $faqs = [
    ['q' => '¿Qué es Humano.app?', 'a' => 'Es el sistema operativo de tu negocio digital: contactos, agenda, tareas, WhatsApp, facturación y automatización con IA, en una sola plataforma en la nube.'],
    ['q' => '¿Puedo probar antes de pagar?', 'a' => 'Sí. Podés suscribirte desde la página de precios con checkout seguro en Stripe.'],
    ['q' => '¿Los datos son míos?', 'a' => 'Sí. Podés exportar tu información cuando quieras. La plataforma se aloja en infraestructura europea robusta.'],
    ['q' => '¿Por qué usar Humano en lugar de Excel?', 'a' => 'Porque centraliza contactos, comunicación, tareas y cobros con roles claros. Menos errores, menos tiempo administrativo y más foco en clientes.'],
    ['q' => '¿Es seguro para mi negocio?', 'a' => 'Sí. Usamos autenticación estándar, permisos por rol e infraestructura en Europa. Tus datos permanecen bajo tu control y son exportables.'],
  ];

  $metrics = [
    ['value' => '24/7', 'label' => 'Plataforma en la nube', 'count' => null],
    ['value' => '6', 'label' => 'Pasos para configurar', 'count' => 6, 'suffix' => ''],
    ['value' => '4', 'label' => 'Planes escalables', 'count' => 4, 'suffix' => ''],
    ['value' => '100%', 'label' => 'Datos exportables', 'count' => 100, 'suffix' => '%'],
  ];

  $pricingTiers = [
    [
      'name' => 'Assistant',
      'price' => 'Desde',
      'suffix' => '/mes',
      'description' => 'Lo esencial para automatizar el día a día: chat, contactos, calendario y tareas.',
      'featured' => false,
      'cta' => 'Empezar',
      'features' => [
        'Chat por WhatsApp y prompts con tu tono',
        'Calendario, contactos y tareas',
        'Panel Hoy y ajustes operativos',
        'Exportación de datos sin límites',
      ],
    ],
    [
      'name' => 'Business',
      'price' => 'Escala',
      'suffix' => 'completa',
      'description' => 'Marketing, ventas y cobros enlazados. El plan más elegido para equipos en crecimiento.',
      'featured' => true,
      'cta' => 'Ver precios',
      'features' => [
        'Todo lo de Hunter y Assistant',
        'Facturas, cobros y módulo financiero',
        'Landings y campañas integradas',
        'Dashboard y visibilidad del equipo',
      ],
    ],
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="El sistema operativo de tu negocio digital. Contactos, WhatsApp, IA y procesos sin depender del mega excel.">
  <meta name="color-scheme" content="dark">
  <title>Humano.app — El asistente digital que trabaja por ti</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ SlashHomeAsset::url('css/landing.css') }}">
  @if(config('app.google_analytics_id'))
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.google_analytics_id') }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('app.google_analytics_id') }}');
  </script>
  @endif
</head>
<body class="slash-page">

  <header class="slash-nav">
    <div class="slash-container slash-nav-inner">
      <a href="{{ url('/') }}" class="slash-nav-brand" aria-label="{{ config('app.name') }}">
        <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
      </a>

      <ul class="slash-nav-links" id="slashNavLinks">
        <li><a href="#historias-planes">En acción</a></li>
        <li><a href="#beneficios">Beneficios</a></li>
        <li><a href="#guias">Guías</a></li>
        <li><a href="#planes">Planes</a></li>
        <li><a href="#precios">Precios</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>

      <div class="slash-nav-actions">
        <a href="{{ route('login') }}" class="slash-btn slash-btn-ghost slash-nav-cta-desktop">Iniciar sesión</a>
        <a href="{{ route('pricing') }}" class="slash-btn slash-btn-dark">Empezar</a>
        <button class="slash-nav-toggle" type="button" data-slash-nav-toggle aria-controls="slashNavLinks" aria-expanded="false" aria-label="Abrir menú">
          @include('homes.slash.partials.icon', ['name' => 'menu'])
        </button>
      </div>
    </div>
  </header>

  <main>
    <section class="slash-hero" data-slash-spotlight>
      <div class="slash-hero-glows" aria-hidden="true">
        <span class="slash-glow slash-glow-green"></span>
        <span class="slash-glow slash-glow-violet"></span>
        <span class="slash-glow slash-glow-cyan"></span>
      </div>
      <div class="slash-hero-grid" aria-hidden="true"></div>
      <div class="slash-container">
        <span class="slash-eyebrow slash-shine-badge">Humano.app</span>
        <h1>Un <em><span class="slash-hero-shine">estándar superior</span></em><br>en gestión de negocio</h1>
        <p class="slash-lead">Contactos, WhatsApp, IA, facturación y más. Todo en una plataforma.</p>
        <form class="slash-hero-form" action="{{ route('pricing') }}" method="GET">
          <input type="email" name="email" placeholder="tu@email.com" aria-label="Email">
          <button type="submit" class="slash-btn slash-btn-accent">Empezar</button>
        </form>
        <p class="slash-hero-note">Checkout seguro con Stripe. Cancelá cuando quieras.</p>
        <div class="slash-hero-shot slash-glow-frame">
          <img src="{{ $slashImg('landing-page/hero-elements-dark.png') }}" alt="Panel Humano" width="3612" height="2328" loading="eager" decoding="async">
        </div>
      </div>
    </section>

    <section class="slash-statband">
      <div class="slash-container">
        <strong>Miles de tareas gestionadas</strong>
        <span>por equipos que eligieron dejar el mega excel</span>
      </div>
    </section>

    <section id="producto" class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Confianza</span>
          <h2 class="slash-h2">Elegido por equipos que quieren moverse más rápido</h2>
          <p class="slash-lead">Desde freelancers hasta pymes, equipos eligen Humano para operar con menos fricción.</p>
        </div>
        <div class="slash-trust-grid">
          @foreach ($trustCards as $card)
            <div class="slash-trust-card">
              <blockquote>{{ $card['quote'] }}</blockquote>
              <div class="slash-trust-meta">
                <span class="slash-trust-avatar">{{ $card['initials'] }}</span>
                <div>
                  <strong>{{ $card['name'] }}</strong>
                  <span>{{ $card['role'] }}</span>
                </div>
              </div>
              <div class="slash-trust-highlight">
                @foreach ($card['stats'] as $stat)
                  <div>
                    <strong>{{ $stat['value'] }}</strong>
                    <span>{{ $stat['label'] }}</span>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    @include('homes.slash.partials.plan-stories', [
      'landingPlans' => $landingPlans,
      'planImages' => $planImages,
    ])

    <section class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Capacidades modernas</span>
          <h2 class="slash-h2">Amplificado con herramientas actuales</h2>
          <p class="slash-lead">WhatsApp, IA, permisos granulares y espacios de trabajo para escalar sin perder control.</p>
        </div>
        <div class="slash-cap-grid">
          @foreach ($capabilities as $cap)
            <div class="slash-cap-card">
              <h3>{{ $cap['title'] }}</h3>
              <p>{{ $cap['text'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section id="beneficios" class="slash-section slash-guides">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Beneficios clave</span>
          <h2 class="slash-h2">Todo lo que necesitás para gestionar tu negocio</h2>
          <p class="slash-lead">Menos gestión administrativa y más tiempo con tus clientes.</p>
        </div>
        <div class="slash-grid">
          @foreach ($features as $feature)
            <div class="slash-card">
              <div class="slash-card-icon">
                <img src="{{ $slashImg('icons/'.$feature['icon']) }}" alt="">
              </div>
              <h3>{{ $feature['title'] }}</h3>
              <p>{{ $feature['text'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="slash-section slash-testimonials">
      <div class="slash-container">
        <div class="slash-quote-grid">
          @foreach ($testimonials as $quote)
            <blockquote class="slash-quote">
              <p>"{{ $quote['text'] }}"</p>
              <footer>
                <strong>{{ $quote['author'] }}</strong>
                {{ $quote['company'] }}
              </footer>
            </blockquote>
          @endforeach
        </div>
      </div>
    </section>

    <section class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Escala y rendimiento</span>
          <h2 class="slash-h2">Métricas que importan</h2>
        </div>
        <div class="slash-metrics">
          @foreach ($metrics as $metric)
            <div class="slash-metric" @if (empty($metric['count'])) data-slash-metric-pulse @endif>
              <strong
                @if (! empty($metric['count']))
                  data-slash-counter="{{ $metric['count'] }}"
                  data-slash-counter-suffix="{{ $metric['suffix'] ?? '' }}"
                @endif
              >{{ $metric['value'] }}</strong>
              <span>{{ $metric['label'] }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="slash-section slash-guides">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Herramientas inteligentes</span>
          <h2 class="slash-h2">Completá cualquier tarea en pocos clics</h2>
          <p class="slash-lead">Módulos pensados para el día a día de tu equipo.</p>
        </div>
        <div class="slash-tools-grid">
          @foreach ($tools as $tool)
            <div class="slash-tool-card">
              <h3>{{ $tool['title'] }}</h3>
              <p>{{ $tool['text'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Seguridad</span>
          <h2 class="slash-h2">Seguro por diseño</h2>
          <p class="slash-lead">Infraestructura europea, permisos granulares y control total de tus datos.</p>
        </div>
        <div class="slash-security-grid">
          @foreach ($securityItems as $item)
            <div class="slash-security-card">
              <h3>{{ $item['title'] }}</h3>
              <p>{{ $item['text'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section id="guias" class="slash-section slash-foundation">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Guías</span>
          <h2 class="slash-h2">Aprendé a usar Humano</h2>
          <p class="slash-lead">Presentaciones paso a paso por módulo. Empezamos por cómo funciona la plataforma.</p>
        </div>
        <div class="slash-grid">
          @foreach ($guidePresentations as $guide)
            <a href="{{ $guide['url'] }}" class="slash-card slash-guide-card">
              <div class="slash-guide-top">
                <span class="slash-guide-badge">@include('homes.slash.partials.icon', ['name' => $guide['icon']])</span>
                <div>
                  <p class="slash-guide-sub">{{ $guide['subtitle'] }}</p>
                  <h3>{{ $guide['title'] }}</h3>
                </div>
              </div>
              <p>{{ $guide['description'] }}</p>
              <span class="slash-guide-link">Ver presentación @include('homes.slash.partials.icon', ['name' => 'arrow-right'])</span>
            </a>
          @endforeach
        </div>
      </div>
    </section>

    <section id="planes" class="slash-section slash-plans">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">{{ __('humano_pricing.landing_plans_badge') }}</span>
          <h2 class="slash-h2">{{ __('humano_pricing.landing_plans_title') }}</h2>
          <p class="slash-lead">{{ __('humano_pricing.landing_plans_subtitle') }}</p>
        </div>

        @foreach ($landingPlans as $index => $plan)
          @php
            $planId = $plan['id'];
            $planImage = $planImages[$planId] ?? $planImages['assistant'];
          @endphp
          <div class="slash-plan-row {{ $index % 2 === 1 ? 'is-reversed' : '' }}">
            <div class="slash-plan-copy">
              <h3>
                {{ __('humano_pricing.plans.'.$planId.'.name') }}
                @if (! empty($plan['popular']))
                  <span class="slash-pill">{{ __('humano_pricing.most_popular') }}</span>
                @endif
              </h3>
              <p>{{ __('humano_pricing.plans.'.$planId.'.description') }}</p>
              <ul class="slash-plan-features">
                @foreach (trans('humano_pricing.plans.'.$planId.'.features') as $planFeature)
                  <li>@include('homes.slash.partials.icon', ['name' => 'check']) <span>{{ $planFeature }}</span></li>
                @endforeach
              </ul>
              <a href="{{ route('pricing') }}#plan-{{ $planId }}" class="slash-btn slash-btn-accent">
                {{ __('humano_pricing.landing_plans_cta') }} @include('homes.slash.partials.icon', ['name' => 'arrow-right'])
              </a>
            </div>
            <div class="slash-plan-visual">
              <img src="{{ $planImage }}" alt="{{ __('humano_pricing.plans.'.$planId.'.name') }}">
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <section id="precios" class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Precios</span>
          <h2 class="slash-h2">Precios transparentes para tu negocio</h2>
          <p class="slash-lead">Elegí el plan que mejor se adapte. Los precios finales y el checkout están en la página de planes.</p>
        </div>
        <div class="slash-pricing-grid">
          @foreach ($pricingTiers as $tier)
            <div class="slash-pricing-card {{ ! empty($tier['featured']) ? 'is-featured' : '' }}">
              <h3>{{ $tier['name'] }}</h3>
              <div class="slash-pricing-price">{{ $tier['price'] }} <small>{{ $tier['suffix'] }}</small></div>
              <p class="slash-pricing-desc">{{ $tier['description'] }}</p>
              <ul class="slash-pricing-features">
                @foreach ($tier['features'] as $feature)
                  <li>@include('homes.slash.partials.icon', ['name' => 'check']) <span>{{ $feature }}</span></li>
                @endforeach
              </ul>
              <a href="{{ route('pricing') }}" class="slash-btn {{ ! empty($tier['featured']) ? 'slash-btn-accent' : 'slash-btn-outline' }}">{{ $tier['cta'] }}</a>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section id="faq" class="slash-section slash-faq">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">FAQ</span>
          <h2 class="slash-h2">Preguntas frecuentes</h2>
          <p class="slash-lead">¿No encontrás la respuesta? <a href="#contacto" style="color: var(--slash-accent);">Escribinos</a>.</p>
        </div>
        <div class="slash-faq-list">
          @foreach ($faqs as $faq)
            <div class="slash-faq-item" data-slash-faq>
              <button type="button" class="slash-faq-q" aria-expanded="false">
                {{ $faq['q'] }}
                @include('homes.slash.partials.icon', ['name' => 'plus'])
              </button>
              <div class="slash-faq-a">
                <div class="slash-faq-a-inner">{{ $faq['a'] }}</div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="slash-section">
      <div class="slash-container">
        <div class="slash-cta-card">
          <div class="slash-cta-copy">
            <h2>Empezá en menos de 10 minutos</h2>
            <p>Unite a los equipos que ya operan su negocio con Humano.app.</p>
            <form class="slash-hero-form" action="{{ route('pricing') }}" method="GET">
              <input type="email" name="email" placeholder="tu@email.com" aria-label="Email">
              <button type="submit" class="slash-btn slash-btn-accent">Empezar gratis</button>
            </form>
          </div>
          <div class="slash-cta-shot">
            <img src="{{ $slashImg('landing-page/hero-dashboard-dark.png') }}" alt="Panel Humano" width="2040" height="1483" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </section>

    <section id="contacto" class="slash-section slash-foundation">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">Contacto</span>
          <h2 class="slash-h2">Hablemos de tu negocio</h2>
          <p class="slash-lead">¿Alguna duda? Escríbenos o visitá la web principal.</p>
        </div>
        <div class="slash-contact-grid">
          <div class="slash-contact-card">
            <span class="slash-card-icon">@include('homes.slash.partials.icon', ['name' => 'mail'])</span>
            <div>
              <span>Email</span>
              <strong><a href="mailto:hola@humano.app">hola@humano.app</a></strong>
            </div>
          </div>
          <div class="slash-contact-card">
            <span class="slash-card-icon">@include('homes.slash.partials.icon', ['name' => 'phone'])</span>
            <div>
              <span>Teléfono</span>
              <strong><a href="tel:+34624159557">+34 624 15 95 57</a></strong>
            </div>
          </div>
        </div>
        <div class="slash-contact-actions">
          <a href="{{ route('humano') }}" class="slash-btn slash-btn-outline slash-btn-lg">Ver landing clásica</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="slash-footer">
    <div class="slash-container">
      <div class="slash-footer-top">
        <div class="slash-footer-brand">
          <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
          <p>El sistema operativo de tu negocio digital. Contactos, WhatsApp, IA y procesos sin depender del mega excel.</p>
        </div>
        <div>
          <h4>Producto</h4>
          <ul>
            <li><a href="#historias-planes">En acción</a></li>
            <li><a href="#beneficios">Beneficios</a></li>
            <li><a href="#planes">Planes</a></li>
            <li><a href="{{ route('pricing') }}">Precios</a></li>
          </ul>
        </div>
        <div>
          <h4>Recursos</h4>
          <ul>
            <li><a href="#guias">Guías</a></li>
            <li><a href="#faq">FAQ</a></li>
            <li><a href="{{ route('humano') }}">Landing clásica</a></li>
            <li><a href="https://humano.app" target="_blank" rel="noopener">humano.app</a></li>
          </ul>
        </div>
        <div>
          <h4>Cuenta</h4>
          <ul>
            <li><a href="{{ route('login') }}">Iniciar sesión</a></li>
            <li><a href="{{ route('pricing') }}">Empezar</a></li>
            <li><a href="#contacto">Contacto</a></li>
          </ul>
        </div>
      </div>
      <div class="slash-footer-bottom">
        <span>© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</span>
        <span>Hecho con foco humano.</span>
      </div>
    </div>
  </footer>

  <script src="{{ SlashHomeAsset::url('vendor/gsap/gsap.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('vendor/gsap/ScrollTrigger.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('vendor/lenis/lenis.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('js/landing.js') }}"></script>
</body>
</html>
