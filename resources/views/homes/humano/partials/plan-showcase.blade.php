@php
  $planImages = [
    'assistant' => 'assets/img/illustrations/page-pricing-basic.png',
    'hunter' => 'assets/img/illustrations/page-pricing-basic.png',
    'business' => 'assets/img/illustrations/page-pricing-standard.png',
    'mentor' => 'assets/img/illustrations/page-pricing-enterprise.png',
  ];
@endphp

<section id="landingPlans" class="section-py landing-plans bg-white">
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
          $planImage = $planImages[$planId] ?? 'assets/img/illustrations/page-pricing-basic.png';
        @endphp
        <div class="row align-items-center gy-4 mb-4 mb-lg-5 landing-plan-row">
          @if ($contentOnRight)
            <div class="col-lg-5 col-xl-4 order-2 order-lg-1 text-center landing-plan-visual">
              <img
                src="{{ asset($planImage) }}"
                alt=""
                class="img-fluid landing-plan-illustration"
                height="200"
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
                src="{{ asset($planImage) }}"
                alt=""
                class="img-fluid landing-plan-illustration"
                height="200"
              />
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</section>
