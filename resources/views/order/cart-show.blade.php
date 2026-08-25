@extends('layouts/layoutMaster')

@section('title', __('Carrito') . ' — ' . $cart['customer'])

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Órdenes') }}/{{ __('Carritos abiertos') }}/</span> {{ $cart['customer'] }}</h4>
		<p class="text-muted">{{ __('Detalle y edición de productos del carrito') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		<a href="{{ route('order.carts') }}" class="btn btn-label-secondary waves-effect waves-light">
			<i class="ti ti-arrow-left me-1"></i>{{ __('Volver') }}
		</a>
		@if (! empty($cart['chat_url']))
			<a href="{{ $cart['chat_url'] }}" class="btn btn-info waves-effect waves-light">
				<i class="ti ti-message-chatbot me-1"></i>{{ __('Chat') }}
			</a>
		@endif
		@if ($canEdit)
			<form action="{{ route('order.carts.destroy', $cart['id']) }}" method="POST" onsubmit="return confirm('{{ __('¿Vaciar y eliminar este carrito?') }}');">
				@csrf
				@method('DELETE')
				<button type="submit" class="btn btn-danger waves-effect waves-light">
					<i class="ti ti-trash me-1"></i>{{ __('Eliminar carrito') }}
				</button>
			</form>
		@endif
	</div>
</div>

@if (session('success'))
	<div class="alert alert-success alert-dismissible" role="alert">
		{{ session('success') }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
@endif

@if ($errors->any())
	<div class="alert alert-danger alert-dismissible" role="alert">
		{{ $errors->first() }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>
@endif

<div class="row">
	<div class="col-xl-4 col-lg-5 col-md-5">
		<div class="card mb-4">
			<div class="card-body">
				<div class="info-container">
					<ul class="list-unstyled mb-0">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Cliente') }}:</span>
							<span>{{ $cart['customer'] }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Canal') }}:</span>
							<span>{{ $cart['channel'] }}</span>
						</li>
						@if ($cart['phone'] !== '')
							<li class="mb-2 pt-1">
								<span class="fw-medium me-1">{{ __('Teléfono') }}:</span>
								<span>{{ $cart['phone'] }}</span>
							</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Items') }}:</span>
							<span>{{ $cart['quantity'] }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Total') }}:</span>
							<span>${{ number_format((float) $cart['total'], 2) }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Actualizado') }}:</span>
							<span>{{ $cart['updated_at'] }}</span>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-8 col-lg-7 col-md-7">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Productos') }}</h5>
			</div>
			@if (count($cart['items']) === 0)
				<div class="card-body">
					<p class="text-muted mb-0">{{ __('Este carrito no tiene productos.') }}</p>
				</div>
			@elseif ($canEdit)
				<form action="{{ route('order.carts.update', $cart['id']) }}" method="POST">
					@csrf
					@method('PUT')
					<div class="table-responsive">
						<table class="table table-striped mb-0">
							<thead>
								<tr>
									<th class="ps-4">{{ __('Producto') }}</th>
									<th>{{ __('Categoría') }}</th>
									<th class="text-end">{{ __('Precio unit.') }}</th>
									<th class="text-center" style="width: 7rem;">{{ __('Cant.') }}</th>
									<th class="text-end">{{ __('Subtotal') }}</th>
									<th class="text-center pe-4" style="width: 4rem;"></th>
								</tr>
							</thead>
							<tbody>
								@foreach ($cart['items'] as $index => $item)
									<tr>
										<td class="ps-4">
											<input type="hidden" name="items[{{ $index }}][id]" value="{{ $item['id'] }}">
											<span class="fw-medium">{{ $item['name'] }}</span>
											<small class="text-muted d-block">ID {{ $item['product_id'] }}</small>
										</td>
										<td>{{ $item['category_name'] ?: '—' }}</td>
										<td class="text-end">${{ number_format((float) $item['unit_price'], 2) }}</td>
										<td>
											<input
												type="number"
												class="form-control form-control-sm @error('items.'.$index.'.quantity') is-invalid @enderror"
												name="items[{{ $index }}][quantity]"
												value="{{ old('items.'.$index.'.quantity', $item['quantity']) }}"
												min="0"
												max="500"
												required
											>
											@error('items.'.$index.'.quantity')
												<div class="invalid-feedback">{{ $message }}</div>
											@enderror
										</td>
										<td class="text-end">${{ number_format((float) $item['line_total'], 2) }}</td>
										<td class="text-center pe-4">
											<button
												type="submit"
												form="remove-item-{{ $item['id'] }}"
												class="btn btn-icon btn-sm text-danger"
												title="{{ __('Quitar') }}"
												onclick="return confirm('{{ __('¿Quitar este producto?') }}');"
											>
												<i class="ti ti-trash ti-sm"></i>
											</button>
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					<div class="card-body d-flex justify-content-end">
						<button type="submit" class="btn btn-primary">{{ __('Guardar cambios') }}</button>
					</div>
				</form>
				@foreach ($cart['items'] as $item)
					<form id="remove-item-{{ $item['id'] }}" action="{{ route('order.carts.items.destroy', [$cart['id'], $item['id']]) }}" method="POST" class="d-none">
						@csrf
						@method('DELETE')
					</form>
				@endforeach
			@else
				<div class="table-responsive">
					<table class="table table-striped mb-0">
						<thead>
							<tr>
								<th class="ps-4">{{ __('Producto') }}</th>
								<th>{{ __('Categoría') }}</th>
								<th class="text-end">{{ __('Precio unit.') }}</th>
								<th class="text-end">{{ __('Cant.') }}</th>
								<th class="text-end pe-4">{{ __('Subtotal') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach ($cart['items'] as $item)
								<tr>
									<td class="ps-4">
										<span class="fw-medium">{{ $item['name'] }}</span>
										<small class="text-muted d-block">ID {{ $item['product_id'] }}</small>
									</td>
									<td>{{ $item['category_name'] ?: '—' }}</td>
									<td class="text-end">${{ number_format((float) $item['unit_price'], 2) }}</td>
									<td class="text-end">{{ $item['quantity'] }}</td>
									<td class="text-end pe-4">${{ number_format((float) $item['line_total'], 2) }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif
		</div>
	</div>
</div>
@endsection
