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
					<a href="{{ route('subscription.index') }}" class="btn btn-sm btn-primary">
						<i class="ti ti-refresh ti-xs me-1"></i>
						{{ $currentPlan === \App\Enums\EmailPlan::FREE ? 'Ver Planes' : 'Cambiar Plan' }}
					</a>
					
					@if($subscription && $subscription->active())
						@if($subscription->onGracePeriod())
							<form method="POST" action="{{ route('subscription.resume') }}" class="d-inline">
								@csrf
								<button type="submit" class="btn btn-sm btn-success">
									<i class="ti ti-player-play ti-xs me-1"></i>
									Reanudar Suscripción
								</button>
							</form>
						@else
							<button type="button" class="btn btn-sm btn-label-danger" onclick="confirmCancel()">
								<i class="ti ti-x ti-xs me-1"></i>
								Cancelar Suscripción
							</button>
						@endif
					@endif
				</div>
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

<!-- Billing Data -->
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
								<dd class="col-sm-7">{{ $stripeData['customer']['individual_name'] ?? $stripeData['customer']['name'] ?? 'No especificado' }}</dd>

								<dt class="col-sm-5 mb-2 fw-medium text-nowrap">Email:</dt>
								<dd class="col-sm-7">{{ $stripeData['customer']['email'] ?? 'No especificado' }}</dd>

								@if(isset($stripeData['customer']['phone']) && $stripeData['customer']['phone'])
									<dt class="col-sm-5 mb-2 fw-medium text-nowrap">Teléfono:</dt>
									<dd class="col-sm-7">{{ $stripeData['customer']['phone'] }}</dd>
								@endif
							</dl>
						</div>
						<div class="col-md-6">
							<dl class="row mb-0">
								<dt class="col-sm-5 mb-2 fw-medium text-nowrap">Razón Social:</dt>
								<dd class="col-sm-7">{{ $stripeData['customer']['metadata']['company_name'] ?? $stripeData['customer']['name'] ?? 'No especificado' }}</dd>

								@if(isset($stripeData['customer']['tax_ids']) && !empty($stripeData['customer']['tax_ids']))
									@foreach($stripeData['customer']['tax_ids'] as $taxId)
										<dt class="col-sm-5 mb-2 fw-medium text-nowrap">{{ strtoupper(str_replace('_', '_', $taxId['type'])) }}:</dt>
										<dd class="col-sm-7">{{ $taxId['value'] }} <small class="text-muted">({{ $taxId['country'] }})</small></dd>
									@endforeach
								@else
									<dt class="col-sm-5 mb-2 fw-medium text-nowrap">CIF/NIF:</dt>
									<dd class="col-sm-7 text-muted">No especificado</dd>
								@endif

								@if(isset($stripeData['customer']['address']) && isset($stripeData['customer']['address']['country']))
									@php
										$countries = [
											'ES' => 'España',
											'AR' => 'Argentina',
											'MX' => 'México',
											'US' => 'Estados Unidos',
											'CO' => 'Colombia',
											'CL' => 'Chile',
											'PE' => 'Perú',
										];
										$countryCode = $stripeData['customer']['address']['country'];
										$countryName = $countries[$countryCode] ?? $countryCode;
									@endphp
									<dt class="col-sm-5 mb-2 fw-medium text-nowrap">País:</dt>
									<dd class="col-sm-7">{{ $countryName }}</dd>
								@endif
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
</div>

