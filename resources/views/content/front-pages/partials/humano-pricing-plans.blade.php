@php
$showPageHeader = $showPageHeader ?? true;
$showFlashAlerts = $showFlashAlerts ?? true;
  $planImages = $planImages ?? [
    'assistant' => 'assets/img/illustrations/page-pricing-basic.png',
    'hunter' => 'homes/humano/img/plans/hunter.png',
    'business' => 'assets/img/illustrations/page-pricing-standard.png',
    'mentor' => 'assets/img/illustrations/page-pricing-enterprise.png',
    'innovation' => 'homes/humano/img/plans/innovation.png',
  ];
@endphp

@if ($showPageHeader)
  <h2 class="text-center mb-2">{{ __('humano_pricing.hero_title') }}</h2>
  <p class="text-center text-muted col-lg-8 mx-auto mb-0">{{ __('humano_pricing.hero_subtitle') }}</p>

  @if (! app()->isProduction())
    <p class="text-center small text-warning mt-2 mb-0">{{ __('humano_pricing.staging_note') }}</p>
  @endif
@endif

@if ($showFlashAlerts)
  @if (session('success'))
    <div class="alert alert-success col-lg-8 mx-auto mt-3 mb-0" role="alert">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger col-lg-8 mx-auto mt-3 mb-0" role="alert">
      {{ session('error') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger col-lg-8 mx-auto mt-3 mb-0" role="alert">
      <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $message)
          <li>{{ $message }}</li>
        @endforeach
      </ul>
    </div>
  @endif
@endif

<div class="d-flex align-items-center justify-content-center flex-wrap gap-2 pb-4 pt-4 mb-0 mb-md-3">
  <label class="switch switch-primary ms-3 ms-sm-0 mt-2">
    <span class="switch-label">{{ __('humano_pricing.billing_monthly') }}</span>
    <input type="checkbox" class="switch-input price-duration-toggler" checked />
    <span class="switch-toggle-slider">
      <span class="switch-on"></span>
      <span class="switch-off"></span>
    </span>
    <span class="switch-label">{{ __('humano_pricing.billing_annual') }}</span>
  </label>
  <div class="mt-n5 ms-n5 d-none d-sm-block">
    <i class="ti ti-corner-left-down ti-sm text-muted me-1 scaleX-n1-rtl"></i>
    <span class="badge badge-sm bg-label-primary">{{ __('humano_pricing.annual_discount_badge') }}</span>
  </div>
</div>

<div class="row mx-0 gy-3 px-lg-4 justify-content-center">
  @foreach ($plans as $plan)
    @php
      $id = $plan['id'];
      $img = $planImages[$id] ?? 'assets/img/illustrations/page-pricing-basic.png';
      $checkoutAvailable = (bool) ($plan['checkout_available'] ?? true);
      $externalUrl = trim((string) ($plan['external_url'] ?? ''));
      $highlightPopular = $checkoutAvailable && ! empty($plan['popular']);
      $cardBorder = $highlightPopular ? 'border-primary border' : 'border rounded';
    @endphp
    <div class="col-md-6 col-lg-4 col-xl-4 mb-md-0 mb-4" id="plan-{{ $id }}">
      <div class="card {{ $cardBorder }} shadow-none h-100 d-flex flex-column">
        <div class="card-body d-flex flex-column flex-grow-1">
          @if ($highlightPopular)
            <div class="position-relative">
              <div class="position-absolute end-0 top-0">
                <span class="badge bg-label-primary">{{ __('humano_pricing.most_popular') }}</span>
              </div>
            </div>
          @endif
          <div class="my-3 pt-2 text-center">
            <img src="{{ asset($img) }}" alt="" height="140" class="img-fluid">
          </div>
          <h3 class="card-title text-center mb-1">{{ __('humano_pricing.plans.'.$id.'.name') }}</h3>
          <div class="pricing-plan-description-slot">
            <p class="text-center text-muted small mb-0">{{ __('humano_pricing.plans.'.$id.'.description') }}</p>
          </div>

          <div class="text-center my-2 pricing-plan-amount-slot">
            @if ($checkoutAvailable)
              <div class="d-flex justify-content-center align-items-baseline flex-wrap gap-1">
                <h1 class="price-toggle price-yearly display-4 mb-0 text-primary">{{ $plan['yearly_amount'] }}</h1>
                <h1 class="price-toggle price-monthly display-4 mb-0 text-primary d-none">{{ $plan['monthly_amount'] }}</h1>
                <span class="h4 text-muted mb-0">€</span>
                <span class="h6 text-muted pricing-duration mt-auto mb-2 fw-normal price-toggle price-yearly">{{ __('humano_pricing.per_year_suffix') }}</span>
                <span class="h6 text-muted pricing-duration mt-auto mb-2 fw-normal price-toggle price-monthly d-none">{{ __('humano_pricing.per_month_suffix') }}</span>
              </div>
              <small class="price-yearly text-muted d-block mb-0">{{ __('humano_pricing.billed_annually') }}</small>
              <small class="price-monthly text-muted d-none">{{ __('humano_pricing.billed_monthly') }}</small>
              <small class="d-block text-muted mt-1 mb-0">{{ __('humano_pricing.prices_plus_vat') }}</small>
            @elseif ($externalUrl !== '')
              <p class="mb-0 text-muted fs-5 fw-semibold">{{ __('humano_pricing.external_pricing') }}</p>
            @else
              <p class="mb-0 text-muted fs-5 fw-semibold">{{ __('humano_pricing.coming_soon') }}</p>
            @endif
          </div>

          @php
            $planFeatures = trans('humano_pricing.plans.'.$id.'.features');
          @endphp
          @if (is_array($planFeatures) && $planFeatures !== [])
            <ul class="ps-0 my-3 pt-2 circle-bullets flex-grow-1">
              @foreach ($planFeatures as $feature)
                <li class="mb-2 d-flex align-items-start gap-2 text-start">
                  <i class="ti ti-point ti-lg flex-shrink-0 text-primary" aria-hidden="true"></i>
                  <span>{{ $feature }}</span>
                </li>
              @endforeach
            </ul>
          @endif

          <div class="mt-auto pt-2">
            @if ($checkoutAvailable)
              <a
                href="{{ $plan['checkout_href_monthly'] ?? $plan['checkout_href'] }}"
                class="btn btn-primary d-grid w-100 price-checkout-link"
                data-checkout-monthly="{{ $plan['checkout_href_monthly'] ?? $plan['checkout_href'] }}"
                data-checkout-yearly="{{ $plan['checkout_href_yearly'] ?? $plan['checkout_href_monthly'] ?? $plan['checkout_href'] }}"
              >
                {{ __('humano_pricing.subscribe') }}
              </a>
            @elseif ($externalUrl !== '')
              <a href="{{ $externalUrl }}" class="btn btn-label-primary d-grid w-100" target="_blank" rel="noopener noreferrer">
                {{ __('humano_pricing.external_cta') }}
              </a>
            @else
              <span class="btn {{ $highlightPopular ? 'btn-primary' : 'btn-label-primary' }} d-grid w-100 disabled" style="cursor: not-allowed; pointer-events: none;">
                {{ __('humano_pricing.coming_soon') }}
              </span>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>
