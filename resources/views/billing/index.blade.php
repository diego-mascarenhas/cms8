@extends('layouts/layoutMaster')

@section('title', 'Facturación y Planes')

@section('vendor-style')
	<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endsection

@section('vendor-script')
	<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-script')
	@if (auth()->user()->hasRole(['root', 'admin']) && is_array($tokenStats ?? null) && ! empty($tokenStats['byModule']))
		<script>
			(function() {
				const moduleData = @json($tokenStats['byModule']);
				const labels = [];
				const series = [];
				const colors = ['#696cff', '#8592a3', '#71dd37', '#ffab00', '#ff3e1d', '#03c3ec'];

				Object.values(moduleData).forEach(module => {
					if (module.tokens_used > 0) {
						labels.push(module.module_name);
						series.push(module.tokens_used);
					}
				});

				if (series.length > 0) {
					const chartEl = document.querySelector('#tokensByModuleChart');
					const chartHeight = chartEl && chartEl.dataset.chartHeight
						? parseInt(chartEl.dataset.chartHeight, 10)
						: 160;
					const chart = new ApexCharts(chartEl, {
						chart: {
							type: 'donut',
							height: chartHeight,
							fontFamily: 'Public Sans'
						},
						series: series,
						labels: labels,
						colors: colors,
						stroke: {
							width: 0
						},
						dataLabels: {
							enabled: false
						},
						legend: {
							show: true,
							position: 'bottom',
							horizontalAlign: 'center',
							fontSize: '11px',
							fontFamily: 'Public Sans',
							markers: {
								width: 10,
								height: 10,
								offsetX: -3
							},
							itemMargin: {
								horizontal: 8,
								vertical: 5
							},
							offsetY: 0
						},
						plotOptions: {
							pie: {
								donut: {
									size: '70%',
									labels: {
										show: true,
										name: {
											show: false
										},
										value: {
											show: true,
											fontSize: '18px',
											fontWeight: 600,
											color: '#566a7f',
											offsetY: 4,
											formatter: val => parseInt(val).toLocaleString()
										},
										total: {
											show: true,
											showAlways: true,
											fontSize: '11px',
											fontWeight: 400,
											color: '#a1acb8',
											label: 'Total tokens',
											formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString()
										}
									}
								}
							}
						},
						tooltip: {
							y: {
								formatter: val => val.toLocaleString() + ' tokens'
							}
						},
						responsive: [{
							breakpoint: 480,
							options: {
								chart: {
									height: Math.max(130, chartHeight - 20)
								},
								legend: {
									fontSize: '10px'
								}
							}
						}]
					});
					chart.render();
				}
			})();
		</script>
	@endif
	<script>
		function confirmCancel(stripeId)
		{
			document.getElementById('cancelStripeId').value = stripeId || '';
			const modal = new bootstrap.Modal(document.getElementById('cancelSubscriptionModal'));
			modal.show();
		}

		@if ($errors->hasAny(['individual_name', 'business_name', 'country', 'phone', 'tax_id']))
		document.addEventListener('DOMContentLoaded', function() {
			var myModal = new bootstrap.Modal(document.getElementById('editBillingModal'));
			myModal.show();
		});
		@elseif ($errors->hasAny(['invite_name', 'invite_email', 'invite_plan']))
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById('affiliate-invite-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
		@endif
	</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">Facturación y Planes</h4>
		<p class="text-muted">Gestiona tu suscripción, métodos de pago y facturas</p>
	</div>
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

<!-- Billing Data & Payment Methods -->
<div class="row">
	<div class="col-12 col-lg-8 mb-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Datos de Facturación</h5>
				<button type="button" class="btn btn-sm btn-label-primary" data-bs-toggle="modal" data-bs-target="#editBillingModal">
					<i class="ti ti-edit ti-xs me-1"></i>
					Editar
				</button>
			</div>
			<div class="card-body">
				@if ($stripeData && isset($stripeData['customer']))
					<div class="row">
						<div class="col-md-6">
							<dl class="row mb-0">
								<dt class="col-sm-5 mb-2 fw-medium text-nowrap">Nombre Completo:</dt>
								<dd class="col-sm-7">{{ $stripeData['customer']->metadata->individual_name ?? $stripeData['customer']->collected_information->individual_name ?? 'No especificado' }}</dd>

								<dt class="col-sm-5 mb-2 fw-medium text-nowrap">Razón Social:</dt>
								<dd class="col-sm-7">{{ $stripeData['customer']->metadata->business_name ?? $stripeData['customer']->metadata->company_name ?? $stripeData['customer']->collected_information->business_name ?? 'No especificado' }}</dd>

								@if(isset($stripeData['customer']->address->country))
									@php
										$countryCode = $stripeData['customer']->address->country;
										$countryName = \App\Models\Country::query()
											->where('code', strtolower($countryCode))
											->value('name') ?? $countryCode;
									@endphp
									<dt class="col-sm-5 mb-2 fw-medium text-nowrap">País:</dt>
									<dd class="col-sm-7">{{ $countryName }}</dd>
								@endif
							</dl>
						</div>
						<div class="col-md-6">
							<dl class="row mb-0">
								@if(isset($stripeData['customer']->phone) && $stripeData['customer']->phone)
									<dt class="col-sm-5 mb-2 fw-medium text-nowrap">WhatsApp:</dt>
									<dd class="col-sm-7">{{ $stripeData['customer']->phone }}</dd>
					@endif

								@php
									$taxIdValue = null;
									$taxIdType = 'ID Fiscal';

									// Intentar obtener tax ID
									if (isset($stripeData['customer']->tax_ids)) {
										if (is_object($stripeData['customer']->tax_ids) && isset($stripeData['customer']->tax_ids->data) && count($stripeData['customer']->tax_ids->data) > 0) {
											$firstTaxId = $stripeData['customer']->tax_ids->data[0];
											$taxIdValue = $firstTaxId->value;
											$taxIdType = strtoupper(str_replace('_', ' ', $firstTaxId->type));
										}
									}
				@endphp

								<dt class="col-sm-5 mb-2 fw-medium text-nowrap">{{ $taxIdType }}:</dt>
								<dd class="col-sm-7{{ !$taxIdValue ? ' text-muted' : '' }}">{{ $taxIdValue ?: 'No especificado' }}</dd>
							</dl>
						</div>
					</div>
				@else
					<div class="text-center py-4">
						<i class="ti ti-file-invoice ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
						<h6 class="text-muted mb-2">No hay dirección de facturación configurada</h6>
						<p class="text-muted mb-0 small">Añade tu información de facturación para gestionar tus pagos</p>
					</div>
				@endif
			</div>
		</div>
	</div>

	<!-- Payment Methods -->
	<div class="col-12 col-lg-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0">Métodos de Pago</h5>
			</div>
			<div class="card-body">
				@if($paymentMethods->isNotEmpty())
					<div class="row g-3">
						@foreach($paymentMethods as $method)
						<div class="col-12">
							<div class="card border shadow-none">
								<div class="card-body">
									<div class="d-flex align-items-start">
										<div class="badge badge-center rounded bg-label-primary me-3 p-2">
											<i class="ti ti-credit-card ti-sm"></i>
										</div>
										<div class="flex-grow-1">
											<div class="d-flex justify-content-between mb-1">
												<h6 class="mb-0 text-capitalize">{{ $method->card->brand }}</h6>
												@if($method->id === $stripeData['customer']->invoice_settings->default_payment_method)
													<span class="badge bg-label-success">Principal</span>
												@endif
											</div>
											<p class="mb-0">**** **** **** {{ $method->card->last4 }}</p>
											<small class="text-muted">Vence {{ $method->card->exp_month }}/{{ $method->card->exp_year }}</small>
										</div>
									</div>
								</div>
							</div>
						</div>
						@endforeach
					</div>
				@else
					<div class="text-center py-4">
						<i class="ti ti-credit-card-off ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
						<h6 class="text-muted mb-2">No hay métodos de pago</h6>
						<p class="text-muted mb-0 small">Añade un método de pago para gestionar tus suscripciones</p>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- All Subscriptions + API usage (sidebar on large screens) -->
<div class="row align-items-lg-start mb-4">
	<div class="col-12 col-lg-8 mb-4 mb-lg-0">
		<div class="card mb-0">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Todas las Suscripciones</h5>
				<a href="{{ route('subscription.index') }}" class="btn btn-primary">
					<i class="ti ti-eye ti-xs me-1"></i>
					{{ __('Ver Planes Disponibles') }}
				</a>
			</div>
			<div class="card-body">
@if($teamSubscriptions->isNotEmpty())
				<div class="row g-3">
					@foreach($teamSubscriptions as $type => $subscriptionGroup)
						@foreach($subscriptionGroup as $sub)
								@php
									// Get corresponding Stripe subscription for more details
									$stripeSub = $subscriptions->firstWhere('id', $sub->stripe_id);

									// Determine status badge
									$statusBadge = match($sub->stripe_status) {
										'active' => $sub->onGracePeriod() ? 'bg-label-warning' : 'bg-label-success',
										'canceled' => 'bg-label-danger',
										'trialing' => 'bg-label-info',
										'past_due' => 'bg-label-warning',
										'incomplete' => 'bg-label-secondary',
										'incomplete_expired' => 'bg-label-danger',
										'unpaid' => 'bg-label-danger',
										default => 'bg-label-secondary',
									};

									$statusText = match($sub->stripe_status) {
										'active' => $sub->onGracePeriod() ? 'Cancela el ' . $sub->ends_at->format('d/m/Y') : 'Activa',
										'canceled' => 'Cancelada',
										'trialing' => 'En prueba',
										'past_due' => 'Pago atrasado',
										'incomplete' => 'Incompleta',
										'incomplete_expired' => 'Expirada',
										'unpaid' => 'Impagada',
										default => ucfirst($sub->stripe_status),
									};

									// Get product name from SubscriptionProduct
									$productName = null;
									$product = \App\Models\SubscriptionProduct::where('stripe_price', $sub->stripe_price)->first();
									if ($product) {
										$productName = $product->name;
									}

									// Get EmailPlan info for mailer subscriptions (fallback)
									$planInfo = null;
									if ($type === 'mailer' && $stripeSub && !$productName) {
										try {
											$planInfo = \App\Enums\EmailPlan::fromStripePriceId($sub->stripe_price);
										} catch (\Exception $e) {
											// Ignore
										}
									}

									// Get type icon and name (mailer: lifebuoy like navbar help; label is user-facing, not internal "Mailer")
									$typeIcons = [
										'mailer' => 'ti-lifebuoy',
										'hosting' => 'ti-server',
										'domain' => 'ti-world',
										'licence' => 'ti-license',
										'default' => 'ti-package',
									];
									$typeIcon = $typeIcons[$type] ?? $typeIcons['default'];

									$typeNames = [
										'mailer' => __('billing.subscription_type_mailer'),
										'hosting' => 'Hosting',
										'domain' => 'Dominio',
										'licence' => 'Licencia',
										'default' => 'General',
									];
									$typeName = $typeNames[$type] ?? ucfirst($type);
								@endphp

								<div class="col-lg-6">
									<div class="card shadow-none border h-100">
										<div class="card-body">
											<div class="d-flex justify-content-between align-items-start mb-3">
												<div class="flex-grow-1">
													<div class="d-flex align-items-center gap-2 mb-2">
														<div class="badge badge-center rounded bg-label-primary p-2">
															<i class="ti {{ $typeIcon }} ti-sm"></i>
														</div>
														<h5 class="mb-0">
															@if($productName)
																{{ $productName }}
															@elseif($planInfo)
																{{ $planInfo->getDisplayName() }}
															@else
																{{ $typeName }}
															@endif
														</h5>
													</div>
													@if($product && $product->description)
														<p class="text-muted mb-0 small">{{ $product->description }}</p>
													@elseif($planInfo)
														<p class="text-muted mb-0 small">{{ $planInfo->getDescription() }}</p>
													@else
														<small class="text-muted d-block mb-0">{{ $typeName }}</small>
													@endif
													@php
														// Get domain from subscription metadata if it's hosting or support
														$domain = null;
														if (in_array($type, ['hosting', 'support']))
														{
															// Try to get domain from local subscription data
															if ($sub->data && is_array($sub->data) && isset($sub->data['domain']))
															{
																$domain = $sub->data['domain'];
															}
															// Fallback: try to get from Stripe subscription metadata
															elseif ($stripeSub && isset($stripeSub->metadata->domain))
															{
																$domain = $stripeSub->metadata->domain;
															}
														}
													@endphp
													@if($domain)
														<div class="mt-2">
															<small class="text-muted d-flex align-items-center">
																<i class="ti ti-world ti-xs me-1"></i>
																<strong>Dominio:</strong> <span class="ms-1">{{ $domain }}</span>
															</small>
														</div>
													@endif
												</div>
												@if($stripeSub)
													<div class="text-end">
														<div class="d-flex align-items-baseline justify-content-end">
															<span class="h4 mb-0 text-primary me-2">{{ number_format($stripeSub->plan->amount / 100, 2) }}</span>
															<span class="text-muted">{{ strtoupper($stripeSub->plan->currency) }}</span>
														</div>
														<small class="text-muted">/ {{ $stripeSub->plan->interval === 'month' ? 'mes' : 'año' }}</small>
													</div>
								@endif
											</div>

											{{-- Subscription Period Progress (for active subscriptions) --}}
											@if($stripeSub && $sub->active() && !$sub->onGracePeriod())
												@php
													$start = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_start);
													$end = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end);
													$now = \Carbon\Carbon::now();
													$totalDays = max(1, $start->diffInDays($end));
													$usedDaysRaw = $start->diffInDays($now, false);
													$usedDays = max(0, min($totalDays, $usedDaysRaw));
													$remainingDays = max(0, -1 * $end->diffInDays($now, false));
													$progressPercentage = max(0, min(100, ($usedDays / $totalDays) * 100));
												@endphp

												<div class="card shadow-none bg-lighter mb-3">
													<div class="card-body p-3">
														<div class="d-flex justify-content-between align-items-center mb-2">
															<small class="text-muted">Período actual</small>
															<small class="text-muted">{{ (int) $remainingDays }} días restantes</small>
														</div>
														<div class="progress mb-2" style="height: 6px;">
															<div class="progress-bar" role="progressbar"
																style="width: {{ round($progressPercentage) }}%"
																aria-valuenow="{{ $progressPercentage }}"
																aria-valuemin="0"
																aria-valuemax="100">
															</div>
														</div>
														<div class="d-flex justify-content-between align-items-center">
															<small>{{ $start->format('d M, Y') }}</small>
															<small>{{ $end->format('d M, Y') }}</small>
														</div>
													</div>
						</div>
								@endif

											{{-- Grace Period Warning --}}
											@if($sub->active() && $sub->onGracePeriod())
												<div class="alert alert-warning mb-3 py-2">
													<small class="d-flex align-items-center mb-0">
														<i class="ti ti-alert-triangle ti-xs me-2"></i>
														Se cancelará el {{ $sub->ends_at->format('d/m/Y') }}
													</small>
												</div>
										@endif

											{{-- Actions --}}
											<div class="d-flex flex-wrap gap-2 mt-auto">
												@if($type === 'mailer')
													<a href="{{ route('subscription.index') }}" class="btn btn-sm btn-primary flex-grow-1">
														<i class="ti ti-refresh ti-xs me-1"></i>
														Cambiar Plan
													</a>
										@endif

												@if($sub->active())
													@if($sub->onGracePeriod())
														{{-- On grace period - show resume --}}
														<form method="POST" action="{{ route('subscription.resume') }}" class="flex-grow-1">
															@csrf
															<button type="submit" class="btn btn-sm btn-success w-100">
																<i class="ti ti-player-play ti-xs me-1"></i>
																Reanudar
															</button>
														</form>
													@else
														{{-- Active - show cancel --}}
														<button type="button" class="btn btn-sm btn-label-danger flex-grow-1" onclick="confirmCancel('{{ $sub->stripe_id }}')">
															<i class="ti ti-x ti-xs me-1"></i>
															Cancelar
														</button>
										@endif
										@endif
											</div>
										</div>
						</div>
								</div>
						@endforeach
					@endforeach
					</div>
				@else
					@php
						$hasAssignedPlans = ($currentPlan->value ?? 'free') !== 'free' || ($remainingProspectCredits ?? 0) > 0;
					@endphp
					@if($hasAssignedPlans)
						<div class="mb-4">
							<h6 class="text-body mb-3">{{ __('Planes y créditos actuales') }}</h6>
							<p class="text-muted small mb-3">{{ __('Asignados a tu equipo (no provienen de una suscripción Stripe activa).') }}</p>
							<div class="row g-3">
								<div class="col-md-6">
									<div class="border rounded p-3">
										<div class="d-flex justify-content-between align-items-start">
											<div>
												<span class="fw-medium">{{ __('billing.subscription_type_mailer') }}</span>
												<span class="badge bg-label-primary ms-2">{{ $currentPlan->getDisplayName() }}</span>
											</div>
										</div>
										<p class="small text-muted mb-0 mt-2">
											{{ $planConfig['monthly_limit'] - $planConfig['monthly_used'] }} {{ __('emails restantes este mes') }}
											@if($planConfig['daily_limit'])
												· {{ $planConfig['daily_limit'] - $planConfig['daily_used'] }} {{ __('hoy') }}
											@endif
										</p>
									</div>
								</div>
								<div class="col-md-6">
									<div class="border rounded p-3">
										<div class="d-flex justify-content-between align-items-start">
											<div>
												<span class="fw-medium">{{ __('Prospectos') }}</span>
												<span class="badge bg-label-info ms-2">{{ $currentProspectPlan->getDisplayName() }}</span>
											</div>
										</div>
										<p class="small text-muted mb-0 mt-2">
											{{ $remainingProspectCredits }} {{ __('créditos disponibles') }}
										</p>
									</div>
								</div>
							</div>
						</div>
					@else
				<div class="text-center py-5">
					<i class="ti ti-package-off ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
					<h5 class="text-muted mb-2">{{ __('No tienes suscripciones activas') }}</h5>
					<p class="text-muted mb-4">{{ __('Explora nuestros planes para comenzar a utilizar los servicios') }}</p>
					<a href="{{ route('subscription.index') }}" class="btn btn-primary">
						<i class="ti ti-eye ti-xs me-1"></i>
						{{ __('Ver Planes Disponibles') }}
					</a>
				</div>
					@endif
				@endif
			</div>
		</div>
	</div>
	@if ($tokenStats !== null)
		<div class="col-12 col-lg-4 mt-4 mt-lg-0">
			@include('partials.team-api-usage-widget', ['tokenStats' => $tokenStats])
		</div>
	@endif
