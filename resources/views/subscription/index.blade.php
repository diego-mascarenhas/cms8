@extends('layouts/layoutMaster')

@section('title', 'Planes de Suscripción')

@section('content')
<!-- Pricing Plans -->
<div class="text-center mb-5">
	<h1 class="mb-2">Planes de Suscripción</h1>
	<p class="mb-5">Comienza con nosotros - es perfecto para individuos y equipos. Elige un plan que se ajuste a tus necesidades.</p>
</div>

@if(session('success'))
	<div class="alert alert-success alert-dismissible" role="alert">
		{{ session('success') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
@endif

@if(session('error') && !request('product_id'))
	<div class="alert alert-danger alert-dismissible" role="alert">
		{{ session('error') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
@endif

<!-- Mentoring Plans -->
@if($mentoringProducts->isNotEmpty())
	<div class="mb-5">
		<h3 class="mb-4">Mentoría</h3>
		<div class="row gy-4">
			@foreach($mentoringProducts as $product)
				<div class="{{ $product->plan === 'complete' ? 'col-12' : 'col-xl col-lg-4 col-md-6' }}">
					<div class="card border h-100 {{ $currentMentoringPlan && $product->plan === $currentMentoringPlan ? 'border-primary shadow-sm' : '' }}">
						<div class="card-body position-relative text-center d-flex flex-column">
							@if($currentMentoringPlan && $product->plan === $currentMentoringPlan)
								<div class="position-absolute end-0 me-4 top-0 mt-3">
									<span class="badge bg-label-primary">Plan Actual</span>
								</div>
							@endif

							<div class="mb-4">
								<div class="d-flex justify-content-center">
									<h1 class="mb-0 text-primary">{{ number_format($product->unit_amount ?? 0, 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ strtoupper($product->currency ?? 'EUR') }}</sup>
									@if($product->recurring_interval)
										<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ $product->getBillingFrequency() }}</sub>
									@endif
								</div>
							</div>

							<h4>{{ $product->name }}</h4>
							<p class="mb-4">{{ $product->description }}</p>

							<div class="mt-auto">
								@if($currentMentoringPlan && $product->plan === $currentMentoringPlan)
									<button class="btn btn-label-primary w-100" disabled>Tu Plan Actual</button>
								@elseif($mentoringSubscription && $product->stripe_price)
									@php
										$currentProduct = $mentoringProducts->firstWhere('stripe_price', $mentoringSubscription->stripe_price);
										$isUpgrade = $currentProduct && $product->unit_amount > $currentProduct->unit_amount;
									@endphp
									<button type="button" class="btn btn-primary w-100" onclick="confirmSwapProduct({{ $product->id }}, '{{ $product->name }}', {{ $isUpgrade ? 'true' : 'false' }})">
										{{ $isUpgrade ? 'Upgrade' : 'Downgrade' }}
									</button>
								@elseif($product->stripe_price)
									<button type="button" class="btn btn-primary w-100" onclick="showConfirmModal(null, null, {{ $product->unit_amount ?? 0 }}, '{{ strtoupper($product->currency ?? 'EUR') }}', {{ $product->id }}, '{{ $product->name }}', '{{ $product->description }}')">
										Suscribirse Ahora
									</button>
								@else
									<button class="btn btn-primary w-100" disabled>Próximamente</button>
								@endif
							</div>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	</div>
@endif

<!-- Mailer Plans -->
<div class="mb-5">
	<h3 class="mb-4">Mailer</h3>
	<div class="row gy-4">
	<!-- FREE Plan -->
	<div class="col-xl-3 col-lg-6 col-12">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::FREE ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::FREE)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif

				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">0</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">&nbsp;</small>
				</div>

				<h4>Free</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::FREE->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>{{ number_format(\App\Enums\EmailPlan::FREE->getMonthlyLimit()) }} emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>{{ number_format(\App\Enums\EmailPlan::FREE->getDailyLimit()) }} emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta {{ number_format(\App\Enums\EmailPlan::FREE->getContactLimit()) }} contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::FREE)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@else
					<button class="btn btn-label-secondary w-100 mt-auto" disabled>Downgrade</button>
				@endif
			</div>
		</div>
	</div>

	<!-- BASIC Plan -->
	<div class="col-xl-3 col-lg-6 col-12">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::BASIC ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::BASIC)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif

				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">{{ $prices['basic'] ? number_format($prices['basic']['amount'], 2, ',', '.') : '15,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
				</div>

				<h4>Basic</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::BASIC->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getDailyLimit()) }}</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::BASIC->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::BASIC)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<button type="button" class="btn btn-primary w-100" onclick="showConfirmModal('basic', 'Basic', {{ $prices['basic']['amount'] ?? 15.99 }}, '{{ $prices['basic']['currency'] ?? 'EUR' }}')">
						Suscribirse Ahora
					</button>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('basic', 'Basic')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::BASIC->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- FOUNDATION Plan -->
	<div class="col-xl-3 col-lg-6 col-12">
		<div class="card border border-primary shadow-sm h-100">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::FOUNDATION)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif

				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">{{ $prices['foundation'] ? number_format($prices['foundation']['amount'], 2, ',', '.') : '35,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
				</div>

				<h4>Foundation</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::FOUNDATION->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getDailyLimit()) }}</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::FOUNDATION->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::FOUNDATION)
					<button class="btn btn-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<button type="button" class="btn btn-primary w-100" onclick="showConfirmModal('foundation', 'Foundation', {{ $prices['foundation']['amount'] ?? 35.99 }}, '{{ $prices['foundation']['currency'] ?? 'EUR' }}')">
						Suscribirse Ahora
					</button>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('foundation', 'Foundation')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::FOUNDATION->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- SCALE Plan -->
	<div class="col-xl-3 col-lg-6 col-12">
		<div class="card border h-100 {{ $currentPlan === \App\Enums\EmailPlan::SCALE ? 'border-primary shadow-sm' : '' }}">
			<div class="card-body position-relative text-center d-flex flex-column">
				@if($currentPlan === \App\Enums\EmailPlan::SCALE)
					<div class="position-absolute end-0 me-4 top-0 mt-3">
						<span class="badge bg-label-primary">Plan Actual</span>
					</div>
				@endif

				<div class="mb-4">
					<div class="d-flex justify-content-center">
						<h1 class="mb-0 text-primary">{{ $prices['scale'] ? number_format($prices['scale']['amount'], 2, ',', '.') : '119,99' }}</h1>
						<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">€</sup>
						<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/mes</sub>
					</div>
					<small class="text-muted">+ I.V.A.</small>
				</div>

				<h4>Scale</h4>
				<p class="mb-4">{{ \App\Enums\EmailPlan::SCALE->getDescription() }}</p>

				<ul class="list-unstyled text-start mb-4 flex-grow-1">
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>{{ number_format(\App\Enums\EmailPlan::SCALE->getMonthlyLimit()) }}</strong> emails por mes</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span><strong>Ilimitados</strong> emails por día</span>
					</li>
					<li class="mb-2">
						<i class="ti ti-circle-check text-success ti-xs me-2"></i>
						<span>Hasta <strong>{{ number_format(\App\Enums\EmailPlan::SCALE->getContactLimit()) }}</strong> contactos</span>
					</li>
				</ul>

				@if($currentPlan === \App\Enums\EmailPlan::SCALE)
					<button class="btn btn-label-primary w-100 mt-auto" disabled>Tu Plan Actual</button>
				@elseif(!$subscription || !$subscription->active())
					<button type="button" class="btn btn-primary w-100" onclick="showConfirmModal('scale', 'Scale', {{ $prices['scale']['amount'] ?? 119.99 }}, '{{ $prices['scale']['currency'] ?? 'EUR' }}')">
						Suscribirse Ahora
					</button>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('scale', 'Scale')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::SCALE->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- Prospectos: planes recurrentes primero, luego pago único -->
