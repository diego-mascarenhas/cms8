@extends('layouts/layoutMaster')

@section('title', 'Orden #' . $order->order_number)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Órdenes') }}/</span> #{{ $order->order_number }}</h4>
		<p class="text-muted">{{ __('Detalles de la Orden') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3">
		@can('order.edit')
		<a href="{{ route('order.edit', $order->id) }}" class="btn btn-primary waves-effect waves-light">
			<i class="ti ti-edit me-1"></i>{{ __('Editar Orden') }}
		</a>
		@endcan
		@can('order.destroy')
		<button type="button" class="btn btn-danger" onclick="deleteOrder({{ $order->id }})">
			<i class="ti ti-trash me-1"></i>{{ __('Eliminar Orden') }}
		</button>
		@endcan
	</div>
</div>

<div class="row">
	<!-- Order Information -->
	<div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
		<!-- Order Card -->
		<div class="card mb-4">
			<div class="card-body">
				<div class="user-avatar-section">
					<div class="d-flex align-items-center flex-column">
						@if($order->contact)
							<img class="img-fluid rounded-circle mb-3 pt-1 mt-4"
								src="{{ \App\Helpers\AvatarHelper::generate($order->contact->name, 100) }}"
								height="100"
								width="100"
								alt="{{ $order->contact->name }}" />
							<div class="user-info text-center">
								<h4 class="mb-2">{{ $order->contact->name }}</h4>
								<span class="badge bg-label-secondary mt-1">{{ $order->contact->email }}</span>
							</div>
						@else
							<div class="avatar avatar-xl mb-3">
								<span class="avatar-initial rounded-circle bg-label-secondary">
									<i class="ti ti-user ti-lg"></i>
								</span>
							</div>
							<div class="user-info text-center">
								<h4 class="mb-2">{{ __('Sin Cliente') }}</h4>
							</div>
						@endif
					</div>
				</div>
				
				<div class="d-flex justify-content-around flex-wrap mt-3 pt-3 pb-4 border-bottom">
					<div class="d-flex align-items-start me-4 mt-3 gap-2">
						<span class="badge bg-label-primary p-2 rounded">
							<i class='ti ti-currency-dollar ti-sm'></i>
						</span>
						<div>
							<p class="mb-0 fw-medium" style="line-height: 1.2;">
								{{ $order->currency ? $order->currency->symbol : '$' }}{{ number_format($order->total_amount, 2) }}
							</p>
							<small style="line-height: 1.2;">{{ __('Total') }}</small>
						</div>
					</div>
					<div class="d-flex align-items-start mt-3 gap-2">
						<span class="badge {{ str_replace('bg-label-', 'bg-label-', $order->payment_status_badge) }} p-2 rounded">
							<i class='ti ti-wallet ti-sm'></i>
						</span>
						<div>
							<p class="mb-0 fw-medium" style="line-height: 1.2;">
								{{ $order->payment_status_label }}
							</p>
							<small style="line-height: 1.2;">{{ __('Pago') }}</small>
						</div>
					</div>
				</div>
				
				<div class="mt-4 info-container">
					<ul class="list-unstyled">
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Estado de Pago') }}:</span>
							<span class="badge {{ $order->payment_status_badge }}">
								{{ $order->payment_status_label }}
							</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Estado de Entrega') }}:</span>
							<span class="badge {{ $order->delivery_status_badge }}">
								{{ $order->delivery_status_label }}
							</span>
						</li>
						@if($order->legacy_estado_label)
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Estado legacy (CMS)') }}:</span>
							<span class="badge {{ $order->legacy_estado_badge }}">
								{{ $order->legacy_estado_label }}
								@if($order->legacy_estado_code !== null)
									<span class="opacity-75">({{ $order->legacy_estado_code }})</span>
								@endif
							</span>
						</li>
						@endif
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Total') }}:</span>
							<span>{{ $order->currency ? $order->currency->symbol : '$' }}{{ number_format($order->total_amount, 2) }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Creada') }}:</span>
							<span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
						</li>
						<li class="mb-2 pt-1">
							<span class="fw-medium me-1">{{ __('Actualizada') }}:</span>
							<span>{{ $order->updated_at->format('d/m/Y H:i') }}</span>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Order Details -->
	<div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
		<!-- Order Information Card -->
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between">
				<h5 class="card-title mb-0">{{ __('Información de la Orden') }}</h5>
				<span class="badge bg-label-primary">{{ __('Orden') }} #{{ $order->order_number }}</span>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Cliente') }}</h6>
						<p class="mb-0">{{ $order->contact->name ?? '-' }}</p>
						@if($order->contact && $order->contact->email)
							<small class="text-muted">{{ $order->contact->email }}</small>
						@endif
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Equipo') }}</h6>
						<p class="mb-0">{{ $order->team->name ?? '-' }}</p>
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('Moneda') }}</h6>
						<p class="mb-0">{{ $order->currency ? $order->currency->name . ' (' . $order->currency->symbol . ')' : '-' }}</p>
					</div>
					<div class="col-md-6 mb-3">
						<h6 class="text-muted">{{ __('ID de la Orden') }}</h6>
						<p class="mb-0">#{{ $order->id }}</p>
					</div>
				</div>
			</div>
		</div>

		@if(!empty($order->metadata['items']) && is_array($order->metadata['items']))
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="card-title mb-0">{{ __('Productos') }}</h5>
				@if(!empty($order->metadata['source']) && $order->metadata['source'] === 'whatsapp')
					<span class="badge bg-label-success">{{ __('WhatsApp') }}</span>
				@endif
			</div>
			<div class="card-body p-0">
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
							@foreach($order->metadata['items'] as $line)
							<tr>
								<td class="ps-4">
									@if(!empty($line['product_id']))
										<span class="fw-medium">{{ $line['name'] ?? '—' }}</span>
										<small class="text-muted d-block">ID {{ $line['product_id'] }}</small>
									@else
										<span class="fw-medium">{{ $line['name'] ?? '—' }}</span>
									@endif
								</td>
								<td>{{ $line['category_name'] ?? '—' }}</td>
								<td class="text-end">{{ $order->currency ? $order->currency->symbol : '$' }}{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
								<td class="text-end">{{ (int) ($line['quantity'] ?? 0) }}</td>
								<td class="text-end pe-4">{{ $order->currency ? $order->currency->symbol : '$' }}{{ number_format((float) ($line['line_total'] ?? 0), 2) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
		@endif

		<!-- Order Notes Card -->
		@if($order->notes)
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Notas') }}</h5>
			</div>
			<div class="card-body">
				<p class="mb-0">{{ $order->notes }}</p>
			</div>
		</div>
		@endif

		<!-- Order Status Timeline -->
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="card-title mb-0">{{ __('Estado de la Orden') }}</h5>
			</div>
			<div class="card-body">
				<ul class="timeline mb-0">
					<li class="timeline-item timeline-item-transparent">
						<span class="timeline-point timeline-point-{{ $order->payment_status == 'paid' ? 'success' : 'warning' }}"></span>
						<div class="timeline-event">
							<div class="timeline-header mb-1">
								<h6 class="mb-0">{{ __('Estado de Pago') }}</h6>
								<small class="text-muted">{{ $order->updated_at->diffForHumans() }}</small>
							</div>
							<p class="mb-2">
								<span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span>
							</p>
						</div>
					</li>
					<li class="timeline-item timeline-item-transparent">
						<span class="timeline-point timeline-point-{{ $order->delivery_status == 'delivered' ? 'success' : 'info' }}"></span>
						<div class="timeline-event">
							<div class="timeline-header mb-1">
								<h6 class="mb-0">{{ __('Estado de Entrega') }}</h6>
								<small class="text-muted">{{ $order->updated_at->diffForHumans() }}</small>
							</div>
							<p class="mb-0">
								<span class="badge {{ $order->delivery_status_badge }}">{{ $order->delivery_status_label }}</span>
							</p>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script>
function deleteOrder(orderId) {
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
			form.action = `/order/${orderId}`;

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