</div>

<!-- Billing History -->
<div class="row mb-4">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<h5 class="card-title mb-0">Historial de Facturación</h5>
			</div>
			<div class="card-body">
				@if($invoices->isNotEmpty())
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Número</th>
								<th>Fecha</th>
								<th class="text-end">Monto</th>
								<th class="text-center">Estado</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach($invoices as $invoice)
							<tr>
								<td>
									<span class="fw-medium">{{ $invoice->number ?? 'N/A' }}</span>
								</td>
								<td>{{ \Carbon\Carbon::createFromTimestamp($invoice->created)->format('d/m/Y') }}</td>
								<td class="text-end">
									<span class="fw-medium">{{ number_format($invoice->amount_due / 100, 2) }} €</span>
								</td>
								<td class="text-center">
									<span class="badge bg-label-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'open' ? 'warning' : 'secondary') }}">
										{{ $invoice->status === 'paid' ? 'Pagado' : ($invoice->status === 'open' ? 'Pendiente' : ucfirst($invoice->status)) }}
									</span>
								</td>
								<td class="text-end">
									<div class="d-flex justify-content-end gap-2">
										@if($invoice->invoice_pdf)
											<a href="{{ $invoice->invoice_pdf }}" class="btn btn-sm btn-icon btn-label-secondary" target="_blank" title="Descargar">
												<i class="ti ti-download"></i>
											</a>
										@endif
										@if($invoice->hosted_invoice_url)
											<a href="{{ $invoice->hosted_invoice_url }}" class="btn btn-sm btn-icon btn-label-info" target="_blank" title="Ver Online">
												<i class="ti ti-eye"></i>
											</a>
										@endif
										@if($invoice->status === 'open' && $invoice->hosted_invoice_url)
											<a href="{{ $invoice->hosted_invoice_url }}" class="btn btn-sm btn-icon btn-label-primary" target="_blank" title="Pagar">
												<i class="ti ti-credit-card"></i>
											</a>
										@endif
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@else
				<div class="text-center py-5">
					<i class="ti ti-file-invoice ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
					<h6 class="text-muted">No hay facturas disponibles</h6>
					<p class="text-muted mb-0">Tus facturas aparecerán aquí una vez que realices tu primera compra</p>
				</div>
				@endif
			</div>
		</div>
	</div>
