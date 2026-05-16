@extends('layouts/layoutMaster')

@section('title', __('Productos'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
{{-- Commerce hero banner (product.commerce-hero): hidden for now; enable when needed --}}

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3">{{ __('Products') }}</h4>
		<p class="text-muted mb-0">{{ __('Manage your product catalogue in Humano. Works standalone without connecting a store.') }}</p>
	</div>
	<div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-md-0">
		@can('create', \App\Models\Product::class)
		<a href="{{ route('product.create') }}" class="btn btn-primary">
			<i class="ti ti-plus me-1"></i>{{ __('Add product') }}
		</a>
		@endcan
		@if (!empty($wordpressConfigured))
		<form action="{{ route('wordpress.sync') }}" method="POST" class="d-inline">
			@csrf
			<button type="submit" class="btn btn-primary">
				<i class="ti ti-refresh me-1"></i>{{ __('Sync content with assistant') }}
			</button>
		</form>
		@endif
		@if (!empty($lastSyncedAt))
		<span class="text-muted small">{{ __('Last sync') }}: {{ $lastSyncedAt->diffForHumans() }}</span>
		@endif
	</div>
</div>

@if (session('success'))
<div class="alert alert-success alert-dismissible mb-3" role="alert">
	{{ session('success') }}
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if (session('error'))
<div class="alert alert-danger alert-dismissible mb-3" role="alert">
	{{ session('error') }}
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

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
		<div id="product-table-custom-filters" class="d-none flex-grow-1">
			<div class="d-flex align-items-center gap-2 flex-nowrap w-100">
				<select id="filter_store_id" class="form-select form-select-sm" style="width: 170px;">
					<option value="">{{ __('Sucursales') }}</option>
					@foreach(($stores ?? collect()) as $store)
						<option value="{{ $store->id }}">{{ $store->name }}</option>
					@endforeach
				</select>
				<select id="filter_category_id" class="form-select form-select-sm" style="width: 190px;">
					<option value="">{{ __('Categorías') }}</option>
					@foreach(($categories ?? collect()) as $category)
						<option value="{{ $category->id }}">{{ $category->name }}</option>
					@endforeach
				</select>
			</div>
		</div>
		{{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
	</div>
</div>

@endsection

@push('scripts')
{{ $dataTable->scripts() }}
<script>
function reloadProductTable() {
	const table = window.LaravelDataTables?.['product-table'];
	if (table) {
		table.ajax.reload();
	}
}

document.addEventListener('DOMContentLoaded', function () {
	const customFilters = $('#product-table-custom-filters');
	const attachFiltersToSearch = function () {
		const searchContainer = $('#product-table_filter');
		if (searchContainer.length && customFilters.length && !searchContainer.find('#filter_store_id').length) {
			searchContainer.addClass('d-flex align-items-center gap-2 flex-nowrap justify-content-between w-100');
			searchContainer.prepend(customFilters.removeClass('d-none'));
			searchContainer.find('label').addClass('mb-0 d-flex align-items-center gap-2 ms-auto');
			searchContainer.find('label input').addClass('form-control-sm').css('width', '160px');
		}
	};

	attachFiltersToSearch();
	setTimeout(attachFiltersToSearch, 250);

	$('#filter_store_id, #filter_category_id').on('change', function () {
		reloadProductTable();
	});
});

function deleteProduct(productId) {
	Swal.fire({
		title: '{{ __('¿Estás seguro?') }}',
		text: "{{ __('¡No podrás revertir esto!') }}",
		icon: 'warning',
		showCancelButton: true,
		buttonsStyling: false,
		customClass: {
			confirmButton: 'btn btn-danger me-2',
			cancelButton: 'btn btn-label-secondary'
		},
		confirmButtonText: '{{ __('Sí, eliminar') }}',
		cancelButtonText: '{{ __('Cancelar') }}',
		allowOutsideClick: false,
		allowEscapeKey: false
	}).then((result) => {
		if (result.isConfirmed) {
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '/product/' + productId;
			const csrfToken = document.createElement('input');
			csrfToken.type = 'hidden';
			csrfToken.name = '_token';
			csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			form.appendChild(csrfToken);
			const methodInput = document.createElement('input');
			methodInput.type = 'hidden';
			methodInput.name = '_method';
			methodInput.value = 'DELETE';
			form.appendChild(methodInput);
			document.body.appendChild(form);
			form.submit();
		}
	});
}
</script>
@endpush

