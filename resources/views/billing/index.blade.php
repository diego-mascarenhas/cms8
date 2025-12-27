@extends('layouts/layoutMaster')

@section('title', 'Facturación y Planes')

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

<div class="row">
	<!-- Current Plan -->
	<div class="col-12 col-lg-8 mb-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Plan Actual</h5>
				<div class="badge bg-label-{{ $currentPlan === \App\Enums\EmailPlan::FREE ? 'secondary' : 'primary' }} text-uppercase">
					{{ $currentPlan->getDisplayName() }}
				</div>
			</div>
			<div class="card-body">
				<div class="mb-4">
					<h6 class="mb-2">{{ $currentPlan->getDescription() }}</h6>
					@if($currentPlan !== \App\Enums\EmailPlan::FREE)
						<div class="d-flex align-items-baseline">
							<span class="h2 mb-0 text-primary me-1">{{ $currentPlan === \App\Enums\EmailPlan::BASIC ? '15,99' : ($currentPlan === \App\Enums\EmailPlan::FOUNDATION ? '35,99' : '119,99') }}</span>
							<span class="text-muted">€/mes + IVA</span>
						</div>
					@else
						<div class="d-flex align-items-baseline">
							<span class="h2 mb-0 text-primary me-1">0</span>
							<span class="text-muted">€/mes</span>
						</div>
					@endif
				</div>

				<!-- Plan Features -->
				<div class="row mb-4">
					<div class="col-md-4 mb-3">
						<div class="d-flex align-items-center">
							<div class="badge badge-center rounded bg-label-primary me-3">
								<i class="ti ti-mail ti-sm"></i>
							</div>
							<div>
								<p class="mb-0 fw-medium">Emails Mensuales</p>
								<small class="text-muted">{{ number_format($planConfig['monthly_limit']) }} por mes</small>
							</div>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="d-flex align-items-center">
							<div class="badge badge-center rounded bg-label-info me-3">
								<i class="ti ti-clock ti-sm"></i>
							</div>
							<div>
								<p class="mb-0 fw-medium">Emails Diarios</p>
								<small class="text-muted">{{ $planConfig['daily_limit'] ? number_format($planConfig['daily_limit']) . ' por día' : 'Ilimitados' }}</small>
							</div>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="d-flex align-items-center">
							<div class="badge badge-center rounded bg-label-success me-3">
								<i class="ti ti-users ti-sm"></i>
							</div>
							<div>
								<p class="mb-0 fw-medium">Contactos</p>
								<small class="text-muted">Hasta {{ number_format($planConfig['contact_limit']) }}</small>
							</div>
						</div>
					</div>
				</div>

			<!-- Subscription Status -->
			@if($subscription && $subscription->active())
				@php
					$stripeSubscription = $subscriptions->firstWhere('id', $subscription->stripe_id);
				@endphp
				
				@if($subscription->onGracePeriod())
					<div class="alert alert-warning mb-4">
						<div class="d-flex align-items-center">
							<i class="ti ti-alert-triangle me-2"></i>
							<div>
								<strong>Cancelado</strong> - Tu suscripción finalizará el {{ $subscription->ends_at->format('d/m/Y') }}
							</div>
						</div>
					</div>
				@elseif($stripeSubscription)
					<div class="card shadow-none bg-lighter mb-4">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-start mb-3">
								<div>
									<h6 class="mb-0">Período de Suscripción Actual</h6>
									<small class="text-muted d-block mt-1">
										{{ \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('d M, Y') }}
									</small>
								</div>
								<div class="text-end">
									<span class="badge bg-label-success">Activa</span>
									<small class="text-muted d-block mt-2">
										Próxima facturación: {{ \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('d/m/Y') }}
									</small>
								</div>
							</div>

							@php
								$start = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
								$end = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
								$now = \Carbon\Carbon::now();

								// Total dias del periodo (al menos 1 para evitar división por cero)
								$totalDays = max(1, $start->diffInDays($end));

								// Días transcurridos dentro del periodo [0, totalDays]
								$usedDaysRaw = $start->diffInDays($now, false);
								$usedDays = max(0, min($totalDays, $usedDaysRaw));

								// Días restantes (0 si ya venció)
								$remainingDays = max(0, -1 * $end->diffInDays($now, false));

								// Porcentaje de progreso entre 0 y 100
								$progressPercentage = max(0, min(100, ($usedDays / $totalDays) * 100));
							@endphp

							<div class="d-flex justify-content-between align-items-center mb-1">
								<span>{{ (int) $usedDays }} de {{ (int) $totalDays }} Días</span>
								<span>{{ (int) $remainingDays }} días restantes</span>
							</div>
							<div class="progress mb-1" style="height: 6px;">
								<div class="progress-bar" role="progressbar"
									style="width: {{ round($progressPercentage) }}%"
									aria-valuenow="{{ $progressPercentage }}"
									aria-valuemin="0"
									aria-valuemax="100">
								</div>
							</div>
						</div>
					</div>
				@endif
			@endif

				<!-- Actions -->
				<div class="d-flex flex-wrap gap-3">
					<a href="{{ route('subscription.index') }}" class="btn btn-primary">
						<i class="ti ti-refresh ti-xs me-1"></i>
						{{ $currentPlan === \App\Enums\EmailPlan::FREE ? 'Ver Planes' : 'Cambiar Plan' }}
					</a>
					
					@if($subscription && $subscription->active())
						@if($subscription->onGracePeriod())
							<form method="POST" action="{{ route('subscription.resume') }}" class="d-inline">
								@csrf
								<button type="submit" class="btn btn-success">
									<i class="ti ti-player-play ti-xs me-1"></i>
									Reanudar Suscripción
								</button>
							</form>
						@else
							<button type="button" class="btn btn-label-danger" onclick="confirmCancel()">
								<i class="ti ti-x ti-xs me-1"></i>
								Cancelar Suscripción
							</button>
						@endif
					@endif
				</div>
			</div>
		</div>
	</div>

	<!-- Usage Statistics -->
	<div class="col-12 col-lg-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0">Uso Actual</h5>
			</div>
			<div class="card-body">
				<!-- Monthly Usage -->
				<div class="mb-4">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Emails este Mes</span>
						<span class="fw-medium">{{ number_format($planConfig['monthly_used']) }} / {{ number_format($planConfig['monthly_limit']) }}</span>
					</div>
					<div class="progress" style="height: 10px;">
						<div class="progress-bar {{ $planConfig['monthly_used'] / $planConfig['monthly_limit'] > 0.9 ? 'bg-danger' : ($planConfig['monthly_used'] / $planConfig['monthly_limit'] > 0.7 ? 'bg-warning' : '') }}" 
							role="progressbar" 
							style="width: {{ min(($planConfig['monthly_used'] / $planConfig['monthly_limit']) * 100, 100) }}%">
						</div>
					</div>
				</div>

				<!-- Daily Usage -->
				@if($planConfig['daily_limit'])
				<div class="mb-4">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Emails Hoy</span>
						<span class="fw-medium">{{ number_format($planConfig['daily_used']) }} / {{ number_format($planConfig['daily_limit']) }}</span>
					</div>
					<div class="progress" style="height: 10px;">
						<div class="progress-bar bg-info {{ $planConfig['daily_used'] / $planConfig['daily_limit'] > 0.9 ? 'bg-danger' : '' }}" 
							role="progressbar" 
							style="width: {{ min(($planConfig['daily_used'] / $planConfig['daily_limit']) * 100, 100) }}%">
						</div>
					</div>
				</div>
				@endif

				<!-- Contacts Usage -->
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted">Contactos</span>
						<span class="fw-medium">{{ number_format($team->contacts()->count()) }} / {{ number_format($planConfig['contact_limit']) }}</span>
					</div>
					<div class="progress" style="height: 10px;">
						<div class="progress-bar bg-success {{ $team->contacts()->count() / $planConfig['contact_limit'] > 0.9 ? 'bg-danger' : '' }}" 
							role="progressbar" 
							style="width: {{ min(($team->contacts()->count() / $planConfig['contact_limit']) * 100, 100) }}%">
						</div>
					</div>
				</div>

				@if($planConfig['monthly_used'] / $planConfig['monthly_limit'] > 0.8)
				<div class="alert alert-warning p-2 mt-4">
					<small>
						<i class="ti ti-alert-triangle ti-xs me-1"></i>
						Estás cerca del límite de tu plan
					</small>
				</div>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- Payment Methods -->
