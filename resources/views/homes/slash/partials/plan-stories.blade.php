@php
  /** @var list<array<string, mixed>> $landingPlans */
  /** @var array<string, string> $planImages */

  $planStoryMeta = [
    'assistant' => [
      'youtube_id' => '',
      'subtitle' => 'WhatsApp, contactos y tareas',
    ],
    'hunter' => [
      'youtube_id' => '',
      'subtitle' => 'Leads, email y landings',
    ],
    'business' => [
      'youtube_id' => '',
      'subtitle' => 'Marketing, ventas y cobros',
    ],
  ];

  $storyPlans = array_values(array_filter(
    $landingPlans,
    static fn (array $plan): bool => ($plan['id'] ?? '') !== 'mentor'
  ));

  $defaultStoryId = 'assistant';

  foreach ($storyPlans as $plan) {
    if (! empty($plan['popular'])) {
      $defaultStoryId = $plan['id'];
      break;
    }
  }
@endphp

<section id="historias-planes" class="slash-section slash-stories">
  <div class="slash-container">
    <div class="slash-section-head">
      <span class="slash-eyebrow">{{ __('humano_pricing.landing_plans_badge') }}</span>
      <h2 class="slash-h2">{{ __('slash_landing.stories.title') }}</h2>
      <p class="slash-lead">
        {!! __('slash_landing.stories.lead', [
          'link' => '<a href="'.e(route('pricing')).'" style="color: var(--slash-accent);">'.e(__('slash_landing.stories.lead_link')).'</a>',
        ]) !!}
      </p>
    </div>

    <div class="slash-stories-stage" data-slash-stories>
      <div class="slash-stories-row">
        @foreach ($storyPlans as $plan)
          @php
            $planId = $plan['id'];
            $meta = $planStoryMeta[$planId] ?? $planStoryMeta['assistant'];
            $planImage = $planImages[$planId] ?? $planImages['assistant'];
            $planName = __('humano_pricing.plans.'.$planId.'.name');
            $youtubeId = trim((string) ($meta['youtube_id'] ?? ''));
            $storyHref = $youtubeId !== ''
              ? 'https://www.youtube.com/watch?v='.$youtubeId
              : route('pricing').'#plan-'.$planId;
            $isDefault = $planId === $defaultStoryId;
          @endphp
          <article
            class="slash-story-card {{ $isDefault ? 'is-active is-default' : '' }}"
            data-slash-story-card="{{ $planId }}"
            tabindex="0"
            role="group"
            aria-label="{{ $planName }}"
          >
            <a
              href="{{ $storyHref }}"
              class="slash-story-media"
              @if ($youtubeId !== '') target="_blank" rel="noopener noreferrer" @endif
            >
              <span class="slash-story-visual" aria-hidden="true">
                @if ($youtubeId !== '')
                  <div
                    class="slash-story-video"
                    data-youtube-id="{{ $youtubeId }}"
                    data-video-title="{{ $planName }}"
                  ></div>
                @else
                  <img
                    class="slash-story-poster"
                    src="{{ $planImage }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                  >
                @endif
              </span>
              <span class="slash-story-shade" aria-hidden="true"></span>
              <div class="slash-story-meta">
                <img
                  class="slash-story-avatar"
                  src="{{ $planImage }}"
                  alt=""
                  width="40"
                  height="40"
                  loading="lazy"
                  decoding="async"
                >
                <div>
                  <strong class="slash-story-name">{{ $planName }}</strong>
                  <span class="slash-story-role">{{ $meta['subtitle'] }}</span>
                </div>
              </div>
            </a>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
