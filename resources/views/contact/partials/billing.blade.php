<!-- Current Plan -->
<div class="card mb-4">
    <h5 class="card-header">Servicios</h5>
    <div class="card-body">
        @if ($stripeData && isset($stripeData['subscriptions']))
            @if(!empty($stripeData['subscriptions']))
                @foreach($stripeData['subscriptions'] as $subscription)
                    <div class="card shadow-none bg-lighter mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-0">{{ $subscription['product_name'] }}</h6>
                                    @if(isset($subscription['description']))
                                        <small class="text-muted d-block mt-1">{{ $subscription['description'] }}</small>
                                    @endif
                                    <small class="text-muted d-block mt-1">
                                        {{ \Carbon\Carbon::createFromTimestamp($subscription['created'])->format('d M, Y') }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-label-{{ $subscription['status'] === 'active' ? 'success' : ($subscription['status'] === 'past_due' ? 'danger' : 'warning') }}">
                                        {{ $subscription['status_translated'] }}
                                    </span>
                                    <small class="text-muted d-block mt-2">
                                        {{ number_format($subscription['amount'], 2) }}
                                        {{ $subscription['currency'] }}/{{ $subscription['interval'] === 'year' ? 'año' : 'mes' }}
                                    </small>
                                </div>
                            </div>

                            @php
								$start = \Carbon\Carbon::createFromTimestamp($subscription['current_period_start']);
								$end = \Carbon\Carbon::createFromTimestamp($subscription['current_period_end']);
								$now = \Carbon\Carbon::now();

								// Total dias del periodo (al menos 1 para evitar división por cero)
								$totalDays = max(1, $start->diffInDays($end));

								// Días transcurridos dentro del periodo [0, totalDays]
								$usedDaysRaw = $start->diffInDays($now, false); // negativo si aún no inició
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

                            @if($subscription['collection_method'] === 'send_invoice')
                                <small class="text-muted d-block mt-2">
                                    Facturación por adelantado, pago a {{ $subscription['days_until_due'] }} días
                                </small>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="alert alert-warning" role="alert">
                    <span>Este cliente no tiene suscripciones registradas</span>
                </div>
            @endif
        @else
            <div class="alert alert-warning" role="alert">
                <p class="mb-0">Este contacto no tiene una cuenta de Stripe asociada.</p>
            </div>
        @endif
    </div>
</div>
<!-- /Current Plan -->

<!-- Payment Methods -->
<div class="card card-action mb-4">
    <div class="card-header align-items-center">
        <h5 class="card-action-title mb-0">Métodos de Pago</h5>
        <!-- <div class="card-action-element">
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addNewCCModal">
                <i class="ti ti-plus ti-xs me-1"></i>Agregar Tarjeta
            </button>
        </div> -->
    </div>
    <div class="card-body">
        <div class="added-cards">
            @if ($stripeData && $stripeData['payment_method'])
                <div class="cardMaster border p-3 rounded mb-3">
                    <div class="d-flex justify-content-between flex-sm-row flex-column">
                        <div class="card-information">
                            <img class="mb-3 img-fluid"
                                src="{{ asset('assets/img/icons/payments/' . strtolower($stripeData['payment_method']['brand']) . '.png') }}"
                                alt="{{ $stripeData['payment_method']['brand'] }}">
                            <h6 class="mb-2 pt-1">{{ $stripeData['customer']['name'] }}</h6>
                            <span class="card-number">
                                &#8727;&#8727;&#8727;&#8727; &#8727;&#8727;&#8727;&#8727;
                                &#8727;&#8727;&#8727;&#8727; {{ $stripeData['payment_method']['last4'] }}
                            </span>
                        </div>
                        <div class="d-flex flex-column text-start text-lg-end">
                            <!--
                            <div class="d-flex order-sm-0 order-1 mt-3">
                                <button class="btn btn-label-primary me-3" data-bs-toggle="modal"
                                    data-bs-target="#editCCModal">Editar</button>
                                <button class="btn btn-label-secondary">Eliminar</button>
                            </div>
                             -->
                            <small class="mt-sm-auto mt-2 order-sm-1 order-0">
                                La tarjeta vence el
                                {{ sprintf('%02d/%d', $stripeData['payment_method']['exp_month'], $stripeData['payment_method']['exp_year']) }}
                            </small>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info mb-0">
                    <p class="mb-0">No hay métodos de pago registrados.</p>
                </div>
            @endif
        </div>
    </div>
</div>
<!--/ Payment Methods -->

<!-- Billing Address -->
<div class="card card-action mb-4">
    <div class="card-header align-items-center">
        <h5 class="card-action-title mb-0">Dirección de Facturación</h5>
    </div>
    <div class="card-body">
        @if ($stripeData && isset($stripeData['customer']))
            <div class="row">
                <div class="col-xl-8 col-12">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 mb-2 fw-medium text-nowrap">Razón Social:</dt>
                        <dd class="col-sm-9">{{ $stripeData['customer']['name'] ?? 'No especificado' }}</dd>

                        <dt class="col-sm-3 mb-2 fw-medium text-nowrap">Email:</dt>
                        <dd class="col-sm-9">{{ $stripeData['customer']['email'] ?? 'No especificado' }}</dd>
                    </dl>
                </div>
                <div class="col-xl-4 col-12">
                    <dl class="row mb-0">
                        @if(isset($stripeData['customer']['tax_ids']) && !empty($stripeData['customer']['tax_ids']))
                            @foreach($stripeData['customer']['tax_ids'] as $taxId)
                                <dt class="col-sm-5 mb-2 fw-medium text-nowrap">{{ strtoupper($taxId['type']) }}:</dt>
                                <dd class="col-sm-7">{{ $taxId['value'] }}
                                    <small class="ms-2 text-muted">({{ $taxId['country'] }})</small>
                                </dd>
                            @endforeach
                        @else
                            <dt class="col-sm-5 mb-2 fw-medium text-nowrap">CIF:</dt>
                            <dd class="col-sm-7">No especificado</dd>
                        @endif

                        <dt class="col-sm-5 mb-2 fw-medium text-nowrap">Fecha de alta:</dt>
                        <dd class="col-sm-7">{{ $stripeData['customer']['created'] }}</dd>
                    </dl>
                </div>
            </div>
        @else
            <div class="alert alert-info mb-0">
                <p class="mb-0">No hay información de facturación disponible.</p>
            </div>
        @endif
    </div>
</div>
<!--/ Billing Address -->