<!-- Billing History -->
<div class="row">
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
						<div class="col-12">
							<label class="form-label" for="individual_name">Nombre Completo *</label>
							<input type="text" id="individual_name" name="individual_name" 
								class="form-control @error('individual_name') is-invalid @enderror" 
								value="{{ old('individual_name', $stripeData['customer']['name'] ?? '') }}" 
								placeholder="Diego Adrián Mascarenhas Goytia" required>
							@error('individual_name')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Company Name -->
						<div class="col-md-6">
							<label class="form-label" for="company_name">Razón Social *</label>
							<input type="text" id="company_name" name="company_name" 
								class="form-control @error('company_name') is-invalid @enderror" 
								value="{{ old('company_name', $stripeData['customer']['name'] ?? $team->name) }}" 
								placeholder="Nombre de la empresa" required>
							@error('company_name')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Tax ID -->
						<div class="col-md-6">
							<label class="form-label" for="tax_id">CIF/NIF *</label>
							<input type="text" id="tax_id" name="tax_id" 
								class="form-control @error('tax_id') is-invalid @enderror" 
								value="{{ old('tax_id', $stripeData['customer']['tax_ids'][0]['value'] ?? '') }}" 
								placeholder="Ej: B12345678" required>
							@error('tax_id')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Email -->
						<div class="col-md-6">
							<label class="form-label" for="billing_email">Email de Facturación *</label>
							<input type="email" id="billing_email" name="billing_email" 
								class="form-control @error('billing_email') is-invalid @enderror" 
								value="{{ old('billing_email', $stripeData['customer']['email'] ?? auth()->user()->email) }}" 
								placeholder="facturacion@empresa.com" required>
							@error('billing_email')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Phone -->
						<div class="col-md-6">
							<label class="form-label" for="billing_phone">Teléfono</label>
							<input type="text" id="billing_phone" name="billing_phone" 
								class="form-control @error('billing_phone') is-invalid @enderror" 
								value="{{ old('billing_phone', $stripeData['customer']['phone'] ?? '') }}" 
								placeholder="+34 600 000 000">
							@error('billing_phone')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Address Line 1 -->
						<div class="col-12">
							<label class="form-label" for="address_line1">Dirección *</label>
							<input type="text" id="address_line1" name="address_line1" 
								class="form-control @error('address_line1') is-invalid @enderror" 
								value="{{ old('address_line1', $stripeData['customer']['address']['line1'] ?? '') }}" 
								placeholder="Calle, número, piso, puerta" required>
							@error('address_line1')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Address Line 2 -->
						<div class="col-12">
							<label class="form-label" for="address_line2">Dirección 2 (opcional)</label>
							<input type="text" id="address_line2" name="address_line2" 
								class="form-control @error('address_line2') is-invalid @enderror" 
								value="{{ old('address_line2', $stripeData['customer']['address']['line2'] ?? '') }}" 
								placeholder="Edificio, escalera, etc.">
							@error('address_line2')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Postal Code -->
						<div class="col-md-4">
							<label class="form-label" for="postal_code">Código Postal *</label>
							<input type="text" id="postal_code" name="postal_code" 
								class="form-control @error('postal_code') is-invalid @enderror" 
								value="{{ old('postal_code', $stripeData['customer']['address']['postal_code'] ?? '') }}" 
								placeholder="28001" required>
							@error('postal_code')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- City -->
						<div class="col-md-4">
							<label class="form-label" for="city">Ciudad *</label>
							<input type="text" id="city" name="city" 
								class="form-control @error('city') is-invalid @enderror" 
								value="{{ old('city', $stripeData['customer']['address']['city'] ?? '') }}" 
								placeholder="Madrid" required>
							@error('city')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- State -->
						<div class="col-md-4">
							<label class="form-label" for="state">Provincia/Estado</label>
							<input type="text" id="state" name="state" 
								class="form-control @error('state') is-invalid @enderror" 
								value="{{ old('state', $stripeData['customer']['address']['state'] ?? '') }}" 
								placeholder="Madrid">
							@error('state')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
						</div>

						<!-- Country -->
						<div class="col-12">
							<label class="form-label" for="country">País *</label>
							<select id="country" name="country" 
								class="form-select @error('country') is-invalid @enderror" required>
								<option value="">Seleccionar país</option>
								<option value="ES" {{ old('country', $stripeData['customer']['address']['country'] ?? 'ES') === 'ES' ? 'selected' : '' }}>España</option>
								<option value="US" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'US' ? 'selected' : '' }}>Estados Unidos</option>
								<option value="MX" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'MX' ? 'selected' : '' }}>México</option>
								<option value="AR" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'AR' ? 'selected' : '' }}>Argentina</option>
								<option value="CO" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'CO' ? 'selected' : '' }}>Colombia</option>
								<option value="CL" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'CL' ? 'selected' : '' }}>Chile</option>
								<option value="PE" {{ old('country', $stripeData['customer']['address']['country'] ?? '') === 'PE' ? 'selected' : '' }}>Perú</option>
							</select>
							@error('country')
								<div class="invalid-feedback">{{ $message }}</div>
							@enderror
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
				<form method="POST" action="{{ route('subscription.cancel') }}" style="display: inline;">
					@csrf
					<button type="submit" class="btn btn-danger">Sí, cancelar</button>
				</form>
			</div>
		</div>
	</div>
</div>

@section('vendor-script')
@endsection

<script>
function confirmCancel()
{
	const modal = new bootstrap.Modal(document.getElementById('cancelSubscriptionModal'));
	modal.show();
}

// Reabrir el modal si hay errores de validación
@if($errors->any())
	document.addEventListener('DOMContentLoaded', function() {
		var myModal = new bootstrap.Modal(document.getElementById('editBillingModal'));
		myModal.show();
	});
@endif
</script>
@endsection
