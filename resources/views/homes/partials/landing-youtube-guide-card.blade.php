@php
  use App\Support\LandingYouTube;

  $landingYoutubeUrl = LandingYouTube::url();
@endphp

@if ($landingYoutubeUrl)
  <a
    href="{{ $landingYoutubeUrl }}"
    class="slash-card slash-guide-card slash-guide-card-youtube"
    target="_blank"
    rel="noopener noreferrer"
  >
    <div class="slash-guide-top">
      <span class="slash-guide-badge slash-guide-badge-youtube">@include('homes.slash.partials.icon', ['name' => 'play'])</span>
      <div>
        <p class="slash-guide-sub">{{ __('slash_landing.guides.youtube_card.subtitle') }}</p>
        <h3>{{ __('slash_landing.guides.youtube_card.title') }}</h3>
      </div>
    </div>
    <p>{{ __('slash_landing.guides.youtube_card.description') }}</p>
    <span class="slash-guide-link">{{ __('slash_landing.guides.youtube_card.cta') }} @include('homes.slash.partials.icon', ['name' => 'arrow-right'])</span>
  </a>
@endif