@if($prospectProducts->isNotEmpty() || $prospectPacks->isNotEmpty() || !empty($prospectionConfig['enabled']))
	<div class="mb-5 mt-5" id="prospectos">
		<h3 class="mb-4">{{ __('Prospectos') }}</h3>
		<div class="row gy-4">
			{{-- 1. Planes recurrentes (suscripción mensual): desde BD o desde enum (Basic, Growth) como Mailer --}}
			@if($prospectProducts->isNotEmpty())
				@foreach($prospectProducts as $product)
				<div class="col-xl col-lg-4 col-md-6">
					<div class="card border h-100 {{ $prospectSubscription && $prospectSubscription->stripe_price === $product->stripe_price ? 'border-primary shadow-sm' : '' }}">
						<div class="card-body position-relative text-center d-flex flex-column">
							@if($prospectSubscription && $prospectSubscription->stripe_price === $product->stripe_price)
								<div class="position-absolute end-0 me-4 top-0 mt-3">
									<span class="badge bg-label-primary">{{ __('Plan actual') }}</span>
								</div>
							@endif
							<div class="mb-4">
								<div class="d-flex justify-content-center flex-wrap">
									<h1 class="mb-0 text-primary">{{ number_format($product->unit_amount ?? 0, 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ strtoupper($product->currency ?? 'EUR') }}</sup>
									@if($product->recurring_interval)
										<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ $product->getBillingFrequency() }}</sub>
									@endif
								</div>
								<p class="small text-muted mb-0">{{ __('Mínimo 3 meses') }}</p>
							</div>
							<h4>{{ $product->name }}</h4>
							<p class="mb-4">{{ $product->description }}</p>
							@if($product->metadata['monthly_credits'] ?? null)
								<p class="small text-muted mb-3">{{ $product->metadata['monthly_credits'] }} {{ __('créditos/mes') }}</p>
							@endif
							<div class="mt-auto">
								@if($prospectSubscription && $prospectSubscription->stripe_price === $product->stripe_price)
									<button class="btn btn-label-primary w-100" disabled>{{ __('Tu plan actual') }}</button>
								@elseif($product->stripe_price)
									<button type="button" class="btn btn-primary w-100" onclick="showConfirmModal(null, null, {{ $product->unit_amount ?? 0 }}, '{{ strtoupper($product->currency ?? 'EUR') }}', {{ $product->id }}, '{{ addslashes($product->name) }}', '{{ addslashes($product->description ?? '') }}')">
										{{ __('Suscribirse ahora') }}
									</button>
								@else
									<button class="btn btn-primary w-100" disabled>{{ __('Suscribirse ahora') }}</button>
								@endif
							</div>
						</div>
					</div>
				</div>
				@endforeach
			@else
				{{-- Planes desde enum (Basic, Growth) cuando no hay productos en BD --}}
				@foreach([\App\Enums\ProspectPlan::BASIC, \App\Enums\ProspectPlan::GROWTH] as $plan)
				@php
					$price = $prospectPrices[$plan->value] ?? null;
					$amount = $price['amount'] ?? ($plan->value === 'basic' ? 9.99 : 29.99);
					$currency = $price['currency'] ?? 'EUR';
					$intervalCount = (int) ($price['interval_count'] ?? 1);
					$isTrimestral = ($price['interval'] ?? 'month') === 'month' && $intervalCount === 3;
					// Mostrar precio mensual siempre; si el cobro es trimestral, mostrar equivalente mensual (amount/3)
					$displayAmount = $isTrimestral ? round($amount / 3, 2) : $amount;
					// Importe trimestral: si ya es trimestral es $amount; si es mensual es amount*3
					$trimestralAmount = $isTrimestral ? $amount : round($amount * 3, 2);
					$commitmentLabel = __('Precio trimestral') . ' ' . number_format($trimestralAmount, 2, ',', '.') . ' ' . $currency;
					$planDisplayName = config("prospects.plan_display_names.{$plan->value}") ?? $plan->getDisplayName();
				@endphp
				<div class="col-xl col-lg-4 col-md-6">
					<div class="card border h-100 {{ $currentProspectPlan === $plan ? 'border-primary shadow-sm' : '' }}">
						<div class="card-body position-relative text-center d-flex flex-column">
							@if($currentProspectPlan === $plan)
								<div class="position-absolute end-0 me-4 top-0 mt-3">
									<span class="badge bg-label-primary">{{ __('Plan actual') }}</span>
								</div>
							@endif
							<div class="mb-4">
								<div class="d-flex justify-content-center flex-wrap">
									<h1 class="mb-0 text-primary">{{ number_format($displayAmount, 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ $currency }}</sup>
									<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ __('mes') }}</sub>
								</div>
								<p class="small text-muted mb-0">{{ $commitmentLabel }}</p>
							</div>
							<h4>{{ $planDisplayName }}</h4>
							<p class="mb-4">{{ $plan->getDescription() }}</p>
							<p class="small text-muted mb-3">{{ $plan->getMonthlyCredits() }} {{ __('créditos/mes') }}</p>
							<div class="mt-auto">
								@if($currentProspectPlan === $plan)
									<button class="btn btn-label-primary w-100" disabled>{{ __('Tu plan actual') }}</button>
								@elseif($plan->getStripePriceId())
									<button type="button" class="btn btn-primary w-100" onclick="showConfirmModalProspect('{{ $plan->value }}', '{{ addslashes($planDisplayName) }}', {{ $amount }}, '{{ $currency }}')">
										{{ __('Suscribirse ahora') }}
									</button>
								@else
									<button class="btn btn-primary w-100" disabled>{{ __('Suscribirse ahora') }}</button>
								@endif
							</div>
						</div>
					</div>
				</div>
				@endforeach
			@endif
			{{-- 2. Pago único (packs o producto legacy) --}}
			@foreach($prospectPacks as $product)
				<div class="col-xl col-lg-4 col-md-6">
					<div class="card border h-100">
						<div class="card-body position-relative text-center d-flex flex-column">
							<div class="mb-4">
								<div class="d-flex justify-content-center flex-wrap">
									<h1 class="mb-0 text-primary">{{ number_format($product->unit_amount ?? 0, 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ strtoupper($product->currency ?? 'EUR') }}</sup>
									<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ __('pago único') }}</sub>
								</div>
							</div>
							<h4>{{ $product->name }}</h4>
							<p class="mb-4">{{ $product->description }}</p>
							@php
								$creditPacks = config('prospects.credit_packs', []);
								$packCredits = $creditPacks[$product->stripe_price] ?? 0;
								$credits = (int)($product->metadata['credits'] ?? $packCredits);
							@endphp
							@if($credits > 0)
								<p class="small text-muted mb-3">{{ $credits }} {{ __('créditos') }}</p>
							@endif
							<div class="mt-auto">
								@if($product->stripe_price)
									<button type="button" class="btn btn-outline-primary w-100" onclick="showConfirmModal(null, null, {{ $product->unit_amount ?? 0 }}, '{{ strtoupper($product->currency ?? 'EUR') }}', {{ $product->id }}, '{{ addslashes($product->name) }}', '{{ addslashes($product->description ?? '') }}')">
										{{ __('Comprar créditos') }}
									</button>
								@else
									<button class="btn btn-outline-primary w-100" disabled>{{ __('Próximamente') }}</button>
								@endif
							</div>
						</div>
					</div>
				</div>
			@endforeach
			{{-- Pago único: Prospection (siempre visible en Prospectos) --}}
			<div class="col-xl col-lg-4 col-md-6">
				<div class="card border h-100">
					<div class="card-body position-relative text-center d-flex flex-column">
						<div class="mb-4">
							<div class="d-flex justify-content-center">
								@if(isset($prospectionConfig['amount']) && $prospectionConfig['amount'] !== null)
									<h1 class="mb-0 text-primary">{{ number_format($prospectionConfig['amount'], 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ strtoupper($prospectionConfig['currency'] ?? 'EUR') }}</sup>
								@else
									<h1 class="mb-0 text-primary">—</h1>
								@endif
								<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ __('pago único') }}</sub>
							</div>
						</div>
						<h4>{{ $prospectionConfig['name'] ?? __('Prospection') }}</h4>
						<p class="mb-4">{{ $prospectionConfig['description'] ?? __('Crédito para la búsqueda de prospectos para que puedas transformarlos en clientes.') }}</p>
						@if(!empty($prospectionConfig['credits']))
							<p class="small text-muted mb-3">{{ $prospectionConfig['credits'] }} {{ __('créditos') }}</p>
						@endif
						<div class="mt-auto">
							@if(!empty($prospectionConfig['enabled']))
								<button type="button" class="btn btn-primary w-100" onclick="showProspectionConfirmModal()">
									{{ __('Contratar') }}
								</button>
							@else
								<button class="btn btn-primary w-100" disabled>{{ __('Próximamente') }}</button>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
			window.prospectionConfig = {
				name: @json($prospectionConfig['name'] ?? 'Prospection'),
				description: @json($prospectionConfig['description'] ?? ''),
				amount: {{ $prospectionConfig['amount'] ?? 0 }},
				currency: @json($prospectionConfig['currency'] ?? 'EUR'),
				app_url: @json($prospectionConfig['app_url'] ?? ''),
			};
		</script>
	</div>
