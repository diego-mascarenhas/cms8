@php
  $highlightClass = $class ?? 'mb-0 fs-5 text-body lh-base landing-hero-highlight';
@endphp

<p class="{{ $highlightClass }}">
  <strong class="highlight-hook">{{ __('slash_landing.hero.highlight.hook') }}</strong>
  {{ __('slash_landing.hero.highlight.works_from') }}
  <strong class="highlight-whatsapp">{{ __('slash_landing.hero.highlight.whatsapp') }}</strong>.
  {{ __('slash_landing.hero.highlight.desk') }}
  <span class="highlight-muted">{{ __('slash_landing.hero.highlight.desk_why') }}</span>
  {{ __('slash_landing.hero.highlight.with_brand') }}
  <strong class="highlight-brand">{{ __('slash_landing.hero.highlight.brand') }}</strong>
  {{ __('slash_landing.hero.highlight.in_hand') }}
  <strong class="highlight-traits">{{ __('slash_landing.hero.highlight.traits') }}</strong>.
</p>
