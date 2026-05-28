@php
  use App\Support\HumanoHomeAsset;

  $planImages = [
    'assistant' => HumanoHomeAsset::url('img/plans/assistant.png'),
    'hunter' => HumanoHomeAsset::url('img/plans/hunter.png'),
    'business' => HumanoHomeAsset::url('img/plans/business.png'),
    'mentor' => HumanoHomeAsset::url('img/plans/mentor.png'),
  ];
@endphp

<section id="landingPlans" class="section-py landing-plans bg-body">
  <div class="container">
    <div class="text-center mb-4 mb-md-5 pb-1">
      <span class="badge bg-label-primary mb-3">{{ __('humano_pricing.landing_plans_badge') }}</span>
      <h3 class="mb-2"><span class="section-title">{{ __('humano_pricing.landing_plans_title') }}</span></h3>
      <p class="text-muted mb-0 col-lg-8 mx-auto">{{ __('humano_pricing.landing_plans_subtitle') }}</p>
    </div>

    <div class="landing-plans-stack">
      @foreach ($landingPlans as $index => $plan)
        @php
          $planId = $plan['id'];
          $contentOnRight = $index % 2 === 1;
          $planImage = $planImages[$planId] ?? HumanoHomeAsset::url('img/plans/assistant.png');
        @endphp
        <div class="row align-items-center gy-4 mb-4 mb-lg-5 landing-plan-row">
          @if ($contentOnRight)
            <div class="col-lg-5 col-xl-4 order-2 order-lg-1 text-center landing-plan-visual">
              <img
                src="{{ $planImage }}"
                alt=""
                class="img-fluid landing-plan-illustration"
              />
            </div>
            <div class="col-lg-7 col-xl-7 order-1 order-lg-2 ms-lg-auto landing-plan-copy">
              @include('homes.humano.partials.plan-showcase-copy', ['plan' => $plan, 'planId' => $planId])
            </div>
          @else
            <div class="col-lg-7 col-xl-7 landing-plan-copy">
              @include('homes.humano.partials.plan-showcase-copy', ['plan' => $plan, 'planId' => $planId])
            </div>
            <div class="col-lg-5 col-xl-4 text-center landing-plan-visual">
              <img
                src="{{ $planImage }}"
                alt=""
                class="img-fluid landing-plan-illustration"
              />
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</section>
