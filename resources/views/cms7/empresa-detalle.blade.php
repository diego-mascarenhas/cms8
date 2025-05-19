@extends('layouts/layoutMaster')

@section('title', 'Datos CMS7')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-style')
    <style>
        .cms7-block {
            margin-bottom: 2rem;
        }
        .cms7-block .card-header {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        pre {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
@endsection

@section('content')
<h4 class="fw-bold py-3 mb-4">
    <span class="text-muted fw-light">CMS7 /</span> Detalle de Empresa #{{ $empresa->id }}
</h4>

<div class="row">
    <div class="col-12">
        <div class="card cms7-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Datos de la Empresa</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <h6>ID</h6>
                        <p>{{ $empresa->id }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Nombre</h6>
                        <p>{{ $empresa->empresa }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Estado</h6>
                        <p>
                            <span class="badge bg-{{ $empresa->estado == 1 ? 'success' : 'warning' }}">
                                {{ $empresa->estado == 1 ? 'Activo' : 'Inactivo' }}
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Teléfono</h6>
                        <p>{{ $empresa->telefono ?? 'No especificado' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Email</h6>
                        <p>{{ $empresa->email ?? 'No especificado' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Sitio Web</h6>
                        <p>{{ $empresa->web ?? 'No especificado' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Dirección</h6>
                        <p>{{ $empresa->domicilio ?? 'No especificada' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Código Postal</h6>
                        <p>{{ $empresa->codigo_postal ?? 'No especificado' }}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h6>Localidad</h6>
                        <p>{{ $empresa->localidad ?? 'No especificada' }}</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#empresaJson" aria-expanded="false">
                            Ver datos completos (JSON)
                        </button>
                        <div class="collapse mt-2" id="empresaJson">
                            <pre>{{ json_encode($empresa, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bloque de Datos Fiscales -->
    <div class="col-12">
        <div class="card cms7-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Datos Fiscales / Razón Social</h5>
                    <span class="badge bg-primary">{{ $datosFiscales->count() }} registros</span>
                </div>
            </div>
            <div class="card-body">
                @if($datosFiscales->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>CUIT</th>
                                    <th>Condición IVA</th>
                                    <th>Dirección</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($datosFiscales as $fiscal)
                                <tr>
                                    <td>{{ $fiscal->id }}</td>
                                    <td>{{ $fiscal->razon_social }}</td>
                                    <td>{{ $fiscal->cuit }}</td>
                                    <td>
                                        @if($fiscal->id_condicion_iva == 1)
                                            Consumidor Final
                                        @elseif($fiscal->id_condicion_iva == 2)
                                            Responsable Inscripto
                                        @elseif($fiscal->id_condicion_iva == 3)
                                            Monotributo
                                        @elseif($fiscal->id_condicion_iva == 4)
                                            Exento
                                        @else
                                            {{ $fiscal->id_condicion_iva }}
                                        @endif
                                    </td>
                                    <td>{{ $fiscal->domicilio }}</td>
                                    <td>
                                        <span class="badge bg-{{ $fiscal->estado == 1 ? 'success' : 'warning' }}">
                                            {{ $fiscal->estado == 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info">No hay datos fiscales registrados</div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Bloque de Contactos -->
    <div class="col-12">
        <div class="card cms7-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Contactos</h5>
                    <span class="badge bg-primary">{{ $contactos->count() }} registros</span>
                </div>
            </div>
            <div class="card-body">
                @if($contactos->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Cargo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($contactos as $contacto)
                                <tr>
                                    <td>{{ $contacto->id }}</td>
                                    <td>{{ $contacto->nombre }} {{ $contacto->apellido }}</td>
                                    <td>{{ $contacto->email }}</td>
                                    <td>{{ $contacto->telefono ?: $contacto->celular }}</td>
                                    <td>{{ $contacto->cargo }}</td>
                                    <td>
                                        <span class="badge bg-{{ $contacto->estado == 1 ? 'success' : 'warning' }}">
                                            {{ $contacto->estado == 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info">No hay contactos registrados</div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Bloque de Servicios -->
    <div class="col-12">
        <div class="card cms7-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Servicios</h5>
                    <span class="badge bg-primary">{{ $servicios->count() }} registros</span>
                </div>
            </div>
            <div class="card-body">
                @if($servicios->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover" id="serviciosTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Dominio</th>
                                    <th>IP</th>
                                    <th>Precio</th>
                                    <th>Frecuencia</th>
                                    <th>Próxima Facturación</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($servicios as $servicio)
                                <tr>
                                    <td>{{ $servicio->id }}</td>
                                    <td>{{ $servicio->descripcion }}</td>
                                    <td>{{ $servicio->dominio ?? '-' }}</td>
                                    <td>{{ $servicio->ip ?? '-' }}</td>
                                    <td>
                                        @if($servicio->valor > 0)
                                            {{ number_format($servicio->valor, 2) }}
                                            @if($servicio->id_moneda == 2)
                                                USD
                                            @else
                                                $
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($servicio->frecuencia == 1) 
                                            Mensual
                                        @elseif($servicio->frecuencia == 3) 
                                            Trimestral
                                        @elseif($servicio->frecuencia == 6) 
                                            Semestral
                                        @elseif($servicio->frecuencia == 12) 
                                            Anual
                                        @else 
                                            {{ $servicio->frecuencia }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($servicio->proxima)
                                            {{ \Carbon\Carbon::parse($servicio->proxima)->format('d/m/Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $servicio->estado == 1 ? 'success' : 'warning' }}">
                                            {{ $servicio->estado == 1 ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info">No hay servicios registrados</div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Bloque de Facturas -->
    <div class="col-12">
        <div class="card cms7-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Facturas</h5>
                    <span class="badge bg-primary">{{ $facturas->count() }} registros</span>
                </div>
            </div>
            <div class="card-body">
                @if($facturas->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover" id="facturasTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Razón Social</th>
                                    <th>Número</th>
                                    <th>Fecha</th>
                                    <th>Vencimiento</th>
                                    <th>Total</th>
                                    <th>Saldo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($facturas as $factura)
                                <tr>
                                    <td>{{ $factura->id }}</td>
                                    <td>{{ $factura->razon_social }}</td>
                                    <td>
                                        @if($factura->operacion == 'V')
                                            {{ str_pad($factura->numero_talonario, 4, '0', STR_PAD_LEFT) }}-{{ str_pad($factura->numero_factura, 8, '0', STR_PAD_LEFT) }}
                                        @else
                                            {{ $factura->numero_factura }}
                                        @endif
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($factura->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($factura->vencimiento)->format('d/m/Y') }}</td>
                                    <td>{{ number_format($factura->total_neto, 2) }} {{ $factura->id_moneda == 2 ? 'USD' : '$' }}</td>
                                    <td>{{ number_format($factura->saldo, 2) }} {{ $factura->id_moneda == 2 ? 'USD' : '$' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $factura->estado == 1 ? 'success' : 'warning' }}">
                                            {{ $factura->estado == 1 ? 'Pagada' : 'Pendiente' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info">No hay facturas registradas</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#serviciosTable, #facturasTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        pageLength: 5,
        lengthMenu: [5, 10, 25, 50],
        ordering: true,
        responsive: true
    });
});
</script>
@endsection 