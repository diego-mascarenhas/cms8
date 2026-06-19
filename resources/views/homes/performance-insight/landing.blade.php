@php
  use App\Support\CmsHomeAsset;
  use App\Support\PerformanceInsightHomeAsset;
  use App\Support\SlashHomeAsset;

  $features = trans('performance_insight_landing.features.items');
  $metricsHighlights = trans('performance_insight_landing.metrics.highlights');
  $chartDays = trans('performance_insight_landing.metrics.chart_days');
  $chartHeights = [58, 94, 76, 122, 100, 52, 82];
  $chartValues = [18, 29, 24, 38, 31, 14, 22];
  $pageUrl = route('performance-insight.landing');
  $ogImage = url('/'.ltrim(config('variables.ogImage', 'assets/logo.png'), '/'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="{{ __('performance_insight_landing.meta_description') }}">
  <meta name="color-scheme" content="dark">
  <title>{{ __('performance_insight_landing.page_title') }}</title>
  <link rel="canonical" href="{{ $pageUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ $pageUrl }}">
  <meta property="og:title" content="{{ __('performance_insight_landing.page_title') }}">
  <meta property="og:description" content="{{ __('performance_insight_landing.meta_description') }}">
  <meta property="og:image" content="{{ $ogImage }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}">
  <link rel="stylesheet" href="{{ CmsHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ PerformanceInsightHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}">
</head>
<body class="cms-page" @if (session('slash_lead_sent')) data-slash-show-reward-modal @endif>

  <header class="cms-nav">
    <div class="cms-container cms-nav-inner">
      <a href="{{ route('humano') }}" class="cms-nav-brand" aria-label="{{ config('app.name') }}">
        <img src="{{ Helper::logoAsset('light') }}" alt="{{ config('app.name') }}">
      </a>
      <ul class="cms-nav-links">
        <li><a href="#metricas">{{ __('performance_insight_landing.nav.metrics') }}</a></li>
        <li><a href="#producto">{{ __('performance_insight_landing.nav.product') }}</a></li>
        <li><a href="#funciones">{{ __('performance_insight_landing.nav.features') }}</a></li>
        <li><a href="{{ $presentationUrl }}">{{ __('performance_insight_landing.nav.guide') }}</a></li>
      </ul>
      <div class="cms-nav-actions">
        <a href="{{ route('login') }}" class="cms-btn cms-btn-ghost">{{ __('performance_insight_landing.nav.login') }}</a>
        <a href="#empezar" class="cms-btn cms-btn-primary">{{ __('performance_insight_landing.nav.cta') }}</a>
      </div>
    </div>
  </header>

  <main>
    <section id="empezar" class="cms-hero">
      <span class="cms-hero-glow cms-hero-glow-a" aria-hidden="true"></span>
      <span class="cms-hero-glow cms-hero-glow-b" aria-hidden="true"></span>
      <div class="cms-container cms-hero-grid">
        <div>
          <span class="cms-eyebrow">{{ __('performance_insight_landing.hero.eyebrow') }}</span>
          <h1 class="cms-h1">{{ __('performance_insight_landing.hero.title') }}</h1>
          <p class="cms-lead">{{ __('performance_insight_landing.hero.lead') }}</p>
          <form class="slash-hero-form cms-hero-form" action="{{ route('performance-insight.lead.store') }}" method="POST" data-slash-lead-form novalidate>
            @csrf
            <input type="hidden" name="source" value="hero">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('slash_landing.hero.email_placeholder') }}" aria-label="Email" autocomplete="email" inputmode="email" spellcheck="false" data-slash-email-input aria-describedby="pi-lead-feedback-hero">
            <button type="submit" class="slash-btn slash-btn-accent">{{ __('slash_landing.hero.cta') }}</button>
            <p class="slash-form-feedback" id="pi-lead-feedback-hero" data-slash-form-feedback role="alert" @if ($errors->has('email') && old('source', 'hero') === 'hero') data-slash-form-feedback-visible @endif>@if ($errors->has('email') && old('source', 'hero') === 'hero'){{ $errors->first('email') }}@endif</p>
          </form>
          <p class="slash-hero-note cms-hero-note">{{ __('slash_landing.hero.note') }}</p>
          <div class="cms-hero-actions">
            <a href="{{ $presentationUrl }}" class="cms-btn cms-btn-ghost">{{ __('performance_insight_landing.hero.cta_secondary') }}</a>
            <a href="{{ route('performance-insight.newsletter') }}" class="cms-btn cms-btn-ghost">{{ __('performance_insight_landing.hero.cta_newsletter') }}</a>
          </div>
        </div>
        <div class="cms-hero-visual pi-hero-visual-stack" aria-hidden="true">
          <div class="pi-hero-panel">
            <div class="pi-dashboard-mock pi-dashboard-mock--hero">
              <div class="pi-dashboard-mock-head">
                <h4>{{ __('performance_insight_landing.dashboard.headline') }}</h4>
                <div class="pi-dashboard-mock-focus">{{ __('performance_insight_landing.dashboard.focus') }}</div>
              </div>
              <div class="pi-dashboard-mock-body">
                {{ __('performance_insight_landing.dashboard.message') }}
                <ul>
                  @foreach (trans('performance_insight_landing.dashboard.highlights') as $line)
                    <li>{{ $line }}</li>
                  @endforeach
                </ul>
              </div>
            </div>
            <div class="pi-hero-chart">
              <div class="pi-hero-chart-head">
                <span>{{ __('performance_insight_landing.metrics.chart_title') }}</span>
                <span class="pi-hero-chart-ratio">{{ __('performance_insight_landing.metrics.ratio_value') }}/100</span>
              </div>
              <div class="pi-bar-chart pi-bar-chart--hero">
                @foreach ($chartDays as $index => $day)
                  <div class="pi-bar-col">
                    <span class="pi-bar-val">{{ $chartValues[$index] ?? 0 }}</span>
                    <div class="pi-bar" style="--h:{{ $chartHeights[$index] ?? 48 }}px;"></div>
                    <span class="pi-bar-label">{{ $day }}</span>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="metricas" class="cms-section">
      <div class="cms-container">
        <div class="cms-section-head">
          <span class="cms-eyebrow">{{ __('performance_insight_landing.metrics.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('performance_insight_landing.metrics.title') }}</h2>
          <p class="cms-lead">{{ __('performance_insight_landing.metrics.lead') }}</p>
        </div>
        <div class="pi-metrics-grid">
          <div>
            <p class="cms-lead" style="margin-bottom:1rem;font-size:0.85rem;">{{ __('performance_insight_landing.metrics.ratio_label') }}</p>
            <div class="pi-ratio-ring" style="--pi-ratio:{{ __('performance_insight_landing.metrics.ratio_value') }};">
              <div class="pi-ratio-value">{{ __('performance_insight_landing.metrics.ratio_value') }}<small>/100</small></div>
            </div>
            <p class="cms-lead" style="margin-top:1.25rem;font-size:0.85rem;">{{ __('performance_insight_landing.metrics.chart_title') }}</p>
            <div class="pi-bar-chart" style="margin-top:0.75rem;">
              @foreach ($chartDays as $index => $day)
                <div class="pi-bar-col">
                  <span class="pi-bar-val">{{ $chartValues[$index] ?? 0 }}</span>
                  <div class="pi-bar" style="--h:{{ $chartHeights[$index] ?? 48 }}px;"></div>
                  <span class="pi-bar-label">{{ $day }}</span>
                </div>
              @endforeach
            </div>
          </div>
          <div>
            <p class="cms-lead" style="margin-bottom:1rem;font-size:0.85rem;">{{ __('performance_insight_landing.metrics.highlights_title') }}</p>
            <div class="pi-signal-list">
              @foreach ($metricsHighlights as $signal)
                <div class="pi-signal-row">
                  <span>{{ $signal['label'] }}</span>
                  <strong>{{ $signal['value'] }}</strong>
                  <div class="pi-signal-bar"><i style="width:{{ $signal['pct'] }}%;"></i></div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="producto" class="cms-section">
      <div class="cms-container cms-flow">
        <div class="cms-flow-copy">
          <span class="cms-eyebrow">{{ __('performance_insight_landing.dashboard.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('performance_insight_landing.dashboard.title') }}</h2>
          <p class="cms-lead">{{ __('performance_insight_landing.dashboard.lead') }}</p>
        </div>
        <div class="cms-flow-visual">
          <div class="cms-browser">
            <div class="cms-browser-bar">
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
              <span class="cms-browser-dot"></span>
            </div>
            <div class="cms-browser-body" style="grid-template-columns:52px 1fr;min-height:220px;background:#f5f5f9;">
              <div class="cms-browser-side">
                <span class="active"></span><span></span><span></span><span></span>
              </div>
              <div class="cms-browser-main" style="background:#fff;padding:16px;">
                <div class="pi-dashboard-mock" style="max-width:100%;box-shadow:none;border:1px solid #e4e6ef;">
                  <div class="pi-dashboard-mock-head">
                    <h4>{{ __('performance_insight_landing.dashboard.headline') }}</h4>
                    <div class="pi-dashboard-mock-focus">{{ __('performance_insight_landing.dashboard.focus') }}</div>
                  </div>
                  <div class="pi-dashboard-mock-body">{{ __('performance_insight_landing.dashboard.message') }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container cms-flow cms-flow-reverse">
        <div class="cms-flow-copy">
          <span class="cms-eyebrow">{{ __('performance_insight_landing.notification.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('performance_insight_landing.notification.title') }}</h2>
          <p class="cms-lead">{{ __('performance_insight_landing.notification.lead') }}</p>
        </div>
        <div class="cms-flow-visual">
          <div class="pi-notif-mock">
            <div class="pi-notif-thread">
              <h6>{{ __('performance_insight_landing.notification.preview_from') }}</h6>
              <small style="color:#a1acb8;">{{ __('performance_insight_landing.notification.preview_subject') }}</small>
              <div class="pi-notif-preview">{{ __('performance_insight_landing.notification.preview_body') }}</div>
              <textarea class="pi-notif-suggestion" readonly>{{ __('performance_insight_landing.notification.suggestion') }}</textarea>
              <div class="pi-notif-actions">
                <span class="pi-btn-copy"><i class="ti ti-copy"></i> {{ __('app.performance_digest_suggestion_copy', [], 'es') }}</span>
                <span class="pi-badge-scheduled"><i class="ti ti-clock"></i> {{ __('performance_insight_landing.notification.scheduled_badge') }}</span>
                <span class="pi-btn-cancel"><i class="ti ti-x"></i> {{ __('performance_insight_landing.notification.cancel') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container">
        <div class="cms-section-head">
          <span class="cms-eyebrow">{{ __('performance_insight_landing.channels.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('performance_insight_landing.channels.title') }}</h2>
          <p class="cms-lead">{{ __('performance_insight_landing.channels.lead') }}</p>
        </div>
        <div class="pi-channel-grid">
          <div class="pi-channel-card"><i class="ti ti-mail"></i><strong>{{ __('performance_insight_landing.channels.email') }}</strong><span>06:15</span></div>
          <div class="pi-channel-card"><i class="ti ti-bell"></i><strong>{{ __('performance_insight_landing.channels.bell') }}</strong><span>Instantáneo</span></div>
          <div class="pi-channel-card"><i class="ti ti-layout-dashboard"></i><strong>{{ __('performance_insight_landing.channels.card') }}</strong><span>Al entrar</span></div>
          <div class="pi-channel-card"><i class="ti ti-history"></i><strong>{{ __('performance_insight_landing.channels.history') }}</strong><span>Filtro por fecha</span></div>
        </div>
      </div>
    </section>

    <section id="funciones" class="cms-section">
      <div class="cms-container">
        <div class="cms-section-head">
          <span class="cms-eyebrow">{{ __('performance_insight_landing.features.eyebrow') }}</span>
          <h2 class="cms-h2">{{ __('performance_insight_landing.features.title') }}</h2>
        </div>
        <div class="cms-feature-grid">
          @foreach ($features as $feature)
            <article class="cms-feature-card">
              <h3>{{ $feature['title'] }}</h3>
              <p>{{ $feature['text'] }}</p>
            </article>
          @endforeach
        </div>
      </div>
    </section>

    <section class="cms-section">
      <div class="cms-container cms-cta">
        <h2 class="cms-h2">{{ __('performance_insight_landing.cta.title') }}</h2>
        <p class="cms-lead">{{ __('performance_insight_landing.cta.lead') }}</p>
        <form class="slash-hero-form cms-hero-form cms-cta-form" action="{{ route('performance-insight.lead.store') }}" method="POST" data-slash-lead-form novalidate>
          @csrf
          <input type="hidden" name="source" value="cta">
          <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('slash_landing.hero.email_placeholder') }}" aria-label="Email" autocomplete="email" data-slash-email-input>
          <button type="submit" class="slash-btn slash-btn-accent">{{ __('performance_insight_landing.cta.button') }}</button>
        </form>
        <div class="cms-cta-actions">
          <a href="{{ $presentationUrl }}" class="cms-btn cms-btn-ghost">{{ __('performance_insight_landing.cta.secondary') }}</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="cms-footer">
    <div class="cms-container humano-brand-footer">
      @include('homes.partials.brand-footer-bottom')
    </div>
  </footer>

  @include('homes.slash.partials.lead-modal', ['leadStoreUrl' => route('performance-insight.lead.store')])
  @include('homes.slash.partials.reward-modal', ['rewardCtaUrl' => route('pricing')])

  <script src="{{ SlashHomeAsset::url('js/landing.js') }}"></script>
</body>
</html>
