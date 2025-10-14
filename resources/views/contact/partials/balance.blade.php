<div class="card mb-4">
    <h5 class="card-header d-flex justify-content-between align-items-center">
        Saldo
        @if(!empty($enterprise->code))
            <a href="https://dashboard.stripe.com/invoices/create?customer={{ $enterprise->code }}"
               target="_blank"
               class="btn btn-primary btn-sm">
                + Crear factura
            </a>
        @endif
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
                        <h4 class="mb-0">{{ count($stripeData['unpaid_invoices'] ?? []) }}</h4>
                        <small class="text-muted">Impagas</small>
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

        <!-- Invoices Table (Paid) -->
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
                                @if(!empty($invoice['pdf']))
                                    <a href="{{ $invoice['pdf'] }}" target="_blank" class="text-body me-2" title="Descargar PDF">
                                        <i class="ti ti-download"></i>
                                    </a>
                                @endif
                                @if(!empty($invoice['dashboard_url']))
                                    <a href="{{ $invoice['dashboard_url'] }}" target="_blank" class="text-body" title="Ver en Stripe">
                                        <i class="ti ti-external-link"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Unpaid Invoices Table (Open + Uncollectible) -->
        @if (!empty($stripeData['unpaid_invoices']))
        <div class="mt-4">
            <h6 class="mb-3">Impago</h6>
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
                        @foreach ($stripeData['unpaid_invoices'] as $invoice)
                            <tr>
                                <td>{{ $invoice['number'] }}</td>
                                <td class="text-center">{{ $invoice['date'] }}</td>
                                <td class="text-end">{{ $invoice['amount'] }} {{ $invoice['currency'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-label-{{ $invoice['status'] === 'uncollectible' ? 'danger' : 'warning' }}">
                                        {{ $invoice['status'] === 'uncollectible' ? 'Incobrable' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if(!empty($invoice['pdf']))
                                        <a href="{{ $invoice['pdf'] }}" target="_blank" class="text-body me-2" title="Descargar PDF">
                                            <i class="ti ti-download"></i>
                                        </a>
                                    @endif
                                    @if(!empty($invoice['dashboard_url']))
                                        <a href="{{ $invoice['dashboard_url'] }}" target="_blank" class="text-body" title="Ver en Stripe">
                                            <i class="ti ti-external-link"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if (!empty($stripeData['void_invoices']))
        <div class="mt-4">
            <h6 class="mb-3">Anuladas</h6>
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
                        @foreach ($stripeData['void_invoices'] as $invoice)
                            <tr>
                                <td>{{ $invoice['number'] }}</td>
                                <td class="text-center">{{ $invoice['date'] }}</td>
                                <td class="text-end">{{ $invoice['amount'] }} {{ $invoice['currency'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-label-secondary">Anulada</span>
                                </td>
                                <td class="text-center">
                                    @if(!empty($invoice['pdf']))
                                        <a href="{{ $invoice['pdf'] }}" target="_blank" class="text-body me-2" title="Descargar PDF">
                                            <i class="ti ti-download"></i>
                                        </a>
                                    @endif
                                    @if(!empty($invoice['dashboard_url']))
                                        <a href="{{ $invoice['dashboard_url'] }}" target="_blank" class="text-body" title="Ver en Stripe">
                                            <i class="ti ti-external-link"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if (!empty($stripeData['credit_notes']))
        <div class="mt-4">
            <h6 class="mb-3">Notas de Crédito</h6>
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
                        @foreach ($stripeData['credit_notes'] as $note)
                            <tr>
                                <td>{{ $note['number'] }}</td>
                                <td class="text-center">{{ $note['date'] }}</td>
                                <td class="text-end">-{{ $note['amount'] }} {{ $note['currency'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-label-{{ $note['status'] === 'void' ? 'secondary' : 'info' }}">
                                        {{ $note['status'] === 'void' ? 'Anulada' : 'Emitida' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if(!empty($note['pdf']))
                                        <a href="{{ $note['pdf'] }}" target="_blank" class="text-body" title="Descargar PDF">
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
        @endif
    </div>
</div>
