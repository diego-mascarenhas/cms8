@php
  $externalUrl = trim((string) ($plan['external_url'] ?? ''));
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h4 class="mb-0">{{ __('humano_pricing.plans.'.$planId.'.name') }}</h4>
  @if (! empty($plan['popular']))
    <span class="badge bg-label-primary">{{ __('humano_pricing.most_popular') }}</span>
  @endif
</div>
<p class="text-muted mb-4">{{ __('humano_pricing.plans.'.$planId.'.description') }}</p>
<ul class="list-unstyled mb-4">
  @foreach (trans('humano_pricing.plans.'.$planId.'.features') as $feature)
    <li class="d-flex align-items-start gap-2 mb-2">
      <i class="ti ti-point text-primary mt-1 flex-shrink-0"></i>
      <span>{{ $feature }}</span>
    </li>
  @endforeach
</ul>
@if ($externalUrl !== '')
  <a href="{{ $externalUrl }}" class="btn btn-label-primary" target="_blank" rel="noopener noreferrer">
    {{ __('humano_pricing.external_cta') }}
    <i class="ti ti-arrow-right ti-xs ms-1"></i>
  </a>
@else
  <a href="{{ route('pricing') }}#plan-{{ $planId }}" class="btn btn-label-primary">
    {{ __('humano_pricing.landing_plans_cta') }}
    <i class="ti ti-arrow-right ti-xs ms-1"></i>
  </a>
@endif
