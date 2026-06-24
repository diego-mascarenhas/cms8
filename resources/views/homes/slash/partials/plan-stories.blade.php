@php
  use App\Support\LandingYouTube;
  use App\Support\SlashHomeAsset;

  /** @var array<string, string> $planImages */
  $featuredVideos = LandingYouTube::featuredVideos();
  $slashStoryImg = static fn (string $path): string => SlashHomeAsset::url('img/'.$path);
@endphp

@if ($featuredVideos !== [])
<section id="historias-planes" class="slash-section slash-stories">
  <div class="slash-container">
    <div class="slash-section-head">
      <span class="slash-eyebrow">{{ __('slash_landing.stories.eyebrow') }}</span>
      <h2 class="slash-h2">{{ __('slash_landing.stories.title') }}</h2>
      <p class="slash-lead">
        {!! __('slash_landing.stories.lead', [
          'link' => '<a href="'.e(LandingYouTube::url() ?? '#guias').'" target="_blank" rel="noopener noreferrer" style="color: var(--slash-accent);">'.e(__('slash_landing.stories.lead_link')).'</a>',
        ]) !!}
      </p>
    </div>

    <div class="slash-stories-stage" data-slash-stories>
      <div class="slash-stories-row">
        @foreach ($featuredVideos as $index => $video)
          @php
            $youtubeId = $video['youtube_id'];
            $storyHref = LandingYouTube::watchUrl($youtubeId);
            $posterPath = $video['poster'] !== '' ? $video['poster'] : 'plans/assistant.png';
            $posterImage = $planImages[basename($posterPath, '.png')] ?? $slashStoryImg($posterPath);
            $isDefault = $index === 0;
          @endphp
          <article
            class="slash-story-card {{ $isDefault ? 'is-active is-default' : '' }}"
            data-slash-story-card="onboarding-{{ $index + 1 }}"
            tabindex="0"
            role="group"
            aria-label="{{ $video['title'] }}"
          >
            <a
              href="{{ $storyHref }}"
              class="slash-story-media"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="slash-story-visual" aria-hidden="true">
                <img
                  class="slash-story-poster"
                  src="{{ LandingYouTube::thumbnailUrl($youtubeId) }}"
                  alt=""
                  loading="lazy"
                  decoding="async"
                >
                <span class="slash-story-play">@include('homes.slash.partials.icon', ['name' => 'play'])</span>
              </span>
              <span class="slash-story-shade" aria-hidden="true"></span>
              <div class="slash-story-meta">
                <img
                  class="slash-story-avatar"
                  src="{{ $posterImage }}"
                  alt=""
                  width="40"
                  height="40"
                  loading="lazy"
                  decoding="async"
                >
                <div>
                  <strong class="slash-story-name">{{ $video['title'] }}</strong>
                  <span class="slash-story-role">{{ $video['subtitle'] }}</span>
                </div>
              </div>
            </a>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endif
