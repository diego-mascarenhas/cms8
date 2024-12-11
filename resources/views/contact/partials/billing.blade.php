<!-- Current Plan -->
<div class="card mb-4">
    <h5 class="card-header">Plan Actual</h5>
    <div class="card-body">
        @if ($stripeData && $stripeData['subscription'])
            <div class="row">
                <div class="col-xl-6 order-1 order-xl-0">
                    <div class="mb-2 pt-1">
                        <h6 class="mb-1">Activo hasta el {{ $stripeData['subscription']['current_period_end'] }}</h6>
                        <p>Te enviaremos una notificación al vencimiento de la suscripción</p>
                    </div>
                    <div class="mb-3 pt-1">
                        <h6 class="mb-1">
                            <span class="me-2">{{ number_format($stripeData['subscription']['amount'], 2) }}
                                {{ $stripeData['subscription']['currency'] }} por mes</span>
                            <span
                                class="badge bg-label-{{ $stripeData['subscription']['status'] === 'active' ? 'success' : 'warning' }}">
                                {{ ucfirst($stripeData['subscription']['status']) }}
                            </span>
                        </h6>
                        <p>Plan estándar para pequeñas y medianas empresas</p>
                    </div>
                </div>
                <div class="col-xl-6 order-0 order-xl-0">
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading mb-2">¡Necesitamos tu atención!</h5>
                        <span>Tu plan requiere actualización</span>
                    </div>
                    <div class="plan-statistics">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-1">Días</h6>
                            <h6 class="mb-1">24 de 30 Días</h6>
                        </div>
                        <div class="progress mb-1" style="height: 10px;">
                            <div class="progress-bar w-75" role="progressbar" aria-valuenow="75" aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                        <p>Quedan 6 días hasta que tu plan requiera actualización</p>
                    </div>
                </div>
                <!-- <div class="col-12 order-2 order-xl-0 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#upgradePlanModal">
                        Actualizar Plan
                    </button>
                    <button class="btn btn-label-danger cancel-subscription">Cancelar Suscripción</button>
                </div> -->
            </div>
        @else
            <div class="alert alert-warning" role="alert">
                <h6 class="alert-heading mb-2">No hay información de Stripe disponible</h6>
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
<div class="card card-action mb-4 opacity-50">
    <div class="card-header align-items-center">
        <h5 class="card-action-title mb-0">Dirección de Facturación</h5>
        <div class="card-action-element">
            <button class="btn btn-primary btn-sm edit-address" type="button" data-bs-toggle="modal"
                data-bs-target="#addNewAddress"><i class="ti ti-edit ti-xs me-1"></i>Editar dirección</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-xl-7 col-12">
                <dl class="row mb-0">
                    <dt class="col-sm-5 mb-2 fw-medium text-nowrap">Nombre de la Empresa:</dt>
                    <dd class="col-sm-7">{{ config('variables.templateName') }}</dd>

                    <dt class="col-sm-5 mb-2 fw-medium text-nowrap">Email de Facturación:</dt>
                    <dd class="col-sm-7">usuario@ejemplo.com</dd>

                    <dt class="col-sm-5 mb-2 fw-medium text-nowrap">ID de Impuestos:</dt>
                    <dd class="col-sm-7">IMP-357378</dd>

                    <dt class="col-sm-5 mb-2 fw-medium text-nowrap">Número de IVA:</dt>
                    <dd class="col-sm-7">SDF754K77</dd>

                    <dt class="col-sm-5 mb-2 fw-medium text-nowrap">Dirección de Facturación:</dt>
                    <dd class="col-sm-7">100 Planta de Agua <br>Avenida, Edificio 1303<br> Isla Wake</dd>
                </dl>
            </div>
            <div class="col-xl-5 col-12">
                <dl class="row mb-0">
                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Contacto:</dt>
                    <dd class="col-sm-8">+1 (605) 977-32-65</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">País:</dt>
                    <dd class="col-sm-8">Isla Wake</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Estado:</dt>
                    <dd class="col-sm-8">Capholim</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Código Postal:</dt>
                    <dd class="col-sm-8">403114</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<!--/ Billing Address -->
