@extends('layouts/layoutMaster')

@section('title', $product->name)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Productos') }}/</span> {{ $product->name }}</h4>
		<p class="text-muted">{{ __('Detalles del Producto') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		@can('update', $product)
		<a href="{{ route('product.edit', $product->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-edit me-1"></i>{{ __('Editar Producto') }}
		</a>
		@endcan
		@can('delete', $product)
		<button type="button" class="btn btn-danger" onclick="deleteProduct({{ $product->id }})">
			<i class="ti ti-trash me-1"></i>{{ __('Eliminar Producto') }}
		</button>
		@endcan
	</div>
</div>

<div class="row">
	<!-- Product Information -->
	<div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
		<!-- Product Card -->
		<div class="card mb-4">
			<div class="card-body">
				<div class="user-avatar-section">
					<div class="d-flex align-items-center flex-column">
						<img class="img-fluid rounded mb-3 pt-1 mt-4"
							src="{{ $product->image ?? asset('assets/img/ecommerce-images/product-1.png') }}"
							height="200"
							width="200"
							alt="{{ $product->name }}" />
						<div class="user-info text-center">
							<h4 class="mb-2">{{ $product->name }}</h4>
							<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Store') }}:</span>
							<span>{{ $product->store?->name ?? '—' }}</span>
						</li>
						@if($product->category)
								<span class="badge bg-label-info mt-1">{{ $product->category->name }}</span>
							@endif
						</div>
					</div>
				</div>

				<div class="d-flex justify-content-around flex-wrap mt-3 pt-3 pb-4 border-bottom">
					<div class="d-flex align-items-start me-4 mt-3 gap-2">
						<span class="badge bg-label-primary p-2 rounded">
							<i class='ti ti-currency-dollar ti-sm'></i>
						</span>
						<div>
							<p class="mb-0 fw-medium" style="line-height: 1.2;">
								@if($product->isOnSale())
									<span class="text-muted text-decoration-line-through">{{ $product->currency ? $product->currency->symbol : '$' }}{{ number_format((float) $product->price, 2) }}</span>
									<span class="ms-1">{{ $product->currency ? $product->currency->symbol : '$' }}{{ number_format($product->currentSellingPrice(), 2) }}</span>
								@else
									{{ $product->currency ? $product->currency->symbol : '$' }}{{ number_format($product->currentSellingPrice(), 2) }}
								@endif
							</p>
							<small style="line-height: 1.2;">{{ __('Precio') }}</small>
						</div>
					</div>
					<div class="d-flex align-items-start mt-3 gap-2">
						<span class="badge bg-label-{{ $product->status ? 'success' : 'secondary' }} p-2 rounded">
							<i class='ti ti-{{ $product->status ? 'check' : 'x' }} ti-sm'></i>
						</span>
						<div>
							<p class="mb-0 fw-medium" style="line-height: 1.2;">
								{{ $product->catalog_status?->label() ?? ($product->status ? __('Published') : __('Draft')) }}
							</p>
							<small style="line-height: 1.2;">{{ __('Estado') }}</small>
						</div>
					</div>
				</div>

				<div class="mt-4 info-container">
					<ul class="list-unstyled">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Code') }}:</span>
							<span>{{ $product->code ?? '—' }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Estado') }}:</span>
							<span class="badge bg-label-{{ $product->status ? 'success' : 'secondary' }}">
								{{ $product->catalog_status?->label() ?? ($product->status ? __('Published') : __('Draft')) }}
							</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Stock status') }}:</span>
							<span>{{ $product->stock_status?->label() ?? '—' }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Manage stock') }}:</span>
							<span>{{ $product->manage_stock ? __('Yes') : __('No') }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Sizes') }}:</span>
							<span>{{ !empty($product->size_options) ? implode(', ', $product->size_options) : '—' }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Colors') }}:</span>
							<span>{{ !empty($product->color_options) ? implode(', ', $product->color_options) : '—' }}</span>
						</li>
						@if($product->manage_stock)
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Stock quantity') }}:</span>
							<span>{{ $product->stock_quantity ?? '—' }}</span>
						</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Store') }}:</span>
							<span>{{ $product->store?->name ?? '—' }}</span>
						</li>
						@if($product->category)
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">{{ __('Categoría') }}:</span>
								<span>{{ $product->category->name }}</span>
							</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Regular price') }}:</span>
							<span>{{ $product->currency ? $product->currency->symbol : '$' }}{{ number_format((float) $product->price, 2) }}</span>
						</li>
						@if($product->sale_price)
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Sale price') }}:</span>
							<span>{{ $product->currency ? $product->currency->symbol : '$' }}{{ number_format((float) $product->sale_price, 2) }}</span>
						</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('WhatsApp Habilitado') }}:</span>
							<span class="badge bg-label-{{ $product->whatsapp_enabled ? 'success' : 'secondary' }}">
								{{ $product->whatsapp_enabled ? __('Sí') : __('No') }}
							</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Creado') }}:</span>
							<span>{{ $product->created_at->format('d/m/Y H:i') }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Actualizado') }}:</span>
							<span>{{ $product->updated_at->format('d/m/Y H:i') }}</span>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- Product Details -->
	<div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
		<!-- Product Description Card -->
		@if($product->short_description)
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Short description') }}</h5>
			</div>
			<div class="card-body">
				<div class="mb-0">{!! $product->short_description !!}</div>
			</div>
		</div>
		@endif
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Descripción') }}</h5>
			</div>
			<div class="card-body">
				@if($product->description)
					<div class="mb-0">{!! $product->description !!}</div>
				@else
					<p class="mb-0">{{ __('Sin descripción disponible') }}</p>
				@endif
			</div>
		</div>

		<!-- Additional Information -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Información Adicional') }}</h5>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Equipo') }}</h6>
						<p class="mb-0">{{ $product->team->name ?? '-' }}</p>
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Moneda') }}</h6>
						<p class="mb-0">{{ $product->currency ? $product->currency->name . ' (' . $product->currency->symbol . ')' : '-' }}</p>
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Integración WhatsApp') }}</h6>
						<p class="mb-0">
							@if($product->whatsapp_enabled)
								<span class="badge bg-success">{{ __('Habilitado') }}</span>
							@else
								<span class="badge bg-secondary">{{ __('Deshabilitado') }}</span>
							@endif
						</p>
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('ID del Producto') }}</h6>
						<p class="mb-0">#{{ $product->id }}</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
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
			form.action = `/product/${productId}`;

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
@endsection