@endif

<!-- Hosting Plans (hosting + support in same section) -->
@if($hostingProducts->isNotEmpty())
	<div class="mb-5 mt-5">
		<h3 class="mb-4">{{ __('Hosting') }}</h3>
		<div class="row gy-4">
			@foreach($hostingProducts as $product)
				<div class="col-lg-6 col-12">
					<div class="card border h-100">
						<div class="card-body position-relative text-center d-flex flex-column">
							<div class="mb-4">
								<div class="d-flex justify-content-center">
									<h1 class="mb-0 text-primary">{{ number_format($product->unit_amount ?? 0, 2, ',', '.') }}</h1>
									<sup class="h6 pricing-currency mt-2 mb-0 ms-1 text-body">{{ strtoupper($product->currency ?? 'EUR') }}</sup>
									@if($product->recurring_interval)
										<sub class="h6 pricing-duration mt-auto mb-3 text-muted">/{{ $product->getBillingFrequency() }}</sub>
									@endif
								</div>
							</div>

							<h4>{{ $product->name }}</h4>
							<p class="mb-4">{{ $product->description }}</p>

							<div class="mt-auto">
								@php
									$isSupport = $product->category === 'support';
								@endphp
								@if($product->stripe_price)
									<button type="button" class="btn btn-primary w-100" onclick="showDomainModal({{ $product->id }}, {{ json_encode($product->name) }}, {{ $isSupport ? 'true' : 'false' }})">
										{{ __('Contratar') }}
									</button>
								@else
									<button class="btn btn-primary w-100" disabled>{{ __('Próximamente') }}</button>
								@endif
							</div>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	</div>
