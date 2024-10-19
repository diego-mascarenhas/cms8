@php
$balanceData = [
    'clients' => 24,
    'invoices' => 165,
    'paid' => '$2.46k',
    'unpaid' => '$876'
];

$invoices = [
    ['id' => '#5036', 'status' => 'pending', 'client' => 'Andrew Burns', 'email' => 'andrew@example.org', 'total' => '$3171', 'date' => '2024-08-19', 'balance' => '-$205'],
    ['id' => '#5035', 'status' => 'draft', 'client' => 'Dana Carey', 'email' => 'dana@example.net', 'total' => '$4263', 'date' => '2024-08-20', 'balance' => '$762'],
    ['id' => '#5034', 'status' => 'paid', 'client' => 'Tammy Sanchez', 'email' => 'tammy@example.com', 'total' => '$4838', 'date' => '2024-08-10', 'balance' => 'Paid'],
    ['id' => '#5033', 'status' => 'paid', 'client' => 'Lori Wells', 'email' => 'lori@example.org', 'total' => '$2869', 'date' => '2024-08-12', 'balance' => 'Paid'],
    ['id' => '#5032', 'status' => 'paid', 'client' => 'Richard Payne', 'email' => 'richard@example.com', 'total' => '$5181', 'date' => '2024-08-31', 'balance' => 'Paid'],
    ['id' => '#5031', 'status' => 'paid', 'client' => 'Jennifer Summers', 'email' => 'jennifer@example.net', 'total' => '$3313', 'date' => '2024-08-21', 'balance' => 'Paid'],
    ['id' => '#5030', 'status' => 'paid', 'client' => 'Justin Richardson', 'email' => 'justin@example.com', 'total' => '$5565', 'date' => '2024-08-07', 'balance' => 'Paid'],
    ['id' => '#5029', 'status' => 'paid', 'client' => 'Nicholas Tanner', 'email' => 'nicholas@example.com', 'total' => '$3851', 'date' => '2024-08-29', 'balance' => 'Paid'],
    ['id' => '#5028', 'status' => 'pending', 'client' => 'Crystal Mayo', 'email' => 'crystal@example.com', 'total' => '$3325', 'date' => '2024-08-18', 'balance' => '$361'],
    ['id' => '#5027', 'status' => 'paid', 'client' => 'Mary Garcia', 'email' => 'mary@example.com', 'total' => '$2719', 'date' => '2024-08-13', 'balance' => 'Paid'],
];
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Saldo</h5>
        <div class="dropdown">
            <button class="btn btn-link p-0" type="button" id="balanceInfo" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-info-circle"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="balanceInfo">
                <p class="dropdown-item-text">Ingresos y gastos del cliente con su histórico</p>
            </div>
        </div>
    </div>
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
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <label for="showEntries">Show</label>
                <select id="showEntries" class="form-select form-select-sm d-inline-block w-auto">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <label for="showEntries">entries</label>
            </div>
            <button class="btn btn-primary btn-sm">+ Create Invoice</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th></th>
                        <th>#</th>
                        <th>STATUS</th>
                        <th>CLIENT</th>
                        <th>TOTAL</th>
                        <th>ISSUED DATE</th>
                        <th>BALANCE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr>
                        <td><input type="checkbox"></td>
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
                        <td>
                            <div class="dropdown">
                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i> Edit</a>
                                    <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-trash me-1"></i> Delete</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>Showing 1 to 10 of 50 entries</div>
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