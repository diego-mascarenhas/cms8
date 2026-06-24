@php
  use App\Support\LandingYouTube;

  $landingYoutubeUrl = LandingYouTube::url();
@endphp

@if ($landingYoutubeUrl)
  <li>
    <a
      href="{{ $landingYoutubeUrl }}"
      target="_blank"
      rel="noopener noreferrer"
    >{{ __('slash_landing.nav.youtube_tutorials') }}</a>
  </li>
@endif