@endif

<!-- Modal Cambiar Plan -->
<div class="modal fade" id="swapPlanModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="swapPlanModalTitle">¿Cambiar Plan?</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p id="swapPlanModalText"></p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
				<form id="swapPlanForm" method="POST" action="{{ route('subscription.swap') }}" style="display: inline;">
					@csrf
					<input type="hidden" name="plan" id="swapPlanInput">
					<input type="hidden" name="product_id" id="swapProductInput">
					<button type="submit" class="btn btn-primary">Sí, cambiar plan</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Modal Cancelar Suscripción -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">¿Cancelar Suscripción?</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p>Tu suscripción seguirá activa hasta el final del período de facturación.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">No, mantener</button>
				<form method="POST" action="{{ route('subscription.cancel') }}" style="display: inline;">
					@csrf
					<button type="submit" class="btn btn-danger">Sí, cancelar</button>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Modal Confirmación con Cupón -->
<div class="modal fade" id="confirmSubscriptionModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Confirmar Suscripción</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<h6 id="confirmPlanName"></h6>
					<p class="text-muted mb-0" id="confirmPlanDescription"></p>
				</div>

				<div class="mb-3">
					<label for="couponCode" class="form-label">Cupón de Descuento (Opcional)</label>
					<div class="input-group">
						<input type="text" class="form-control" id="couponCode" placeholder="Ingresa el código del cupón">
						<button type="button" class="btn btn-outline-primary" onclick="validateCoupon()">Aplicar</button>
					</div>
					<div id="couponMessage" class="mt-2"></div>
					<div id="couponDiscount" class="mt-2"></div>
				</div>

				<hr>

				<div class="d-flex justify-content-between align-items-center">
					<span>Precio:</span>
					<strong id="confirmPrice"></strong>
				</div>
				<div id="discountRow" class="d-flex justify-content-between align-items-center text-success" style="display: none !important;">
					<span>Descuento:</span>
					<strong id="discountAmount"></strong>
				</div>
				<div class="d-flex justify-content-between align-items-center mt-2">
					<span class="h6 mb-0">Total:</span>
					<span class="h5 mb-0 text-primary" id="confirmTotal"></span>
				</div>

				<form id="confirmSubscriptionForm" method="POST" action="{{ route('subscription.checkout') }}">
					@csrf
					<input type="hidden" name="plan" id="confirmPlanInput">
					<input type="hidden" name="product_id" id="confirmProductIdInput">
					<input type="hidden" name="prospect_plan" id="confirmProspectPlanInput" value="">
					<input type="hidden" name="prospection" id="confirmProspectionInput" value="">
					<input type="hidden" name="coupon" id="confirmCouponInput">
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" onclick="submitConfirmation()">Confirmar y Continuar</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal Solicitar Dominio -->
<div class="modal fade" id="domainModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="domainModalTitle">Información del Dominio</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				@if(session('error') && request('product_id'))
					<div class="alert alert-danger mb-3">
						{{ session('error') }}
					</div>
				@endif

				<form id="domainForm" method="POST" action="{{ route('subscription.checkout') }}">
					@csrf
					<input type="hidden" name="product_id" id="domainProductId">
					<input type="hidden" name="domain" id="domainInput">
					<input type="hidden" name="coupon" id="domainCouponInput">

					<div class="mb-3">
						<label for="domain" class="form-label" id="domainLabel">Dominio (*)</label>
						<input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain') }}" placeholder="ejemplo.com">
						@error('domain')
							<div class="invalid-feedback d-block">{{ $message }}</div>
						@enderror
					</div>

					<div class="mb-3">
						<label for="domainCouponCode" class="form-label">Cupón de Descuento (Opcional)</label>
						<div class="input-group">
							<input type="text" class="form-control" id="domainCouponCode" placeholder="Ingresa el código del cupón">
							<button type="button" class="btn btn-outline-primary" onclick="validateDomainCoupon()">Aplicar</button>
						</div>
						<div id="domainCouponMessage" class="mt-2"></div>
						<div id="domainCouponDiscount" class="mt-2"></div>
					</div>

					<div class="alert alert-info mb-0">
						<small id="domainAlertText">
							Este dominio se utilizará para configurar tu servicio de hosting WordPress.
						</small>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" onclick="submitDomainForm()">Continuar</button>
			</div>
		</div>
	</div>
