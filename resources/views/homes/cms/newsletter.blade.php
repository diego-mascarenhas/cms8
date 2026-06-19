@php
  $adminBullets = trans('cms_landing.newsletter.admin_bullets');
  $userBullets = trans('cms_landing.newsletter.user_bullets');
  $logoUrl = url(Helper::logoAsset('light'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ __('cms_landing.newsletter.page_title') }}</title>
  <link rel="stylesheet" href="{{ \App\Support\CmsHomeAsset::url('css/landing.css') }}">
  <link rel="stylesheet" href="{{ asset('homes/shared/css/brand-footer.css') }}">
</head>
<body class="cms-page cms-newsletter-preview-page">
  <div class="cms-container cms-newsletter-preview-wrap">
    <header class="cms-newsletter-preview-head">
      <a href="{{ $landingUrl }}" class="cms-btn cms-btn-ghost">&larr; CMS</a>
      <p class="cms-newsletter-preview-note">{{ __('cms_landing.newsletter.preview_note') }}</p>
      <dl class="cms-newsletter-meta">
        <div>
          <dt>Subject</dt>
          <dd>{{ __('cms_landing.newsletter.subject') }}</dd>
        </div>
        <div>
          <dt>Preheader</dt>
          <dd>{{ __('cms_landing.newsletter.preheader') }}</dd>
        </div>
      </dl>
      <p class="cms-newsletter-campaign-link">
        <a href="{{ asset('homes/cms/newsletter-campaign.html') }}" target="_blank" rel="noopener">newsletter-campaign.html</a>
      </p>
    </header>

    <div class="cms-email-frame" role="article" aria-label="{{ __('cms_landing.newsletter.headline') }}">
      @include('homes.cms.partials.newsletter-email', [
        'headline' => __('cms_landing.newsletter.headline'),
        'intro' => __('cms_landing.newsletter.intro'),
        'adminTitle' => __('cms_landing.newsletter.admin_title'),
        'adminBullets' => $adminBullets,
        'userTitle' => __('cms_landing.newsletter.user_title'),
        'userBullets' => $userBullets,
        'cta' => __('cms_landing.newsletter.cta'),
        'ctaGuide' => __('cms_landing.newsletter.cta_guide'),
        'footer' => __('cms_landing.newsletter.footer'),
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