</div>

@if($team->hasModule('affiliates'))
<div class="row">
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
				<div>
					<h5 class="card-title mb-0"><i class="ti ti-affiliate me-2"></i>Afiliados</h5>
					<p class="text-muted small mb-0 mt-1">
						Comparte tu enlace de referido. Cuando otro equipo se suscribe a Humano con tu código, recibes un
						<strong>{{ number_format($affiliateCommissionPercent, 2) }}%</strong> de cada cobro (configuración de plataforma).
					</p>
				</div>
			</div>
			<div class="card-body">
				@if(!$affiliateReferralCode)
					<div class="alert alert-warning mb-4" role="alert">
						<div class="d-flex">
							<div class="flex-shrink-0 me-3">
								<i class="ti ti-alert-triangle ti-md"></i>
							</div>
							<div class="flex-grow-1">
								<h6 class="alert-heading mb-2">Activa tu código de referido</h6>
								<p class="mb-3 mb-md-0">
									Para compartir enlaces e invitar por email, tu equipo debe estar registrado en Stripe.
									Al activarlo, creamos tu cliente de facturación y guardamos el identificador en tu equipo.
								</p>
								<form method="POST" action="{{ route('billing.affiliate-setup-stripe') }}" class="mt-3">
									@csrf
									<button type="submit" class="btn btn-warning">
										<i class="ti ti-brand-stripe me-1"></i>Activar en Stripe
									</button>
								</form>
							</div>
						</div>
					</div>
				@else
					@if(count($affiliateReferralPlans) > 0)
						<h6 class="mb-3">Enlaces por plan</h6>
						<div class="table-responsive mb-4">
							<table class="table table-sm">
								<thead>
									<tr>
										<th>Plan</th>
										<th>Enlace de referido</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									@foreach($affiliateReferralPlans as $plan)
										<tr>
											<td class="align-middle">{{ $plan['name'] }}</td>
											<td class="align-middle">
												@if($plan['referral_url'])
													<input type="text" class="form-control form-control-sm affiliate-plan-link" readonly value="{{ $plan['referral_url'] }}">
												@else
													<span class="text-muted small">—</span>
												@endif
											</td>
											<td class="align-middle text-end">
												@if($plan['referral_url'])
													<button type="button" class="btn btn-sm btn-label-secondary" onclick="copyAffiliatePlanLink(this)">
														<i class="ti ti-copy"></i>
													</button>
												@endif
											</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif

					@if(count($affiliateReferralPlans) > 0)
						<h6 class="mb-3">Invitar por email</h6>
						<form id="affiliate-invite-form" action="{{ route('billing.affiliate-invite') }}" method="POST" class="row g-3 mb-4" novalidate>
							@csrf
							<div class="col-md-4">
								<x-input-general id="invite_name" label="Nombre (*)" value="{{ old('invite_name') }}" />
							</div>
							<div class="col-md-4">
								<x-input-general id="invite_email" label="Email (*)" type="email" value="{{ old('invite_email') }}" />
							</div>
							<div class="col-md-4">
								@php
									$invitePlanOptions = collect($affiliateReferralPlans)->pluck('name', 'id')->all();
								@endphp
								<x-input-select
									id="invite_plan"
									label="Plan (*)"
									:options="$invitePlanOptions"
									value="{{ old('invite_plan') }}"
									placeholder="Seleccionar…"
									:allow-clear="false"
								/>
							</div>
							<div class="col-12">
								<button type="submit" class="btn btn-primary">
									<i class="ti ti-mail me-1"></i>Enviar invitación
								</button>
							</div>
						</form>
					@endif
				@endif

				<h6 class="mb-3">Invitaciones enviadas</h6>
				<div class="table-responsive mb-4">
					<table class="table table-sm table-hover">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Nombre</th>
								<th>Email</th>
								<th>Plan</th>
								<th>Enviado por</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody>
							@forelse($affiliateInvitations as $invitation)
								<tr>
									<td>{{ ($invitation->sent_at ?? $invitation->created_at)->format('d/m/Y H:i') }}</td>
									<td>{{ $invitation->invitee_name }}</td>
									<td>{{ $invitation->invitee_email }}</td>
									<td>{{ $invitation->plan_name }}</td>
									<td>{{ $invitation->invitedBy?->name ?? '—' }}</td>
									<td>
										<span
											class="badge {{ $invitation->statusBadgeClass() }} rounded-pill"
											@if($invitation->statusAt())
												title="{{ $invitation->statusAt()->format('d/m/Y H:i') }}"
											@endif
										>{{ $invitation->statusLabel() }}</span>
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="6" class="text-center text-muted py-4">Aún no has enviado invitaciones.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>

				<h6 class="mb-3">Como referidor</h6>
				@if($affiliateTotalsAsReferrer !== [])
					<div class="d-flex flex-wrap gap-3 mb-3">
						@foreach($affiliateTotalsAsReferrer as $cur => $tot)
							<span class="badge bg-label-success rounded-pill">
								{{ $cur }}: comisión {{ number_format($tot['commission_cents'] / 100, 2) }} · base pagada {{ number_format($tot['paid_cents'] / 100, 2) }}
							</span>
						@endforeach
					</div>
				@endif
				<div class="table-responsive mb-4">
					<table class="table table-sm table-hover">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Equipo que pagó</th>
								<th>Ref. cobro</th>
								<th class="text-end">Pagó</th>
								<th class="text-end">%</th>
								<th class="text-end">Tu comisión</th>
								<th>Moneda</th>
							</tr>
						</thead>
						<tbody>
							@forelse($affiliateCommissionsAsReferrer as $row)
								<tr>
									<td>{{ $row->created_at->format('d/m/Y H:i') }}</td>
									<td>{{ $row->payingTeam?->name ?? '—' }}</td>
									<td><code class="small">{{ $row->stripe_invoice_id }}</code></td>
									<td class="text-end">{{ number_format($row->amount_paid_cents / 100, 2) }}</td>
									<td class="text-end">{{ number_format((float) $row->commission_percent, 2) }}</td>
									<td class="text-end fw-medium">{{ number_format($row->commission_amount_cents / 100, 2) }}</td>
									<td>{{ strtoupper($row->currency) }}</td>
								</tr>
							@empty
								<tr>
									<td colspan="7" class="text-center text-muted py-4">Sin movimientos como referidor.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>

				<h6 class="mb-3">Tus pagos con comisión al referidor</h6>
				@if($affiliateTotalsAsPayer !== [])
					<div class="d-flex flex-wrap gap-3 mb-3">
						@foreach($affiliateTotalsAsPayer as $cur => $tot)
							<span class="badge bg-label-info rounded-pill">
								{{ $cur }}: pagaste {{ number_format($tot['paid_cents'] / 100, 2) }} · comisión referidor {{ number_format($tot['commission_cents'] / 100, 2) }}
							</span>
						@endforeach
					</div>
				@endif
				<div class="table-responsive">
					<table class="table table-sm table-hover">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Equipo referidor</th>
								<th>Ref. cobro</th>
								<th class="text-end">Tu pago</th>
								<th class="text-end">%</th>
								<th class="text-end">Comisión referidor</th>
								<th>Moneda</th>
							</tr>
						</thead>
						<tbody>
							@forelse($affiliateCommissionsAsPayer as $row)
								<tr>
									<td>{{ $row->created_at->format('d/m/Y H:i') }}</td>
									<td>{{ $row->referrerTeam?->name ?? '—' }}</td>
									<td><code class="small">{{ $row->stripe_invoice_id }}</code></td>
									<td class="text-end">{{ number_format($row->amount_paid_cents / 100, 2) }}</td>
									<td class="text-end">{{ number_format((float) $row->commission_percent, 2) }}</td>
									<td class="text-end">{{ number_format($row->commission_amount_cents / 100, 2) }}</td>
									<td>{{ strtoupper($row->currency) }}</td>
								</tr>
							@empty
								<tr>
									<td colspan="7" class="text-center text-muted py-4">Sin registros de comisión sobre tus pagos.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