</div>

@section('vendor-script')
@endsection

@section('page-style')
@endsection

<script>
function confirmCancel()
{
    const modal = new bootstrap.Modal(document.getElementById('cancelSubscriptionModal'));
    modal.show();
}

function confirmSwap(plan, planName)
{
    // Update modal content
    document.getElementById('swapPlanModalText').textContent = `¿Cambiar al plan ${planName}? Los cambios tomarán efecto inmediatamente.`;
    document.getElementById('swapPlanInput').value = plan;
    document.getElementById('swapProductInput').value = '';

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('swapPlanModal'));
    modal.show();
}

function confirmSwapProduct(productId, productName, isUpgrade)
{
    // Update modal content
    const action = isUpgrade ? 'mejorar' : 'degradar';
    document.getElementById('swapPlanModalText').textContent = `¿${isUpgrade ? 'Mejorar' : 'Degradar'} al plan ${productName}? Los cambios tomarán efecto inmediatamente.`;
    document.getElementById('swapPlanInput').value = '';
    document.getElementById('swapProductInput').value = productId;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('swapPlanModal'));
    modal.show();
}

function showConfirmModalProspect(planKey, planName, price, currency)
{
    document.getElementById('couponCode').value = '';
    document.getElementById('couponMessage').innerHTML = '';
    document.getElementById('couponDiscount').innerHTML = '';
    document.getElementById('discountRow').style.display = 'none';
    document.getElementById('confirmCouponInput').value = '';
    document.getElementById('confirmProspectionInput').value = '';
    document.getElementById('confirmPlanInput').value = '';
    document.getElementById('confirmProductIdInput').value = '';
    document.getElementById('confirmProspectPlanInput').value = planKey;
    document.getElementById('confirmPlanName').textContent = planName;
    document.getElementById('confirmPlanDescription').textContent = '';
    currentPrice = price;
    currentCurrency = currency;
    document.getElementById('confirmPrice').textContent = (parseFloat(price)).toFixed(2).replace('.', ',') + ' ' + currency;
    document.getElementById('confirmTotal').textContent = (parseFloat(price)).toFixed(2).replace('.', ',') + ' ' + currency;
    appliedCoupon = null;
    const modal = new bootstrap.Modal(document.getElementById('confirmSubscriptionModal'));
    modal.show();
}

