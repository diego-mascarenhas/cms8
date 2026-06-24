@php
  use App\Helpers\SupportWhatsAppHelper;
  use App\Support\SlashHomeAsset;

  $slashImg = static fn (string $path): string => SlashHomeAsset::url('img/'.$path);

  $planImages = [
    'assistant' => $slashImg('plans/assistant.png'),
    'hunter' => $slashImg('plans/hunter.png'),
    'business' => $slashImg('plans/business.png'),
    'mentor' => $slashImg('plans/mentor.png'),
    'innovation' => $slashImg('plans/innovation.png'),
  ];

  $features = trans('slash_landing.features');
  $capabilities = trans('slash_landing.capability_items');
  $tools = trans('slash_landing.tool_items');
  $trustCards = trans('slash_landing.trust_cards');
  $testimonials = trans('slash_landing.testimonials');
  $securityItems = trans('slash_landing.security_items');
  $faqs = trans('slash_landing.faqs');
  $metrics = trans('slash_landing.metric_items');

  $whatsappSupportUrl = SupportWhatsAppHelper::webUrl();
  $supportPhoneDisplay = SupportWhatsAppHelper::phoneDisplay();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="{{ __('slash_landing.meta_description') }}">
  <meta name="color-scheme" content="dark">
  <title>{{ __('slash_landing.page_title') }}</title>
  @php
    $slashOgImagePath = config('variables.ogImage', 'assets/logo.png');
    $slashOgImageUrl = str_starts_with($slashOgImagePath, 'http') ? $slashOgImagePath : url('/'.ltrim($slashOgImagePath, '/'));
    $slashPageUrl = route('slash');
  @endphp
  <link rel="canonical" href="{{ $slashPageUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ $slashPageUrl }}">
  <meta property="og:title" content="{{ __('slash_landing.og_title') }}">
  <meta property="og:description" content="{{ __('slash_landing.meta_description') }}">
  <meta property="og:image" content="{{ $slashOgImageUrl }}">
  <meta property="og:image:secure_url" content="{{ $slashOgImageUrl }}">
  <meta property="og:image:width" content="{{ config('variables.ogImageWidth', 552) }}">
  <meta property="og:image:height" content="{{ config('variables.ogImageHeight', 552) }}">
  <meta property="og:image:alt" content="{{ config('variables.ogImageAlt', config('variables.templateName')) }}">
  <meta property="og:site_name" content="{{ config('variables.templateName') }}">
  <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ __('slash_landing.og_title') }}">
  <meta name="twitter:description" content="{{ __('slash_landing.meta_description') }}">
  <meta name="twitter:image" content="{{ $slashOgImageUrl }}">
  <meta name="twitter:image:alt" content="{{ config('variables.ogImageAlt', config('variables.templateName')) }}">
  @if (config('variables.twitterUrl'))
  <meta name="twitter:site" content="{{ config('variables.twitterUrl') }}">
  @endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ SlashHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/landing-highlight.css') }}">
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
<body class="slash-page" @if (session('slash_lead_sent')) data-slash-show-reward-modal @endif>

  <header class="slash-nav">
    <div class="slash-container slash-nav-inner">
      <a href="{{ url('/') }}" class="slash-nav-brand" aria-label="{{ config('app.name') }}">
        <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
      </a>

      <ul class="slash-nav-links" id="slashNavLinks">
        @if (config('slash_landing.show_plan_stories_section'))
        <li><a href="#historias-planes">{{ __('slash_landing.nav.in_action') }}</a></li>
        @endif
        <li><a href="#beneficios">{{ __('slash_landing.nav.benefits') }}</a></li>
        <li><a href="#guias">{{ __('slash_landing.nav.guides') }}</a></li>
        <li><a href="#planes">{{ __('slash_landing.nav.plans') }}</a></li>
        <li><a href="#precios">{{ __('slash_landing.nav.pricing') }}</a></li>
        <li><a href="#faq">{{ __('slash_landing.nav.faq') }}</a></li>
        <li class="slash-nav-login-mobile">
          <a href="{{ route('login') }}" class="slash-btn slash-btn-ghost slash-nav-login-link">{{ __('slash_landing.nav.login') }}</a>
        </li>
      </ul>

      <div class="slash-nav-actions">
        <a href="{{ route('login') }}" class="slash-btn slash-btn-ghost slash-nav-cta-desktop">{{ __('slash_landing.nav.login') }}</a>
        <button class="slash-nav-toggle" type="button" data-slash-nav-toggle aria-controls="slashNavLinks" aria-expanded="false" aria-label="{{ __('slash_landing.nav.open_menu') }}">
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
        <h1>{{ __('slash_landing.hero.title') }}</h1>
        <p class="slash-lead">{{ __('slash_landing.hero.lead') }}</p>
        <form class="slash-hero-form" action="{{ route('slash.lead.store') }}" method="POST" data-slash-lead-form novalidate>
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
            aria-describedby="slash-lead-feedback-hero"
          >
          <button type="submit" class="slash-btn slash-btn-accent">{{ __('slash_landing.hero.cta') }}</button>
          <p
            class="slash-form-feedback"
            id="slash-lead-feedback-hero"
            data-slash-form-feedback
            role="alert"
            @if ($errors->has('email') && old('source', 'hero') === 'hero') data-slash-form-feedback-visible @endif
          >@if ($errors->has('email') && old('source', 'hero') === 'hero'){{ $errors->first('email') }}@endif</p>
        </form>
        <p class="slash-hero-note">{{ __('slash_landing.hero.note') }}</p>
        <div class="slash-hero-shot">
          <img src="{{ $slashImg('landing-page/hero-elements-dark.png') }}" alt="{{ __('slash_landing.hero.image_alt') }}" width="3612" height="2328" loading="eager" decoding="async">
        </div>
      </div>
    </section>

    <section class="slash-section pt-0">
      <div class="slash-container">
        <div class="slash-section-head text-center">
          @include('homes.shared.partials.hero-highlight', ['class' => 'slash-lead mb-0 landing-hero-highlight'])
        </div>
      </div>
    </section>

    <section class="slash-statband">
      <div class="slash-container">
        <strong>{{ __('slash_landing.statband.strong') }}</strong>
        <span>{{ __('slash_landing.statband.span') }}</span>
      </div>
    </section>

    @if (config('slash_landing.show_trust_section'))
    <section id="producto" class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">{{ __('slash_landing.trust.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.trust.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.trust.lead') }}</p>
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
    @endif

    @if (config('slash_landing.show_plan_stories_section'))
    @include('homes.slash.partials.plan-stories', [
      'landingPlans' => $landingPlans,
      'planImages' => $planImages,
    ])
    @endif

    <section class="slash-section">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">{{ __('slash_landing.capabilities.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.capabilities.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.capabilities.lead') }}</p>
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
          <span class="slash-eyebrow">{{ __('slash_landing.benefits.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.benefits.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.benefits.lead') }}</p>
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
          <span class="slash-eyebrow">{{ __('slash_landing.metrics.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.metrics.title') }}</h2>
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
          <span class="slash-eyebrow">{{ __('slash_landing.tools.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.tools.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.tools.lead') }}</p>
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
          <span class="slash-eyebrow">{{ __('slash_landing.security.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.security.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.security.lead') }}</p>
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
          <span class="slash-eyebrow">{{ __('slash_landing.guides.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.guides.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.guides.lead') }}</p>
        </div>
        <div class="slash-grid">
          @include('homes.partials.landing-youtube-guide-card')
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
              <span class="slash-guide-link">{{ __('slash_landing.guides.cta') }} @include('homes.slash.partials.icon', ['name' => 'arrow-right'])</span>
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
              <h3>{{ __('humano_pricing.plans.'.$planId.'.name') }}</h3>
              <p>{{ __('humano_pricing.plans.'.$planId.'.description') }}</p>
              <ul class="slash-plan-features">
                @foreach (trans('humano_pricing.plans.'.$planId.'.features') as $planFeature)
                  <li>@include('homes.slash.partials.icon', ['name' => 'check']) <span>{{ $planFeature }}</span></li>
                @endforeach
              </ul>
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
          <span class="slash-eyebrow">{{ __('slash_landing.pricing.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('humano_pricing.hero_title') }}</h2>
          <p class="slash-lead">{{ __('humano_pricing.hero_subtitle') }}</p>
        </div>
        <div class="slash-pricing-billing" role="group" aria-label="{{ __('humano_pricing.hero_subtitle') }}">
          <span class="slash-pricing-billing-label is-active" data-slash-billing-label="monthly">{{ __('humano_pricing.billing_monthly') }}</span>
          <label class="slash-pricing-billing-switch">
            <input type="checkbox" class="slash-price-duration-toggler">
            <span class="slash-pricing-billing-track" aria-hidden="true">
              <span class="slash-pricing-billing-thumb"></span>
            </span>
          </label>
          <span class="slash-pricing-billing-label" data-slash-billing-label="annual">{{ __('humano_pricing.billing_annual') }}</span>
          <span class="slash-pill slash-pricing-billing-badge">{{ __('humano_pricing.annual_discount_badge') }}</span>
        </div>
        <div class="slash-pricing-grid">
          @foreach ($landingPlans as $plan)
            @php
              $planId = $plan['id'];
              $checkoutAvailable = (bool) ($plan['checkout_available'] ?? true);
              $checkoutHrefMonthly = trim((string) ($plan['checkout_href_monthly'] ?? $plan['checkout_href'] ?? $plan['checkout_url'] ?? ''));
              $checkoutHrefYearly = trim((string) ($plan['checkout_href_yearly'] ?? $checkoutHrefMonthly));
              $externalUrl = trim((string) ($plan['external_url'] ?? ''));
              $isFeatured = $checkoutAvailable && ! empty($plan['popular']);
            @endphp
            <article id="plan-{{ $planId }}" class="slash-pricing-card {{ $isFeatured ? 'is-featured' : '' }}">
              @if ($isFeatured)
                <span class="slash-pill slash-pricing-badge">{{ __('humano_pricing.most_popular') }}</span>
              @endif
              <h3>{{ __('humano_pricing.plans.'.$planId.'.name') }}</h3>
              @if ($checkoutAvailable && filled($plan['monthly_amount'] ?? null) && filled($plan['yearly_amount'] ?? null))
                <div class="slash-pricing-price-wrap">
                  <div class="slash-pricing-price slash-price-toggle slash-price-yearly is-hidden">
                    {{ $plan['yearly_amount'] }}€
                    <small>{{ __('humano_pricing.per_year_suffix') }}</small>
                  </div>
                  <div class="slash-pricing-price slash-price-toggle slash-price-monthly">
                    {{ $plan['monthly_amount'] }}€
                    <small>{{ __('humano_pricing.per_month_suffix') }}</small>
                  </div>
                  <p class="slash-pricing-billing-note slash-price-toggle slash-price-yearly is-hidden">{{ __('humano_pricing.billed_annually') }}</p>
                  <p class="slash-pricing-billing-note slash-price-toggle slash-price-monthly">{{ __('humano_pricing.billed_monthly') }}</p>
                  <p class="slash-pricing-vat">{{ __('humano_pricing.prices_plus_vat') }}</p>
                </div>
              @elseif ($checkoutAvailable && filled($plan['monthly_amount'] ?? null))
                <div class="slash-pricing-price">
                  {{ $plan['monthly_amount'] }}€
                  <small>{{ __('humano_pricing.per_month_suffix') }}</small>
                </div>
                <p class="slash-pricing-vat">{{ __('humano_pricing.prices_plus_vat') }}</p>
              @elseif ($externalUrl !== '')
                <div class="slash-pricing-price slash-pricing-soon">{{ __('humano_pricing.external_pricing') }}</div>
              @else
                <div class="slash-pricing-price slash-pricing-soon">{{ __('humano_pricing.coming_soon') }}</div>
              @endif
              <p class="slash-pricing-desc">{{ __('humano_pricing.plans.'.$planId.'.description') }}</p>
              <ul class="slash-pricing-features">
                @foreach (trans('humano_pricing.plans.'.$planId.'.features') as $planFeature)
                  <li>@include('homes.slash.partials.icon', ['name' => 'check']) <span>{{ $planFeature }}</span></li>
                @endforeach
              </ul>
              @if ($checkoutAvailable && $checkoutHrefMonthly !== '')
                <a
                  href="{{ $checkoutHrefMonthly }}"
                  class="slash-btn slash-pricing-checkout {{ $isFeatured ? 'slash-btn-accent' : 'slash-btn-outline' }}"
                  data-slash-checkout-monthly="{{ $checkoutHrefMonthly }}"
                  data-slash-checkout-yearly="{{ $checkoutHrefYearly }}"
                >
                  {{ __('humano_pricing.subscribe') }}
                </a>
              @elseif ($externalUrl !== '')
                <a href="{{ $externalUrl }}" class="slash-btn slash-btn-outline" target="_blank" rel="noopener noreferrer">
                  {{ __('humano_pricing.external_cta') }}
                </a>
              @else
                @if ($whatsappSupportUrl)
                  <a href="{{ $whatsappSupportUrl }}" class="slash-btn slash-btn-outline" target="_blank" rel="noopener noreferrer">
                    {{ __('humano_pricing.consult_cta') }}
                  </a>
                @else
                  <a href="#contacto" class="slash-btn slash-btn-outline">
                    {{ __('humano_pricing.consult_cta') }}
                  </a>
                @endif
              @endif
            </article>
          @endforeach
        </div>
      </div>
    </section>

    <section id="faq" class="slash-section slash-faq">
      <div class="slash-container">
        <div class="slash-section-head">
          <span class="slash-eyebrow">{{ __('slash_landing.faq.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.faq.title') }}</h2>
          <p class="slash-lead">
            {!! __('slash_landing.faq.lead', [
              'link' => '<a href="#contacto" style="color: var(--slash-accent);">'.e(__('slash_landing.faq.lead_link')).'</a>',
            ]) !!}
          </p>
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
            <h2>{{ __('slash_landing.cta.title') }}</h2>
            <p>{{ __('slash_landing.cta.lead') }}</p>
            <form class="slash-hero-form" action="{{ route('slash.lead.store') }}" method="POST" data-slash-lead-form novalidate>
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
                aria-describedby="slash-lead-feedback-cta"
              >
              <button type="submit" class="slash-btn slash-btn-accent">{{ __('slash_landing.cta.button') }}</button>
              <p
                class="slash-form-feedback"
                id="slash-lead-feedback-cta"
                data-slash-form-feedback
                role="alert"
                @if ($errors->has('email') && old('source') === 'cta') data-slash-form-feedback-visible @endif
              >@if ($errors->has('email') && old('source') === 'cta'){{ $errors->first('email') }}@endif</p>
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
          <span class="slash-eyebrow">{{ __('slash_landing.contact.eyebrow') }}</span>
          <h2 class="slash-h2">{{ __('slash_landing.contact.title') }}</h2>
          <p class="slash-lead">{{ __('slash_landing.contact.lead') }}</p>
        </div>
        <div class="slash-contact-grid">
          <div class="slash-contact-card">
            <span class="slash-card-icon">@include('homes.slash.partials.icon', ['name' => 'mail'])</span>
            <div>
              <span>{{ __('slash_landing.contact.email') }}</span>
              <strong><a href="mailto:hola@humano.app">hola@humano.app</a></strong>
            </div>
          </div>
          <div class="slash-contact-card">
            <span class="slash-card-icon">@include('homes.slash.partials.icon', ['name' => 'phone'])</span>
            <div>
              <span>{{ __('slash_landing.contact.phone') }}</span>
              <strong><a href="{{ $whatsappSupportUrl ?? '#contacto' }}" target="_blank" rel="noopener noreferrer">{{ $supportPhoneDisplay ?? '+34 624 15 95 57' }}</a></strong>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="slash-footer">
    <div class="slash-container">
      <div class="slash-footer-top">
        <div class="slash-footer-brand">
          <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
          <p>{{ __('slash_landing.footer.tagline') }}</p>
        </div>
        <div>
          <h4>{{ __('slash_landing.nav.product') }}</h4>
          <ul>
            @if (config('slash_landing.show_plan_stories_section'))
            <li><a href="#historias-planes">{{ __('slash_landing.nav.in_action') }}</a></li>
            @endif
            <li><a href="#beneficios">{{ __('slash_landing.nav.benefits') }}</a></li>
            <li><a href="#planes">{{ __('slash_landing.nav.plans') }}</a></li>
            <li><a href="#precios">{{ __('slash_landing.nav.pricing') }}</a></li>
          </ul>
        </div>
        <div>
          <h4>{{ __('slash_landing.nav.resources') }}</h4>
          <ul>
            <li><a href="#guias">{{ __('slash_landing.nav.guides') }}</a></li>
            @include('homes.partials.landing-youtube-footer-link')
            <li><a href="#faq">{{ __('slash_landing.nav.faq') }}</a></li>
            <li>
              <a
                href="{{ config('slash_landing.compliance_url') }}"
                target="_blank"
                rel="noopener noreferrer"
              >{{ __('slash_landing.nav.data_privacy') }}</a>
            </li>
          </ul>
        </div>
        <div>
          <h4>{{ __('slash_landing.nav.account') }}</h4>
          <ul>
            <li><a href="{{ route('login') }}">{{ __('slash_landing.nav.login') }}</a></li>
            <li>
              <a
                href="{{ $whatsappSupportUrl }}"
                target="_blank"
                rel="noopener noreferrer"
              >{{ __('slash_landing.nav.contact') }}</a>
            </li>
          </ul>
        </div>
      </div>
      @include('homes.partials.brand-footer-bottom')
    </div>
  </footer>

  @include('homes.slash.partials.lead-modal')
  @include('homes.slash.partials.reward-modal')

  <script src="{{ SlashHomeAsset::url('vendor/gsap/gsap.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('vendor/gsap/ScrollTrigger.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('vendor/lenis/lenis.min.js') }}"></script>
  <script src="{{ SlashHomeAsset::url('js/landing.js') }}"></script>
</body>
</html>
