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
										$countries = [
											'ES' => 'España',
											'AR' => 'Argentina',
											'MX' => 'México',
											'US' => 'Estados Unidos',
											'CO' => 'Colombia',
											'CL' => 'Chile',
											'PE' => 'Perú',
											'UY' => 'Uruguay',
										];
										$countryCode = $stripeData['customer']->address->country;
										$countryName = $countries[$countryCode] ?? $countryCode;
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

<!-- All Subscriptions -->
<div class="row">
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">Todas las Suscripciones</h5>
				<a href="{{ route('subscription.index') }}" class="btn btn-sm btn-primary">
					<i class="ti ti-plus ti-xs me-1"></i>
					Ver Planes
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
									
									// Get type icon and name
									$typeIcons = [
										'mailer' => 'ti-send',
										'hosting' => 'ti-server',
										'domain' => 'ti-world',
										'licence' => 'ti-license',
										'default' => 'ti-package',
									];
									$typeIcon = $typeIcons[$type] ?? 'ti-package';
									
									$typeNames = [
										'mailer' => 'Mailer',
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
				<div class="text-center py-5">
					<i class="ti ti-package-off ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
					<h5 class="text-muted mb-2">No tienes suscripciones activas</h5>
					<p class="text-muted mb-4">Explora nuestros planes para comenzar a utilizar los servicios</p>
					<a href="{{ route('subscription.index') }}" class="btn btn-primary">
						<i class="ti ti-eye ti-xs me-1"></i>
						Ver Planes Disponibles
					</a>
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
							<label class="form-label" for="country">País (*)</label>
							<select class="form-select @error('country') is-invalid @enderror"
								id="country"
								name="country">
								<option value="">Seleccionar país</option>
								<option value="AR" {{ old('country', $stripeData['customer']->address->country ?? '') == 'AR' ? 'selected' : '' }}>Argentina</option>
								<option value="ES" {{ old('country', $stripeData['customer']->address->country ?? '') == 'ES' ? 'selected' : '' }}>España</option>
								<option value="MX" {{ old('country', $stripeData['customer']->address->country ?? '') == 'MX' ? 'selected' : '' }}>México</option>
								<option value="CL" {{ old('country', $stripeData['customer']->address->country ?? '') == 'CL' ? 'selected' : '' }}>Chile</option>
								<option value="CO" {{ old('country', $stripeData['customer']->address->country ?? '') == 'CO' ? 'selected' : '' }}>Colombia</option>
								<option value="PE" {{ old('country', $stripeData['customer']->address->country ?? '') == 'PE' ? 'selected' : '' }}>Perú</option>
								<option value="UY" {{ old('country', $stripeData['customer']->address->country ?? '') == 'UY' ? 'selected' : '' }}>Uruguay</option>
								<option value="US" {{ old('country', $stripeData['customer']->address->country ?? '') == 'US' ? 'selected' : '' }}>Estados Unidos</option>
							</select>
							@error('country')
								<div class="invalid-feedback d-block">{{ $message }}</div>
							@enderror
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

@section('vendor-script')
@endsection

<script>
function confirmCancel(stripeId)
{
	// Set the stripe_id in the hidden input
	document.getElementById('cancelStripeId').value = stripeId || '';
	
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