function showProspectionConfirmModal()
{
    if (typeof window.prospectionConfig === 'undefined') return;
    const c = window.prospectionConfig;
    document.getElementById('confirmPlanInput').value = '';
    document.getElementById('confirmProductIdInput').value = '';
    document.getElementById('confirmProspectPlanInput').value = '';
    document.getElementById('confirmProspectionInput').value = '1';
    document.getElementById('confirmPlanName').textContent = c.name;
    document.getElementById('confirmPlanDescription').textContent = c.description || '';
    currentPrice = c.amount || 0;
    currentCurrency = c.currency || 'EUR';
    document.getElementById('confirmPrice').textContent = (parseFloat(currentPrice)).toFixed(2).replace('.', ',') + ' ' + currentCurrency;
    document.getElementById('confirmTotal').textContent = (parseFloat(currentPrice)).toFixed(2).replace('.', ',') + ' ' + currentCurrency;
    document.getElementById('couponCode').value = '';
    document.getElementById('couponMessage').innerHTML = '';
    document.getElementById('couponDiscount').innerHTML = '';
    document.getElementById('confirmCouponInput').value = '';
    document.getElementById('discountRow').style.display = 'none';
    appliedCoupon = null;
    const modal = new bootstrap.Modal(document.getElementById('confirmSubscriptionModal'));
    modal.show();
}

