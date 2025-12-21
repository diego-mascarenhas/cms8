@extends('layouts/layoutMaster')

@section('title', 'Accounting - Invoices')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<style>
    tr.invoice-void {
        text-decoration: line-through;
        opacity: 0.7;
    }
    
    tr.invoice-uncollectible {
        text-decoration: line-through;
        opacity: 0.7;
        background-color: rgba(255, 0, 0, 0.05);
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
@endsection

@section('content')
@if(session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Total Facturas</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $stripeData['metrics']['total_amount'] ?? '0.00' }}€</h3>
                        </div>
                        <p class="mb-0">{{ ($stripeData['metrics']['total_invoices'] ?? 0) + ($stripeData['metrics']['unpaid_invoices'] ?? 0) }} facturas</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-file ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Pagado</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $stripeData['metrics']['total_paid'] }}€</h3>
                        </div>
                        <p class="mb-0">{{ $stripeData['metrics']['total_invoices'] ?? 0 }} facturas</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-currency-euro ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Pendiente</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $stripeData['metrics']['unpaid'] }}€</h3>
                        </div>
                        <p class="mb-0">{{ $stripeData['metrics']['unpaid_invoices'] ?? 0 }} facturas</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-alert-circle ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Incobrable</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $stripeData['metrics']['uncollectible'] ?? '0.00' }}€</h3>
                        </div>
                        <p class="mb-0">{{ $stripeData['metrics']['uncollectible_invoices'] ?? 0 }} facturas</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-receipt-off ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div id="accounting-app">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Facturas</h5>
        </div>
        <div class="card-datatable table-responsive">
            @forelse($stripeData['grouped_invoices'] as $quarter => $invoices)
                <div class="d-flex justify-content-between align-items-center bg-light p-2 border-bottom">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-calendar-stats me-2"></i>
                        <h6 class="mb-0">{{ $quarter }}</h6>
                    </div>
                    @php
                        $quarterParts = explode(' ', $quarter);
                        $quarterNum = (int)substr($quarterParts[0], 1);
                        $year = $quarterParts[1];
                    @endphp
                    <div class="d-flex">
                        <a href="{{ route('accounting.download-quarter', ['quarter' => $quarterNum, 'year' => $year]) }}"
                           class="btn btn-sm btn-primary d-flex align-items-center me-2">
                            <i class="ti ti-file-zip me-1"></i> Generar ZIP
                        </a>
                        <button 
                            @click="checkZipFile({{ $quarterNum }}, {{ $year }})"
                            class="btn btn-sm btn-outline-primary d-flex align-items-center me-2">
                            <i class="ti ti-download me-1"></i> Descargar ZIP
                        </button>
                        <a href="{{ route('accounting.download-quarter-csv', ['quarter' => $quarterNum, 'year' => $year]) }}"
                           class="btn btn-sm btn-outline-success d-flex align-items-center">
                            <i class="ti ti-file-spreadsheet me-1"></i> CSV
                        </a>
                    </div>
                </div>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Importe</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Separar facturas activas y anuladas/incobrables
                            $activeInvoices = [];
                            $inactiveInvoices = [];
                            
                            foreach ($invoices as $invoice) {
                                if ($invoice['status'] === 'void' || $invoice['status'] === 'uncollectible') {
                                    $inactiveInvoices[] = $invoice;
                                } else {
                                    $activeInvoices[] = $invoice;
                                }
                            }
                        @endphp
                        
                        {{-- Mostrar facturas activas --}}
                        @foreach($activeInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice['number'] }}</td>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="{{ route('accounting.customer', $invoice['customer_id']) }}" class="text-body fw-semibold">
                                        {{ $invoice['customer_name'] ?? 'Desconocido' }}
                                    </a>
                                    <small class="text-muted">{{ $invoice['customer_email'] ?? '' }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold">{{ number_format($invoice['amount'], 2) }}€</span>
                                <small class="text-muted">{{ $invoice['currency'] }}</small>
                            </td>
                            <td>
                                @if($invoice['status'] === 'paid')
                                <span class="badge bg-label-success">Pagado</span>
                                @elseif($invoice['status'] === 'open')
                                <span class="badge bg-label-warning">Pendiente</span>
                                @else
                                <span class="badge bg-label-secondary">{{ ucfirst($invoice['status']) }}</span>
                                @endif
                            </td>
                            <td>{{ $invoice['date'] }}</td>
                            <td>
                                <div class="d-flex justify-content-center">
                                    <a href="{{ route('accounting.invoice', $invoice['id']) }}" class="btn btn-sm btn-icon">
                                        <i class="ti ti-eye text-primary"></i>
                                    </a>
                                    <a href="{{ $invoice['pdf'] }}" target="_blank" class="btn btn-sm btn-icon">
                                        <i class="ti ti-download text-success"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        
                        {{-- Mostrar facturas anuladas/incobrables si existen --}}
                        @if(count($inactiveInvoices) > 0)
                        <tr class="bg-light border-top">
                            <td colspan="6" class="py-3">
                                <h6 class="mb-0 text-secondary fw-bold">FACTURAS ANULADAS E INCOBRABLES</h6>
                            </td>
                        </tr>
                        
                        @foreach($inactiveInvoices as $invoice)
                        <tr class="{{ $invoice['status'] === 'void' ? 'invoice-void' : 'invoice-uncollectible' }}">
                            <td>{{ $invoice['number'] }}</td>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="{{ route('accounting.customer', $invoice['customer_id']) }}" class="text-body fw-semibold">
                                        {{ $invoice['customer_name'] ?? 'Desconocido' }}
                                    </a>
                                    <small class="text-muted">{{ $invoice['customer_email'] ?? '' }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold">{{ number_format($invoice['amount'], 2) }}€</span>
                                <small class="text-muted">{{ $invoice['currency'] }}</small>
                            </td>
                            <td>
                                @if($invoice['status'] === 'void')
                                <span class="badge bg-label-secondary">Anulado</span>
                                @elseif($invoice['status'] === 'uncollectible')
                                <span class="badge bg-label-danger">Incobrable</span>
                                @endif
                            </td>
                            <td>{{ $invoice['date'] }}</td>
                            <td>
                                <div class="d-flex justify-content-center">
                                    <a href="{{ route('accounting.invoice', $invoice['id']) }}" class="btn btn-sm btn-icon">
                                        <i class="ti ti-eye text-primary"></i>
                                    </a>
                                    <a href="{{ $invoice['pdf'] }}" target="_blank" class="btn btn-sm btn-icon">
                                        <i class="ti ti-download text-success"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            @empty
                <div class="text-center py-5">
                    <i class="ti ti-file-x fs-1 text-secondary mb-2"></i>
                    <p>No se encontraron facturas</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    new Vue({
        el: '#accounting-app',
        methods: {
            checkZipFile(quarter, year) {
                const userId = {{ auth()->id() }};
                const zipUrl = `/storage/downloads/user_${userId}/facturas_Q${quarter}_${year}.zip`;
                
                // Verificar si el archivo existe
                axios.head(zipUrl)
                    .then(response => {
                        // El archivo existe, redirigir para descarga
                        window.location.href = zipUrl;
                    })
                    .catch(error => {
                        // El archivo no existe, mostrar mensaje
                        alert('El archivo ZIP aún no ha sido generado. Por favor, haga clic en "Generar ZIP" primero.');
                    });
            }
        }
    });
</script>
@endsection 