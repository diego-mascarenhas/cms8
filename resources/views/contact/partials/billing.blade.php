<!-- Current Plan -->
<div class="card mb-4">
    <h5 class="card-header">Plan Actual</h5>
    <div class="card-body">
        <div class="row">
            <div class="col-xl-6 order-1 order-xl-0">
                <div class="mb-2">
                    <h6 class="mb-1">Tu Plan Actual es Básico</h6>
                    <p>Un comienzo simple para todos</p>
                </div>
                <div class="mb-2 pt-1">
                    <h6 class="mb-1">Activo hasta el 9 de Diciembre de 2024</h6>
                    <p>Te enviaremos una notificación al vencimiento de la suscripción</p>
                </div>
                <div class="mb-3 pt-1">
                    <h6 class="mb-1"><span class="me-2">199€ por mes</span> <span
                            class="badge bg-label-primary">Popular</span></h6>
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
            <div class="col-12 order-2 order-xl-0 d-flex flex-wrap gap-2">
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#upgradePlanModal">Actualizar
                    Plan</button>
                <button class="btn btn-label-danger cancel-subscription">Cancelar Suscripción</button>
            </div>
        </div>
    </div>
</div>
<!-- /Current Plan -->

<!-- Payment Methods -->
<div class="card card-action mb-4">
    <div class="card-header align-items-center">
        <h5 class="card-action-title mb-0">Métodos de Pago</h5>
        <div class="card-action-element">
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal"
                data-bs-target="#addNewCCModal"><i class="ti ti-plus ti-xs me-1"></i>Agregar Tarjeta</button>
        </div>
    </div>
    <div class="card-body">
        <div class="added-cards">
            <div class="cardMaster border p-3 rounded mb-3">
                <div class="d-flex justify-content-between flex-sm-row flex-column">
                    <div class="card-information">
                        <img class="mb-3 img-fluid" src="{{ asset('assets/img/icons/payments/mastercard.png') }}"
                            alt="Master Card">
                        <h6 class="mb-2 pt-1">Kaith Morrison</h6>
                        <span class="card-number">&#8727;&#8727;&#8727;&#8727; &#8727;&#8727;&#8727;&#8727;
                            &#8727;&#8727;&#8727;&#8727; 9856</span>
                    </div>
                    <div class="d-flex flex-column text-start text-lg-end">
                        <div class="d-flex order-sm-0 order-1 mt-3">
                            <button class="btn btn-label-primary me-3" data-bs-toggle="modal"
                                data-bs-target="#editCCModal">Editar</button>
                            <button class="btn btn-label-secondary">Eliminar</button>
                        </div>
                        <small class="mt-sm-auto mt-2 order-sm-1 order-0">La tarjeta vence el 26/12</small>
                    </div>
                </div>
            </div>
            <div class="cardMaster border p-3 rounded mb-3">
                <div class="d-flex justify-content-between flex-sm-row flex-column">
                    <div class="card-information">
                        <img class="mb-3 img-fluid" src="{{ asset('assets/img/icons/payments/visa.png') }}"
                            alt="Master Card">
                        <div class="d-flex align-items-center mb-2 pt-1">
                            <h6 class="mb-0 me-3">Tom McBride</h6>
                            <span class="badge bg-label-primary me-1">Primario</span>
                        </div>
                        <span class="card-number">&#8727;&#8727;&#8727;&#8727; &#8727;&#8727;&#8727;&#8727;
                            &#8727;&#8727;&#8727;&#8727; 6542</span>
                    </div>
                    <div class="d-flex flex-column text-start text-lg-end">
                        <div class="d-flex order-sm-0 order-1 mt-3">
                            <button class="btn btn-label-primary me-3" data-bs-toggle="modal"
                                data-bs-target="#editCCModal">Editar</button>
                            <button class="btn btn-label-secondary">Eliminar</button>
                        </div>
                        <small class="mt-sm-auto mt-2 order-sm-1 order-0">La tarjeta vence el 24/10</small>
                    </div>
                </div>
            </div>
            <div class="cardMaster border p-3 rounded">
                <div class="d-flex justify-content-between flex-sm-row flex-column">
                    <div class="card-information">
                        <img class="mb-3 img-fluid"
                            src="{{ asset('assets/img/icons/payments/american-express-logo.png') }}" alt="Visa Card">
                        <h6 class="mb-1 pt-1">Mildred Wagner</h6>
                        <span class="card-number">&#8727;&#8727;&#8727;&#8727; &#8727;&#8727;&#8727;&#8727;
                            &#8727;&#8727;&#8727;&#8727; 5896</span>
                    </div>
                    <div class="d-flex flex-column text-start text-lg-end">
                        <div class="d-flex order-sm-0 order-1 mt-3">
                            <button class="btn btn-label-primary me-3" data-bs-toggle="modal"
                                data-bs-target="#editCCModal">Editar</button>
                            <button class="btn btn-label-secondary">Eliminar</button>
                        </div>
                        <small class="mt-sm-auto mt-2 order-sm-1 order-0">La tarjeta vence el 27/10</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--/ Payment Methods -->

<!-- Billing Address -->
<div class="card card-action mb-4">
    <div class="card-header align-items-center">
        <h5 class="card-action-title mb-0">Dirección de Facturación</h5>
        <div class="card-action-element">
            <button class="btn btn-primary btn-sm edit-address" type="button" data-bs-toggle="modal"
                data-bs-target="#addNewAddress">Editar dirección</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-xl-7 col-12">
                <dl class="row mb-0">
                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Nombre de la Empresa:</dt>
                    <dd class="col-sm-8">{{ config('variables.templateName') }}</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Email de Facturación:</dt>
                    <dd class="col-sm-8">usuario@ejemplo.com</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">ID de Impuestos:</dt>
                    <dd class="col-sm-8">IMP-357378</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Número de IVA:</dt>
                    <dd class="col-sm-8">SDF754K77</dd>

                    <dt class="col-sm-4 mb-2 fw-medium text-nowrap">Dirección de Facturación:</dt>
                    <dd class="col-sm-8">100 Planta de Agua <br>Avenida, Edificio 1303<br> Isla Wake</dd>
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