@if($paymentMethods->isNotEmpty())
<div class="row">
	<div class="col-12 col-lg-8 mb-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Métodos de Pago</h5>
				@if($stripeData && isset($stripeData['customer']))
					<a href="https://billing.stripe.com/p/login/test_00g14Y8i03dEc2kdQQ" target="_blank" class="btn btn-sm btn-label-primary">
						<i class="ti ti-plus ti-xs me-1"></i>
						Agregar Método
					</a>
				@endif
			</div>
			<div class="card-body">
				<div class="row g-3">
					@foreach($paymentMethods as $method)
					<div class="col-md-6">
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
			</div>
		</div>
	</div>

	<!-- Billing Address -->
	<div class="col-12 col-lg-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0">Dirección de Facturación</h5>
			</div>
			<div class="card-body">
				@if($stripeData && isset($stripeData['customer']->address))
					<p class="mb-2"><strong>{{ $stripeData['customer']->name ?? $team->name }}</strong></p>
					@if($stripeData['customer']->address->line1)
						<p class="mb-1">{{ $stripeData['customer']->address->line1 }}</p>
					@endif
					@if($stripeData['customer']->address->line2)
						<p class="mb-1">{{ $stripeData['customer']->address->line2 }}</p>
					@endif
					@if($stripeData['customer']->address->city || $stripeData['customer']->address->postal_code)
						<p class="mb-1">
							{{ $stripeData['customer']->address->postal_code }} {{ $stripeData['customer']->address->city }}
						</p>
					@endif
					@if($stripeData['customer']->address->country)
						<p class="mb-0">{{ $stripeData['customer']->address->country }}</p>
					@endif
					
					<a href="https://billing.stripe.com/p/login/test_00g14Y8i03dEc2kdQQ" target="_blank" class="btn btn-sm btn-label-secondary mt-3">
						<i class="ti ti-edit ti-xs me-1"></i>
						Editar Dirección
					</a>
				@else
					<p class="text-muted mb-3">No hay dirección de facturación configurada</p>
					<a href="https://billing.stripe.com/p/login/test_00g14Y8i03dEc2kdQQ" target="_blank" class="btn btn-sm btn-primary">
						<i class="ti ti-plus ti-xs me-1"></i>
						Agregar Dirección
					</a>
				@endif
			</div>
		</div>
	</div>
</div>
@endif

<!-- Billing History -->
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Historial de Facturación</h5>
				@if($stripeData && isset($stripeData['customer']))
					<a href="https://billing.stripe.com/p/login/test_00g14Y8i03dEc2kdQQ" target="_blank" class="btn btn-sm btn-label-primary">
						<i class="ti ti-external-link ti-xs me-1"></i>
						Portal de Stripe
					</a>
				@endif
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

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

<script>
function confirmCancel()
{
	Swal.fire({
		title: '¿Cancelar Suscripción?',
		text: "Tu suscripción permanecerá activa hasta el final del período de facturación actual.",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#d33',
		cancelButtonColor: '#3085d6',
		confirmButtonText: 'Sí, cancelar',
		cancelButtonText: 'No, mantener',
		customClass: {
			confirmButton: 'btn btn-danger me-2',
			cancelButton: 'btn btn-label-secondary'
		},
		buttonsStyling: false
	}).then((result) => {
		if (result.isConfirmed)
		{
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '{{ route("subscription.cancel") }}';
			form.innerHTML = '@csrf';
			document.body.appendChild(form);
			form.submit();
		}
	});
}
</script>
@endsection
