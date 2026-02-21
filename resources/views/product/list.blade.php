@extends('layouts/layoutMaster')

@section('title', __('Productos'))

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
		<h4 class="mb-1 mt-3">{{ __('Productos') }}</h4>
		<p class="text-muted">{{ __('Gestiona tus productos') }}</p>
	</div>
	@can('product.create')
	<div class="mt-3 mt-md-0">
		<a href="{{ route('product.create') }}" class="btn btn-primary">
			<i class="ti ti-plus me-1"></i> {{ __('Agregar Producto') }}
		</a>
	</div>
	@endcan
</div>

<!-- Product List Widget -->
<div class="card mb-4">
	<div class="card-widget-separator-wrapper">
		<div class="card-body card-widget-separator">
			<div class="row gy-4 gy-sm-1">
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-3 pb-sm-0">
						<div>
							<h6 class="mb-2">{{ __('Total Productos') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Product::count() }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Activo') }}</span></p>
						</div>
						<span class="avatar me-sm-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-box text-body"></i>
							</span>
						</span>
					</div>
					<hr class="d-none d-sm-block d-lg-none me-4">
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-3 pb-sm-0">
						<div>
							<h6 class="mb-2">{{ __('Productos Activos') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Product::where('status', true)->count() }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Disponibles') }}</span></p>
						</div>
						<span class="avatar p-2 me-lg-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-check text-body"></i>
							</span>
						</span>
					</div>
					<hr class="d-none d-sm-block d-lg-none">
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start border-end pb-3 pb-sm-0 card-widget-3">
						<div>
							<h6 class="mb-2">{{ __('Categorías') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Category::where('module_id', \App\Models\Module::where('key', 'products')->first()?->id ?? 0)->count() }}</h4>
							<p class="mb-0 text-muted">{{ __('Total') }}</p>
						</div>
						<span class="avatar p-2 me-sm-4">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-category text-body"></i>
							</span>
						</span>
					</div>
				</div>
				<div class="col-sm-6 col-lg-3">
					<div class="d-flex justify-content-between align-items-start">
						<div>
							<h6 class="mb-2">{{ __('WhatsApp Habilitado') }}</h6>
							<h4 class="mb-2">{{ \App\Models\Product::where('whatsapp_enabled', true)->count() }}</h4>
							<p class="mb-0"><span class="text-muted">{{ __('Productos') }}</span></p>
						</div>
						<span class="avatar p-2">
							<span class="avatar-initial bg-label-secondary rounded">
								<i class="ti-md ti ti-brand-whatsapp text-body"></i>
							</span>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Product List Table -->
<div class="card">
	<div class="card-body">
		{{ $dataTable->table() }}
	</div>
</div>

@endsection

@push('scripts')
{{ $dataTable->scripts() }}
@endpush

