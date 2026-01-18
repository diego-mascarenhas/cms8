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

@if(session('error'))
	<div class="alert alert-danger alert-dismissible" role="alert">
		{{ session('error') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
@endif

<!-- Mentoring Plans -->
@if($mentoringProducts->isNotEmpty())
	<div class="mb-5">
		<h3 class="mb-4">Planes de Mentoría</h3>
		<div class="row gy-4">
			@foreach($mentoringProducts as $product)
				<div class="col-xl col-lg-4 col-md-6">
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
									<form method="GET" action="{{ route('subscription.checkout') }}" class="mt-auto w-100">
										<input type="hidden" name="product_id" value="{{ $product->id }}">
										<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
									</form>
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
	<h3 class="mb-4">Planes de Mailer</h3>
	<div class="row gy-4">
	<!-- FREE Plan -->
	<div class="col-xl col-lg-4 col-md-6">
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
	<div class="col-xl col-lg-4 col-md-6">
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
					<form method="GET" action="{{ route('subscription.billing-info') }}" class="mt-auto w-100">
						<input type="hidden" name="plan" value="basic">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('basic', 'Basic')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::BASIC->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- FOUNDATION Plan -->
	<div class="col-xl col-lg-4 col-md-6">
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
					<form method="GET" action="{{ route('subscription.billing-info') }}" class="mt-auto w-100">
						<input type="hidden" name="plan" value="foundation">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('foundation', 'Foundation')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::FOUNDATION->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>

	<!-- SCALE Plan -->
	<div class="col-xl col-lg-4 col-md-6">
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
					<form method="GET" action="{{ route('subscription.billing-info') }}" class="mt-auto w-100">
						<input type="hidden" name="plan" value="scale">
						<button type="submit" class="btn btn-primary w-100">Suscribirse Ahora</button>
					</form>
				@else
					<button type="button" class="btn btn-primary w-100 mt-auto" onclick="confirmSwap('scale', 'Scale')">
						{{ $currentPlan->getMonthlyLimit() < \App\Enums\EmailPlan::SCALE->getMonthlyLimit() ? 'Upgrade' : 'Downgrade' }}
					</button>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- Hosting Plans -->
@if($hostingProducts->isNotEmpty())
	<div class="mb-5 mt-5">
		<h3 class="mb-4">Planes de Hosting</h3>
		<div class="row gy-4">
			@foreach($hostingProducts as $product)
				<div class="col-xl col-lg-4 col-md-6">
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
									<button type="button" class="btn btn-primary w-100" onclick="showDomainModal({{ $product->id }}, '{{ $product->name }}', {{ $isSupport ? 'true' : 'false' }})">
										Contratar
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

<!-- Modal Solicitar Dominio -->
<div class="modal fade" id="domainModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="domainModalTitle">Información del Dominio</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="domainForm" method="GET" action="{{ route('subscription.checkout') }}">
					<input type="hidden" name="product_id" id="domainProductId">
					<input type="hidden" name="domain" id="domainInput">
					
					<div class="mb-3">
						<label for="domain" class="form-label" id="domainLabel">Dominio (*)</label>
						<input type="text" class="form-control" id="domain" name="domain" placeholder="ejemplo.com" required>
						<small class="text-muted" id="domainHelp">
							Ingresa el dominio para el servicio de hosting
						</small>
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

function showDomainModal(productId, productName, isSupport)
{
    // Update modal content
    document.getElementById('domainModalTitle').textContent = productName;
    document.getElementById('domainProductId').value = productId;
    document.getElementById('domain').value = '';
    
    if (isSupport) {
        document.getElementById('domainLabel').textContent = 'Dominio existente (*)';
        document.getElementById('domainHelp').textContent = 'Ingresa el dominio al que se asociará el soporte (debe ser un dominio ya existente)';
        document.getElementById('domainAlertText').textContent = 'Este soporte se asociará a un dominio existente. Asegúrate de que el dominio ya esté configurado.';
    } else {
        document.getElementById('domainLabel').textContent = 'Dominio (*)';
        document.getElementById('domainHelp').textContent = 'Ingresa el dominio para el servicio de hosting (puedes tener varios dominios)';
        document.getElementById('domainAlertText').textContent = 'Este dominio se utilizará para configurar tu servicio de hosting WordPress.';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('domainModal'));
    modal.show();
}

function submitDomainForm()
{
    const domainInput = document.getElementById('domain');
    let domain = domainInput.value.trim();
    
    if (!domain) {
        alert('Por favor, ingresa un dominio válido');
        return;
    }
    
    // Remove protocol if present
    domain = domain.replace(/^https?:\/\//, '');
    // Remove trailing slash
    domain = domain.replace(/\/$/, '');
    // Remove www. if present
    domain = domain.replace(/^www\./, '');
    
    // Enhanced domain validation (matches backend validation)
    const domainRegex = /^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;
    if (!domainRegex.test(domain)) {
        alert('Por favor, ingresa un dominio válido (ej: ejemplo.com)');
        return;
    }
    
    // Set cleaned domain in input
    domainInput.value = domain;
    document.getElementById('domainInput').value = domain;
    
    // Submit form
    document.getElementById('domainForm').submit();
}
</script>
@endsection