function showDomainModal(productId, productName, isSupport)
{
    // Update modal content
    document.getElementById('domainModalTitle').textContent = productName;
    document.getElementById('domainProductId').value = productId;
    document.getElementById('domain').value = '{{ old('domain') }}';

    if (isSupport) {
        document.getElementById('domainLabel').textContent = 'Dominio existente (*)';
        document.getElementById('domainAlertText').textContent = 'Este soporte se asociará a un dominio existente. Asegúrate de que el dominio ya esté configurado.';
    } else {
        document.getElementById('domainLabel').textContent = 'Dominio (*)';
        document.getElementById('domainAlertText').textContent = 'Este dominio se utilizará para configurar tu servicio de hosting WordPress.';
    }

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('domainModal'));
    modal.show();
}

let domainProductPrice = 0;
let domainProductCurrency = 'EUR';

function showDomainModal(productId, productName, isSupport)
{
    // Reset coupon
    document.getElementById('domainCouponCode').value = '';
    document.getElementById('domainCouponMessage').innerHTML = '';
    document.getElementById('domainCouponDiscount').innerHTML = '';
    document.getElementById('domainCouponInput').value = '';

    // Update modal content
    document.getElementById('domainModalTitle').textContent = productName;
    document.getElementById('domainProductId').value = productId;
    document.getElementById('domain').value = '{{ old('domain') }}';

    if (isSupport) {
        document.getElementById('domainLabel').textContent = 'Dominio existente (*)';
        document.getElementById('domainAlertText').textContent = 'Este soporte se asociará a un dominio existente. Asegúrate de que el dominio ya esté configurado.';
    } else {
        document.getElementById('domainLabel').textContent = 'Dominio (*)';
        document.getElementById('domainAlertText').textContent = 'Este dominio se utilizará para configurar tu servicio de hosting WordPress.';
    }

    // Get product price (we'll need to fetch this or pass it)
    // For now, we'll get it from the card on the page
    const productCard = document.querySelector(`[data-product-id="${productId}"]`);
    if (productCard) {
        const priceText = productCard.querySelector('.text-primary')?.textContent;
        if (priceText) {
            domainProductPrice = parseFloat(priceText.replace(/[^\d,]/g, '').replace(',', '.'));
        }
    }

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('domainModal'));
    modal.show();
}

