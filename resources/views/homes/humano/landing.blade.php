@php
use App\Support\HumanoHomeAsset;

$configData = Helper::appClasses();
$humanoImg = static fn (string $path): string => HumanoHomeAsset::url('img/'.$path);
$heroImageStyle = $configData['style'] === 'dark' ? 'dark' : 'light';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'HumanoApp')
@section('ogTitle', __('slash_landing.og_title'))
@section('metaDescription', __('slash_landing.meta_description'))

@section('vendor-style')
<link rel="stylesheet" href="{{ HumanoHomeAsset::url('vendor/swiper/swiper.css') }}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ HumanoHomeAsset::url('css/landing.css') }}" />
<link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}" />
<link rel="stylesheet" href="{{ asset('homes/shared/css/landing-highlight.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ HumanoHomeAsset::url('vendor/swiper/swiper.js') }}"></script>
<script src="{{ HumanoHomeAsset::url('vendor/gsap/gsap.min.js') }}"></script>
<script src="{{ HumanoHomeAsset::url('vendor/gsap/ScrollTrigger.min.js') }}"></script>
<script src="{{ HumanoHomeAsset::url('vendor/lenis/lenis.min.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ HumanoHomeAsset::url('js/landing.js') }}"></script>
@endsection

@section('content')
<div class="scrollspy-example humano-landing-page">
  <section id="hero-animation">
    <div id="landingHero" class="section-py landing-hero position-relative">
      <div class="container">
        <div class="hero-text-box text-center">
          <h1 class="text-primary hero-title display-6 fw-bold">{{ __('slash_landing.hero.title') }}</h1>
          <h2 class="hero-sub-title h6 mb-4 pb-1">
            {{ __('slash_landing.hero.lead') }}
          </h2>
        </div>
        <div id="heroDashboardAnimation" class="hero-animation-img">
          <div id="heroAnimationImg" class="position-relative hero-dashboard-img">
            <img src="{{ $humanoImg('landing-page/hero-elements-'.$heroImageStyle.'.png') }}" alt="Panel Humano" class="animation-img humano-landing-hero-img" width="3612" height="2328" loading="eager" decoding="async" data-humano-light-img="{{ $humanoImg('landing-page/hero-elements-light.png') }}" data-humano-dark-img="{{ $humanoImg('landing-page/hero-elements-dark.png') }}" />
          </div>
        </div>
      </div>
    </div>
    <div class="landing-hero-blank"></div>
  </section>

  <section id="landingHighlight" class="section-py pt-0 pb-0">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5 text-center">
              @include('homes.shared.partials.hero-highlight')
            </div>
          </div>
        </div>
      </div>
    </div>
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
            <img src="{{ $humanoImg('icons/laptop.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Sin el mega excel</h5>
          <p class="features-icon-description">Dejá macros opacos y permisos confusos. Toda la empresa ve la misma información con roles claros.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ $humanoImg('icons/check.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Los datos son tuyos</h5>
          <p class="features-icon-description">Servidores en Europa. Exportá y llevate tus datos cuando quieras, sin límites.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ $humanoImg('icons/rocket.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Menos gestión, más vida</h5>
          <p class="features-icon-description">Procesos probados sin inventarlos vos. Humano.app acelera la operativa desde el primer día.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ $humanoImg('icons/cloud.svg') }}" alt="" />
          </div>
          <h5 class="mb-3">Sistema en la nube</h5>
          <p class="features-icon-description">Accedé desde móvil, tablet u ordenador desde cualquier parte del mundo.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ $humanoImg('icons/user.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Control por WhatsApp</h5>
          <p class="features-icon-description">Gestioná tu negocio desde WhatsApp. Vos das las órdenes, Humano.app responde.</p>
        </div>
        <div class="col-lg-4 col-sm-6 text-center features-icon-box">
          <div class="text-center mb-3">
            <img src="{{ $humanoImg('icons/keyboard.png') }}" alt="" />
          </div>
          <h5 class="mb-3">Consultor IA personalizado</h5>
          <p class="features-icon-description">Respuestas útiles para tu negocio y tu equipo, con tu tono de marca.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="landingManuals" class="section-py bg-body landing-reviews pb-0">
    <div class="container">
      <div class="row align-items-center gx-0 gy-4 g-lg-5">
        <div class="col-md-6 col-lg-5 col-xl-3">
          <div class="mb-3 pb-1">
            <span class="badge bg-label-primary">Guías</span>
          </div>
          <h3 class="mb-1"><span class="section-title">Aprendé a usar</span> Humano</h3>
          <p class="mb-3 mb-md-5">
            Presentaciones paso a paso por módulo.<br class="d-none d-xl-block" />
            Empezamos por cómo funciona la plataforma.
          </p>
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
                @foreach ($guidePresentations as $guide)
                  <div class="swiper-slide">
                    <a href="{{ $guide['url'] }}" class="card h-100 text-body text-decoration-none">
                      <div class="card-body d-flex flex-column justify-content-between h-100">
                        <div class="mb-3 d-flex align-items-center gap-3">
                          <span class="badge bg-label-primary rounded p-2 flex-shrink-0">
                            <i class="ti ti-{{ $guide['icon'] }} ti-md"></i>
                          </span>
                          <div class="min-w-0">
                            <h6 class="mb-0">{{ $guide['title'] }}</h6>
                            <p class="small text-primary mb-0 fw-semibold">{{ $guide['subtitle'] }}</p>
                          </div>
                        </div>
                        <p class="mb-2">{{ $guide['description'] }}</p>
                        <span class="text-primary small fw-semibold mt-3 d-inline-flex align-items-center gap-1">
                          Ver presentación <i class="ti ti-arrow-right ti-xs"></i>
                        </span>
                      </div>
                    </a>
                  </div>
                @endforeach
              </div>
            </div>
            <div class="swiper-button-next d-none" aria-hidden="true"></div>
            <div class="swiper-button-prev d-none" aria-hidden="true"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  @include('homes.humano.partials.plan-showcase')

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
            <img src="{{ $humanoImg('landing-page/faq-boy-with-logos.png') }}" alt="" class="faq-image" />
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
        <div class="col-lg-6 pt-lg-5 text-center text-lg-end d-flex align-items-end justify-content-center justify-content-lg-end">
          <div class="landing-cta-panel-mask">
            <img src="{{ $humanoImg('landing-page/hero-elements-dark.png') }}" alt="Panel Humano" class="landing-cta-panel-shot" width="1024" height="696" loading="lazy" decoding="async" />
          </div>
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
      <div class="humano-brand-footer">
        @include('homes.partials.brand-footer-bottom')
      </div>
    </div>
  </section>
</div>
@endsection