function copyAffiliatePlanLink(btn) {
	const input = btn.closest('tr')?.querySelector('.affiliate-plan-link');
	if (!input) return;
	navigator.clipboard.writeText(input.value);
}
</script>
@endif

<!-- Modal: Edit Billing Data -->
<div class="modal fade" id="editBillingModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Editar Datos de Facturación</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="POST" action="{{ route('billing.update') }}">
				@csrf
				<div class="modal-body">
					<div class="row g-3">
						<!-- Individual Name -->
						<div class="col-md-6">
							<label class="form-label" for="individual_name">Nombre Completo (*)</label>
							@php
								$individualName = old('individual_name');
								if (!$individualName && isset($stripeData['customer'])) {
									$individualName = $stripeData['customer']->metadata->individual_name ??
													  $stripeData['customer']->collected_information->individual_name ??
													  '';
								}
							@endphp
							<input type="text"
								class="form-control @error('individual_name') is-invalid @enderror"
								id="individual_name"
								name="individual_name"
								value="{{ $individualName }}"
								placeholder="Juan Pérez">
							@error('individual_name')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
						</div>

						<!-- Business Name -->
						<div class="col-md-6">
							<label class="form-label" for="business_name">Razón Social</label>
							@php
								$businessName = old('business_name');
								if (!$businessName && isset($stripeData['customer'])) {
									$businessName = $stripeData['customer']->metadata->business_name ??
													$stripeData['customer']->metadata->company_name ??
													$stripeData['customer']->collected_information->business_name ??
													'';
								}
							@endphp
							<input type="text"
								class="form-control @error('business_name') is-invalid @enderror"
								id="business_name"
								name="business_name"
								value="{{ $businessName }}"
								placeholder="Mi Empresa S.A.">
							@error('business_name')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
							<small class="text-muted">Opcional - Si no se completa, se usará el Nombre Completo</small>
						</div>

						<!-- Country -->
						<div class="col-md-6">
							<x-country-select
								name="country"
								id="billing_country"
								label="País (*)"
								value-key="code"
								:value="old('country', $stripeData['customer']->address->country ?? '')"
							/>
						</div>

						<!-- Phone -->
						<div class="col-md-6">
							<label class="form-label" for="phone">WhatsApp (*)</label>
							<input type="text"
								class="form-control @error('phone') is-invalid @enderror"
								id="phone"
								name="phone"
								value="{{ old('phone', $stripeData['customer']->phone ?? '') }}"
								placeholder="+54 9 11 0000-0000">
							@error('phone')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
							<small class="text-muted">Ingrese con código de país: +54 para Argentina, +34 para España, +52 para México</small>
						</div>

						<!-- Tax ID -->
						<div class="col-md-12">
							<label class="form-label" for="tax_id">Identificación Fiscal (*)</label>
							@php
								$taxIdValue = '';
								if (isset($stripeData['customer']->tax_ids)) {
									if (is_object($stripeData['customer']->tax_ids) && isset($stripeData['customer']->tax_ids->data[0])) {
										$taxIdValue = $stripeData['customer']->tax_ids->data[0]->value;
									} elseif (is_array($stripeData['customer']->tax_ids) && count($stripeData['customer']->tax_ids) > 0) {
										$taxIdValue = is_object($stripeData['customer']->tax_ids[0]) ? $stripeData['customer']->tax_ids[0]->value : $stripeData['customer']->tax_ids[0]['value'];
									}
								}
							@endphp
							<input type="text"
								class="form-control @error('tax_id') is-invalid @enderror"
								id="tax_id"
								name="tax_id"
								value="{{ old('tax_id', $taxIdValue) }}"
								placeholder="CUIT, CIF, NIF, RFC, etc.">
							@error('tax_id')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
							<small class="text-muted">Ingrese su identificación fiscal según su país. Ejemplos: 20250242000 (Argentina), B12345678 (España), ABCD123456ABC (México)</small>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">
						<i class="ti ti-device-floppy me-1"></i>
						Guardar Cambios
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<!-- /Modal -->

<!-- Modal Cancelar Suscripción -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">¿Cancelar Suscripción?</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p>Tu suscripción permanecerá activa hasta el final del período de facturación actual.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">No, mantener</button>
				<form id="cancelSubscriptionForm" method="POST" action="{{ route('subscription.cancel') }}" style="display: inline;">
					@csrf
					<input type="hidden" name="stripe_id" id="cancelStripeId" value="">
					<button type="submit" class="btn btn-danger">Sí, cancelar</button>
				</form>
			</div>
		</div>
	</div>
</div>

@endsection