function validateDomainCoupon()
{
    const couponCode = document.getElementById('domainCouponCode').value.trim();
    const messageDiv = document.getElementById('domainCouponMessage');
    const discountDiv = document.getElementById('domainCouponDiscount');

    if (!couponCode) {
        messageDiv.innerHTML = '<div class="alert alert-warning">Por favor, ingresa un código de cupón.</div>';
        return;
    }

    // Show loading
    messageDiv.innerHTML = '<div class="text-muted">Validando cupón...</div>';
    discountDiv.innerHTML = '';

    // Validate coupon via AJAX
    fetch('{{ route('subscription.validate-coupon') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ coupon: couponCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            // Store promotion code ID (not coupon ID) for Stripe
            document.getElementById('domainCouponInput').value = data.coupon.promotion_code_id;

            // Calculate discount
            let discountText = '';
            if (data.coupon.percent_off) {
                discountText = `${data.coupon.percent_off}% de descuento`;
            } else if (data.coupon.amount_off) {
                const discountAmount = data.coupon.amount_off / 100;
                discountText = `${discountAmount.toFixed(2)} ${data.coupon.currency.toUpperCase()} de descuento`;
            }

            messageDiv.innerHTML = `<div class="alert alert-success">¡Cupón aplicado correctamente!</div>`;
            discountDiv.innerHTML = `<small class="text-success">${discountText}</small>`;
        } else {
            document.getElementById('domainCouponInput').value = '';
            messageDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'El cupón no es válido.'}</div>`;
            discountDiv.innerHTML = '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '<div class="alert alert-danger">Error al validar el cupón. Por favor, intenta nuevamente.</div>';
        discountDiv.innerHTML = '';
    });
}

function submitDomainForm()
{
    // Update domain input
    document.getElementById('domainInput').value = document.getElementById('domain').value;
    
    // Submit form directly - validation will be done by Laravel
    document.getElementById('domainForm').submit();
}

let currentPrice = 0;
let currentCurrency = 'EUR';
let appliedCoupon = null;

function showConfirmModal(plan, planName, price, currency, productId = null, productName = null, productDescription = null)
{
    // Reset coupon, prospection and prospect plan
    document.getElementById('couponCode').value = '';
    document.getElementById('couponMessage').innerHTML = '';
    document.getElementById('couponDiscount').innerHTML = '';
    document.getElementById('discountRow').style.display = 'none';
    document.getElementById('confirmCouponInput').value = '';
    document.getElementById('confirmProspectionInput').value = '';
    document.getElementById('confirmProspectPlanInput').value = '';
    appliedCoupon = null;

    // Set form values
    if (productId) {
        document.getElementById('confirmPlanInput').value = '';
        document.getElementById('confirmProductIdInput').value = productId;
        document.getElementById('confirmProspectPlanInput').value = '';
        document.getElementById('confirmPlanName').textContent = productName || 'Producto';
        document.getElementById('confirmPlanDescription').textContent = productDescription || '';
    } else {
        document.getElementById('confirmPlanInput').value = plan;
        document.getElementById('confirmProductIdInput').value = '';
        document.getElementById('confirmProspectPlanInput').value = '';
        document.getElementById('confirmPlanName').textContent = `Plan ${planName}`;
        document.getElementById('confirmPlanDescription').textContent = '';
    }

    // Set price
    currentPrice = price;
    currentCurrency = currency;
    document.getElementById('confirmPrice').textContent = `${parseFloat(price).toFixed(2).replace('.', ',')} ${currency}`;
    document.getElementById('confirmTotal').textContent = `${parseFloat(price).toFixed(2).replace('.', ',')} ${currency}`;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('confirmSubscriptionModal'));
    modal.show();
}

function validateCoupon()
{
    const couponCode = document.getElementById('couponCode').value.trim();
    const messageDiv = document.getElementById('couponMessage');
    const discountDiv = document.getElementById('couponDiscount');
    const discountRow = document.getElementById('discountRow');

    if (!couponCode) {
        messageDiv.innerHTML = '<div class="alert alert-warning">Por favor, ingresa un código de cupón.</div>';
        return;
    }

    // Show loading
    messageDiv.innerHTML = '<div class="text-muted">Validando cupón...</div>';
    discountDiv.innerHTML = '';
    discountRow.style.display = 'none';

    // Validate coupon via AJAX
    fetch('{{ route('subscription.validate-coupon') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ coupon: couponCode })
    })
    .then(response => response.json())
    .then(data => {
            if (data.valid) {
                appliedCoupon = data.coupon;
                // Store promotion code ID (not coupon ID) for Stripe
                document.getElementById('confirmCouponInput').value = data.coupon.promotion_code_id;

                // Calculate discount
                let discountAmount = 0;
                let discountText = '';

                if (data.coupon.percent_off) {
                    discountAmount = (currentPrice * data.coupon.percent_off) / 100;
                    discountText = `${data.coupon.percent_off}% de descuento`;
                } else if (data.coupon.amount_off) {
                    discountAmount = data.coupon.amount_off / 100; // Stripe stores in cents
                    discountText = `${discountAmount.toFixed(2)} ${data.coupon.currency.toUpperCase()} de descuento`;
                }

            const finalPrice = currentPrice - discountAmount;

            // Show success message
            messageDiv.innerHTML = `<div class="alert alert-success">¡Cupón aplicado correctamente!</div>`;
            discountDiv.innerHTML = `<small class="text-success">${discountText}</small>`;
            discountRow.style.display = 'flex';
            document.getElementById('discountAmount').textContent = `-${discountAmount.toFixed(2).replace('.', ',')} ${currentCurrency}`;
            document.getElementById('confirmTotal').textContent = `${finalPrice.toFixed(2).replace('.', ',')} ${currentCurrency}`;
        } else {
            appliedCoupon = null;
            document.getElementById('confirmCouponInput').value = '';
            messageDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'El cupón no es válido.'}</div>`;
            discountDiv.innerHTML = '';
            discountRow.style.display = 'none';
            document.getElementById('confirmTotal').textContent = `${currentPrice.toFixed(2).replace('.', ',')} ${currentCurrency}`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '<div class="alert alert-danger">Error al validar el cupón. Por favor, intenta nuevamente.</div>';
        discountDiv.innerHTML = '';
        discountRow.style.display = 'none';
    });
}

function submitConfirmation()
{
    document.getElementById('confirmSubscriptionForm').submit();
}

// Show modal automatically if there are validation errors or session error and product_id is present
@if(($errors->has('domain') || session('error')) && request('product_id'))
    document.addEventListener('DOMContentLoaded', function() {
        @php
            $product = \App\Models\SubscriptionProduct::find(request('product_id'));
            $isSupport = $product && $product->category === 'support';
        @endphp
        showDomainModal({{ request('product_id') }}, '{{ $product ? $product->name : 'Producto' }}', {{ $isSupport ? 'true' : 'false' }});
    });
@endif
</script>
@endsection
