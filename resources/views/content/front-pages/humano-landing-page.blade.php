@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Humano.app — El sistema operativo de tu negocio digital')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/front-page-landing.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/front-page-pricing.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/front-page-landing.js') }}"></script>
<script src="{{ asset('assets/js/front-page-pricing.js') }}"></script>
@endsection

@section('content')
<div data-bs-spy="scroll" class="scrollspy-example">
  <section id="hero-animation">
    <div id="landingHero" class="section-py landing-hero position-relative">
      <div class="container">
        <div class="hero-text-box text-center">
          <h1 class="text-primary hero-title display-6 fw-bold">El asistente digital que trabaja por ti</h1>
          <h2 class="hero-sub-title h6 mb-4 pb-1">
            El sistema operativo de tu negocio digital.<br class="d-none d-lg-block" />
            Contactos, WhatsApp, IA y procesos sin depender del mega excel.
          </h2>
          <div class="landing-hero-btn d-inline-block position-relative">
            <a href="#landingPricing" class="btn btn-primary btn-lg me-2 mb-2">Ver planes</a>
          </div>
        </div>
        <div id="heroDashboardAnimation" class="hero-animation-img">
          <div id="heroAnimationImg" class="position-relative hero-dashboard-img">
            <img src="{{ asset('assets/img/front-pages/landing-page/hero-dashboard-'.$configData['style'].'.png') }}" alt="Panel Humano" class="animation-img" data-app-light-img="front-pages/landing-page/hero-dashboard-light.png" data-app-dark-img="front-pages/landing-page/hero-dashboard-dark.png" />
            <img src="{{ asset('assets/img/front-pages/landing-page/hero-elements-'.$configData['style'].'.png') }}" alt="" class="position-absolute hero-elements-img animation-img top-0 start-0" data-app-light-img="front-pages/landing-page/hero-elements-light.png" data-app-dark-img="front-pages/landing-page/hero-elements-dark.png" />
          </div>
        </div>
      </div>
    </div>
    <div class="landing-hero-blank"></div>
  </section>

  <section id="landingFeatures" class="section-py landing-features">
    <div class="container">
      <div class="text-center mb-3 pb-1">
        <span class="badge bg-label-primary">Beneficios clave</span>
      </div>
      <h3 class="text-center mb-1">
        <span class="section-title">Todo lo que necesitás</span> para gestionar tu negocio
      </h3>
      <p class="text-center mb-3 mb-md-5 pb-3">
        Menos gestión administrativa y más tiempo con tus clientes.
      </p>
      <div class="features-icon-wrapper row gx-0 gy-4 g-sm-5">
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/laptop.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Sin el mega excel</h5>
          <p class="features-icon-description">Dejá macros opacos y permisos confusos. Toda la empresa ve la misma información con roles claros.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/check.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Los datos son tuyos</h5>
          <p class="features-icon-description">Servidores en Europa. Exportá y llevate tus datos cuando quieras, sin límites.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/rocket.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Menos gestión, más vida</h5>
          <p class="features-icon-description">Procesos probados sin inventarlos vos. Humano.app acelera la operativa desde el primer día.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/paper.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Sistema en la nube</h5>
          <p class="features-icon-description">Accedé desde móvil, tablet u ordenador desde cualquier parte del mundo.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/user.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Control por WhatsApp</h5>
          <p class="features-icon-description">Gestioná tu negocio desde WhatsApp. Vos das las órdenes, Humano.app responde.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ asset('assets/img/front-pages/icons/keyboard.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Consultor IA personalizado</h5>
          <p class="features-icon-description">Respuestas útiles para tu negocio y tu equipo, con tu tono de marca.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-py bg-body">
    <div class="container">
      <div class="row align-items-center gy-5">
        <div class="col-lg-6">
          <span class="badge bg-label-primary mb-3">El empleado del mes</span>
          <h3 class="mb-3"><span class="section-title">Todos los meses</span></h3>
          <p class="mb-0 text-body">
            Si perdés el día con formularios y configuraciones en vez de estar con tus clientes, Humano.app es tu empleado secreto para el trabajo aburrido — con un consultor de IA que te acompaña siempre.
          </p>
        </div>
        <div class="col-lg-6 text-center">
          <img src="{{ asset('assets/img/front-pages/landing-page/cta-dashboard.png') }}" alt="Humano.app" class="img-fluid" />
        </div>
      </div>
    </div>
  </section>

  <section id="landingReviews" class="section-py bg-body landing-reviews pb-0">
    <div class="container">
      <div class="row align-items-center gx-0 gy-4 g-lg-5">
        <div class="col-md-6 col-lg-5 col-xl-3">
          <div class="mb-3 pb-1">
            <span class="badge bg-label-primary">Testimonios</span>
          </div>
          <h3 class="mb-1"><span class="section-title">Esto dicen</span> quienes usan Humano.app</h3>
          <p class="mb-3 mb-md-5">Experiencias reales de equipos que dejaron el excel atrás.</p>
          <div class="landing-reviews-btns">
            <button id="reviews-previous-btn" class="btn btn-label-primary reviews-btn me-3 scaleX-n1-rtl" type="button">
              <i class="ti ti-chevron-left ti-sm"></i>
            </button>
            <button id="reviews-next-btn" class="btn btn-label-primary reviews-btn scaleX-n1-rtl" type="button">
              <i class="ti ti-chevron-right ti-sm"></i>
            </button>
          </div>
        </div>
        <div class="col-md-6 col-lg-7 col-xl-9">
          <div class="swiper-reviews-carousel overflow-hidden mb-5 pb-md-2 pb-md-3">
            <div class="swiper" id="swiper-reviews">
              <div class="swiper-wrapper">
                <div class="swiper-slide">
                  <div class="card h-100">
                    <div class="card-body text-body d-flex flex-column justify-content-between h-100">
                      <p>«Gracias a tener un software personalizado hemos podido llegar a tener 6 oficinas en la empresa. Con el excel esto hubiera sido imposible.»</p>
                      <div>
                        <h6 class="mb-0">Juan Carlos Casal</h6>
                        <p class="small text-muted mb-0">CEO</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card h-100">
                    <div class="card-body text-body d-flex flex-column justify-content-between h-100">
                      <p>«Humano.app son los únicos programadores con los que he trabajado que resuelven problemas de negocio y entienden las necesidades del cliente.»</p>
                      <div>
                        <h6 class="mb-0">Lorena Pérez</h6>
                        <p class="small text-muted mb-0">CEO · Marcas Honestas</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="swiper-slide">
                  <div class="card h-100">
                    <div class="card-body text-body d-flex flex-column justify-content-between h-100">
                      <p>«Si mi abuelo hubiera tenido este programa, ahora yo sería millonario.»</p>
                      <div>
                        <h6 class="mb-0">Javier Fernández</h6>
                        <p class="small text-muted mb-0">Vendedor y artista</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="landingPricing" class="section-py bg-body landing-pricing">
    <div class="container">
      @include('content.front-pages.partials.humano-pricing-plans', [
        'plans' => $plans,
        'showPageHeader' => true,
        'showFlashAlerts' => false,
      ])
    </div>
  </section>

  <section id="landingFunFacts" class="section-py landing-fun-facts">
    <div class="container">
      <div class="row gy-3">
        <div class="col-sm-6 col-lg-3">
          <div class="card border border-label-primary shadow-none">
            <div class="card-body text-center">
              <img src="{{ asset('assets/img/front-pages/icons/laptop.png') }}" alt="" class="mb-2" />
              <h5 class="h2 mb-1">100%</h5>
              <p class="fw-medium mb-0">Tus datos,<br />siempre exportables</p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border border-label-success shadow-none">
            <div class="card-body text-center">
              <img src="{{ asset('assets/img/front-pages/icons/user-success.png') }}" alt="" class="mb-2" />
              <h5 class="h2 mb-1">24/7</h5>
              <p class="fw-medium mb-0">Nube y acceso<br />desde cualquier sitio</p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border border-label-info shadow-none">
            <div class="card-body text-center">
              <img src="{{ asset('assets/img/front-pages/icons/diamond-info.png') }}" alt="" class="mb-2" />
              <h5 class="h2 mb-1">IA</h5>
              <p class="fw-medium mb-0">Consultor<br />personalizado</p>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="card border border-label-warning shadow-none">
            <div class="card-body text-center">
              <img src="{{ asset('assets/img/front-pages/icons/check-warning.png') }}" alt="" class="mb-2" />
              <h5 class="h2 mb-1">WA</h5>
              <p class="fw-medium mb-0">Control por<br />WhatsApp</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="landingFAQ" class="section-py bg-body landing-faq">
    <div class="container">
      <div class="text-center mb-3 pb-1">
        <span class="badge bg-label-primary">FAQ</span>
      </div>
      <h3 class="text-center mb-1">Preguntas <span class="section-title">frecuentes</span></h3>
      <p class="text-center mb-5 pb-3">Respuestas rápidas antes de empezar.</p>
      <div class="row gy-5">
        <div class="col-lg-5">
          <div class="text-center">
            <img src="{{ asset('assets/img/front-pages/landing-page/faq-boy-with-logos.png') }}" alt="" class="faq-image" />
          </div>
        </div>
        <div class="col-lg-7">
          <div class="accordion" id="accordionHumanoLanding">
            <div class="card accordion-item active">
              <h2 class="accordion-header" id="faqOne">
                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne" aria-expanded="true">¿Qué es Humano.app?</button>
              </h2>
              <div id="faqCollapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionHumanoLanding">
                <div class="accordion-body">Es el sistema operativo de tu negocio digital: contactos, agenda, tareas, WhatsApp, facturación y automatización con IA, en una sola plataforma en la nube.</div>
              </div>
            </div>
            <div class="card accordion-item">
              <h2 class="accordion-header" id="faqTwo">
                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo">¿Puedo probar antes de pagar?</button>
              </h2>
              <div id="faqCollapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionHumanoLanding">
                <div class="accordion-body">Sí. Podés suscribirte desde la página de precios con checkout seguro en Stripe.</div>
              </div>
            </div>
            <div class="card accordion-item">
              <h2 class="accordion-header" id="faqThree">
                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faqCollapseThree">¿Los datos son míos?</button>
              </h2>
              <div id="faqCollapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionHumanoLanding">
                <div class="accordion-body">Sí. Podés exportar tu información cuando quieras. La plataforma se aloja en infraestructura europea robusta.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="landingCTA" class="section-py landing-cta p-lg-0 pb-0">
    <div class="container">
      <div class="row align-items-center gy-5 gy-lg-0">
        <div class="col-lg-6 text-center text-lg-start">
          <h6 class="h2 text-primary fw-bold mb-1">¿Listo para empezar?</h6>
          <p class="fw-medium mb-4">Hacemos la tecnología amigable. Hablamos como personas normales.</p>
          <a href="{{ route('pricing') }}" class="btn btn-lg btn-primary mb-2">Ver precios</a>
        </div>
        <div class="col-lg-6 pt-lg-5 text-center text-lg-end">
          <img src="{{ asset('assets/img/front-pages/landing-page/cta-dashboard.png') }}" alt="" class="img-fluid" />
        </div>
      </div>
    </div>
  </section>

  <section id="landingContact" class="section-py bg-body landing-contact">
    <div class="container">
      <div class="text-center mb-3 pb-1">
        <span class="badge bg-label-primary">Contacto</span>
      </div>
      <h3 class="text-center mb-1"><span class="section-title">Hablemos</span> de tu negocio</h3>
      <p class="text-center mb-4 mb-lg-5 pb-md-3">¿Alguna duda? Escríbenos o visitá la web principal.</p>
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="d-flex align-items-center">
                    <div class="badge bg-label-primary rounded p-2 me-3"><i class="ti ti-mail ti-sm"></i></div>
                    <div>
                      <p class="mb-0 text-muted">Email</p>
                      <h5 class="mb-0"><a href="mailto:hola@humano.app" class="text-heading">hola@humano.app</a></h5>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="d-flex align-items-center">
                    <div class="badge bg-label-success rounded p-2 me-3"><i class="ti ti-phone-call ti-sm"></i></div>
                    <div>
                      <p class="mb-0 text-muted">Teléfono</p>
                      <h5 class="mb-0"><a href="tel:+34624159557" class="text-heading">+34 624 15 95 57</a></h5>
                    </div>
                  </div>
                </div>
                <div class="col-12 text-center pt-2">
                  <a href="https://humano.app" class="btn btn-primary" target="_blank" rel="noopener">Ir a humano.app</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
@endsection
