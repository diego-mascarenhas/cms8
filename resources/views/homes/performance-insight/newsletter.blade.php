@php
  $adminBullets = trans('performance_insight_landing.newsletter.admin_bullets');
  $userBullets = trans('performance_insight_landing.newsletter.user_bullets');
  $logoUrl = url(Helper::logoAsset('light'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ __('performance_insight_landing.newsletter.page_title') }}</title>
  @include('layouts.partials.favicon')
  <link rel="stylesheet" href="{{ \App\Support\CmsHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}">
</head>
<body class="cms-page cms-newsletter-preview-page">
  <div class="cms-container cms-newsletter-preview-wrap">
    <header class="cms-newsletter-preview-head">
      <a href="{{ $landingUrl }}" class="cms-btn cms-btn-ghost">&larr; Insight diario</a>
      <p class="cms-newsletter-preview-note">{{ __('performance_insight_landing.newsletter.preview_note') }}</p>
      <dl class="cms-newsletter-meta">
        <div>
          <dt>Subject</dt>
          <dd>{{ __('performance_insight_landing.newsletter.subject') }}</dd>
        </div>
        <div>
          <dt>Preheader</dt>
          <dd>{{ __('performance_insight_landing.newsletter.preheader') }}</dd>
        </div>
      </dl>
    </header>

    <div class="cms-email-frame" role="article" aria-label="{{ __('performance_insight_landing.newsletter.headline') }}">
      @include('homes.performance-insight.partials.newsletter-email', [
        'headline' => __('performance_insight_landing.newsletter.headline'),
        'intro' => __('performance_insight_landing.newsletter.intro'),
        'adminTitle' => __('performance_insight_landing.newsletter.admin_title'),
        'adminBullets' => $adminBullets,
        'userTitle' => __('performance_insight_landing.newsletter.user_title'),
        'userBullets' => $userBullets,
        'cta' => __('performance_insight_landing.newsletter.cta'),
        'ctaGuide' => __('performance_insight_landing.newsletter.cta_guide'),
        'footer' => __('performance_insight_landing.newsletter.footer'),
        'badge' => __('performance_insight_landing.newsletter.badge'),
        'ratioLabel' => __('performance_insight_landing.newsletter.ratio_label'),
        'ratioValue' => __('performance_insight_landing.newsletter.ratio_value'),
        'focusLabel' => __('performance_insight_landing.newsletter.focus_label'),
        'focusValue' => __('performance_insight_landing.newsletter.focus_value'),
        'landingUrl' => $landingUrl,
        'presentationUrl' => $presentationUrl,
        'registerUrl' => $registerUrl,
        'logoUrl' => $logoUrl,
      ])
    </div>
  </div>

  <footer class="cms-footer">
    <div class="cms-container humano-brand-footer">
      @include('homes.partials.brand-footer-bottom')
    </div>
  </footer>
</body>
</html>
