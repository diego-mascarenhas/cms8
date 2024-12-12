<div class="card mb-4">
    <h5 class="card-header d-flex justify-content-between align-items-center">
        Saldo
        <a href="https://dashboard.stripe.com/invoices/create?customer={{ $data->enterprise->code }}" 
           target="_blank" 
           class="btn btn-primary btn-sm">
            + Crear factura
        </a>
    </h5>
    <div class="card-body">
        <!-- Balance Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="avatar me-3 bg-label-primary p-2">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-file text-primary ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="mb-0">{{ count($stripeData['invoices'] ?? []) }}</h4>
                        <small class="text-muted">Facturas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="avatar me-3 bg-label-success p-2">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-currency-dollar text-success ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="mb-0">{{ $stripeData['metrics']['total_paid'] ?? '0.00' }}€</h4>
                        <small class="text-muted">Pagado</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center">
                    <div class="avatar me-3 bg-label-warning p-2">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-bolt text-warning ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="mb-0">{{ $stripeData['metrics']['unpaid'] ?? '0.00' }}€</h4>
                        <small class="text-muted">Impago</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-end">Monto</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stripeData['invoices'] ?? [] as $invoice)
                        <tr>
                            <td>{{ $invoice['number'] }}</td>
                            <td class="text-center">{{ $invoice['date'] }}</td>
                            <td class="text-end">{{ $invoice['amount'] }} {{ $invoice['currency'] }}</td>
                            <td class="text-center">
                                <span class="badge bg-label-{{ $invoice['status'] === 'paid' ? 'success' : 'warning' }}">
                                    {{ $invoice['status'] === 'paid' ? 'Pagado' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($invoice['pdf'])
                                    <a href="{{ $invoice['pdf'] }}" target="_blank" class="btn btn-sm btn-icon btn-label-secondary">
                                        <i class="ti ti-download"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
