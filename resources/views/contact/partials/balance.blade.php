@php
$balanceData = [
    'clients' => 24,
    'invoices' => 165,
    'paid' => '$2.46k',
    'unpaid' => '$876'
];

$invoices = [
    ['id' => '#5036', 'status' => 'pendiente', 'client' => 'Andrew Burns', 'email' => 'andrew@example.org', 'total' => '3171€', 'date' => '19/08/2024', 'balance' => '-205€'],
    ['id' => '#5035', 'status' => 'pendiente', 'client' => 'Dana Carey', 'email' => 'dana@example.net', 'total' => '4263€', 'date' => '20/08/2024', 'balance' => '762€'],
    ['id' => '#5034', 'status' => 'pagado', 'client' => 'Tammy Sanchez', 'email' => 'tammy@example.com', 'total' => '4838€', 'date' => '10/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5033', 'status' => 'pagado', 'client' => 'Lori Wells', 'email' => 'lori@example.org', 'total' => '2869€', 'date' => '12/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5032', 'status' => 'pagado', 'client' => 'Richard Payne', 'email' => 'richard@example.com', 'total' => '5181€', 'date' => '31/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5031', 'status' => 'pagado', 'client' => 'Jennifer Summers', 'email' => 'jennifer@example.net', 'total' => '3313€', 'date' => '21/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5030', 'status' => 'pagado', 'client' => 'Justin Richardson', 'email' => 'justin@example.com', 'total' => '5565€', 'date' => '07/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5029', 'status' => 'pagado', 'client' => 'Nicholas Tanner', 'email' => 'nicholas@example.com', 'total' => '3851€', 'date' => '29/08/2024', 'balance' => 'Pagado'],
    ['id' => '#5028', 'status' => 'pendiente', 'client' => 'Crystal Mayo', 'email' => 'crystal@example.com', 'total' => '3325€', 'date' => '18/08/2024', 'balance' => '361€'],
    ['id' => '#5027', 'status' => 'pagado', 'client' => 'Mary Garcia', 'email' => 'mary@example.com', 'total' => '2719€', 'date' => '13/08/2024', 'balance' => 'Pagado'],
];
@endphp

<div class="card mb-4 opacity-50">
    <h5 class="card-header d-flex justify-content-between align-items-center">
        Saldo
        <button type="button" class="btn btn-primary btn-sm">
            + Crear factura
        </button>
    </h5>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-user-check ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $balanceData['clients'] }}</h6>
                        <small>Clientes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="ti ti-file-invoice ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $balanceData['invoices'] }}</h6>
                        <small>Facturas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-currency-dollar ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $balanceData['paid'] }}</h6>
                        <small>Pagado</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-currency-dollar-off ti-sm"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $balanceData['unpaid'] }}</h6>
                        <small>Impagado</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Estado</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Vencimiento</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice['id'] }}</td>
                        <td>
                            <span class="badge bg-label-{{ $invoice['status'] == 'paid' ? 'success' : ($invoice['status'] == 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($invoice['status']) }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-2">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($invoice['client']) }}&background=random" alt="Avatar" class="rounded-circle">
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ $invoice['client'] }}</h6>
                                    <small class="text-muted">{{ $invoice['email'] }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $invoice['total'] }}</td>
                        <td>{{ $invoice['date'] }}</td>
                        <td>
                            <span class="{{ $invoice['balance'] == 'Paid' ? 'text-success' : '' }}">
                                {{ $invoice['balance'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>Mostrando 1 a 10 de 50</div>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevrons-left"></i></a></li>
                    <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevron-left"></i></a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                    <li class="page-item"><a class="page-link" href="#">5</a></li>
                    <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevron-right"></i></a></li>
                    <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevrons-right"></i></a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>