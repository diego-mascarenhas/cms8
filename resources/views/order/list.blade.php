@extends('layouts/layoutMaster')

@section('title', __('Órdenes'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">{{ __('Órdenes') }}</h4>
		<p class="text-muted">{{ __('Gestiona las órdenes de tus clientes') }}</p>
	</div>
	@can('order.create')
	<div class="mt-3 mt-md-0">
		<a href="{{ route('order.create') }}" class="btn btn-primary">
			<i class="ti ti-plus me-1"></i> {{ __('Agregar Orden') }}
		</a>
	</div>
	@endcan
</div>

<!-- Order List Widget -->
<div class="card mb-4">
	<div class="card-widget-separator-wrapper">
		<div class="card-body card-widget-separator">
			<div class="row gy-4 gy-sm-1">
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-3 pb-sm-0">
						<div>
							<h6 class="mb-2">{{ __('Pendientes de Pago') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Order::where('payment_status', 'pending')->count() ?? 0 }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Órdenes') }}</span></p>
						</div>
						<span class="avatar me-sm-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-clock text-body"></i>
							</span>
						</span>
					</div>
					<hr class="d-none d-sm-block d-lg-none me-4">
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-3 pb-sm-0">
						<div>
							<h6 class="mb-2">{{ __('Completadas') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Order::where('delivery_status', 'delivered')->count() ?? 0 }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Entregadas') }}</span></p>
						</div>
						<span class="avatar p-2 me-lg-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-circle-check text-body"></i>
							</span>
						</span>
					</div>
					<hr class="d-none d-sm-block d-lg-none">
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start border-end pb-3 pb-sm-0 card-widget-3">
						<div>
							<h6 class="mb-2">{{ __('Reembolsadas') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Order::where('payment_status', 'refunded')->count() ?? 0 }}</h4>
							<p class="mb-0 text-muted">{{ __('Órdenes') }}</p>
						</div>
						<span class="avatar p-2 me-sm-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-refresh text-body"></i>
							</span>
						</span>
					</div>
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start">
						<div>
							<h6 class="mb-2">{{ __('Fallidas') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Order::where('payment_status', 'failed')->count() ?? 0 }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Órdenes') }}</span></p>
						</div>
						<span class="avatar p-2">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-x text-body"></i>
							</span>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Order List Table -->
<div class="card">
	<div class="card-datatable table-responsive">
		{{ $dataTable->table(['class' => 'datatables-orders table']) }}
	</div>
</div>

@endsection

@push('scripts')
{{ $dataTable->scripts() }}
@endpush

