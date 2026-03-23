@extends('layouts/layoutManual')

@section('title', __('Facturas y pagos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Facturas y pagos') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Facturas') }}</h5>
                <p>{{ __('Desde el módulo de facturas puedes crear y gestionar facturas: añadir líneas (concepto, cantidad, precio), indicar el cliente, importes base, IVA y total, y enviar la factura por email o descargarla en PDF. Las facturas quedan registradas y vinculadas al cliente para el historial.') }}</p>

                <h5 class="mt-4">{{ __('Pagos') }}</h5>
                <p>{{ __('Los pagos son los movimientos de dinero (cobros y pagos). Puedes registrar pagos vinculados a una factura u otro concepto, con fecha e importe, para llevar el control de qué está cobrado y qué pendiente.') }}</p>

                <h5 class="mt-4">{{ __('Ingresos y gastos') }}</h5>
                <p>{{ __('Los módulos de ingresos y gastos permiten anotar todo el dinero que entra y sale: categorías, conceptos, fechas e importes. Sirven para tener un registro claro de la tesorería y para cruzar con facturas y pagos si lo necesitas.') }}</p>

                <h5 class="mt-4">{{ __('Panel de finanzas') }}</h5>
                <p>{{ __('El panel de finanzas (finance dashboard) resume en una sola pantalla ingresos, gastos y métricas financieras (totales, por periodo, tendencias). Te da una visión global sin tener que abrir cada listado.') }}</p>

                <h5 class="mt-4">{{ __('Tarifas (Fares)') }}</h5>
                <p>{{ __('Las tarifas definen tus precios (por ejemplo por hora, por servicio o por tipo de trabajo). Se usan al armar presupuestos y facturas para que los importes se calculen de forma coherente en toda la plataforma.') }}</p>

                <p class="mb-0">{{ __('Según tu plan y configuración pueden estar disponibles informes contables o de facturación adicionales.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
