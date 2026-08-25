@extends('layouts/layoutMaster')

@section('title', __('Carritos abiertos'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Órdenes') }}/</span> {{ __('Carritos abiertos') }}</h4>
		<p class="text-muted">{{ __('Productos sumados que todavía no se confirmaron como pedido') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
		<a href="{{ route('order.index') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>{{ __('Órdenes') }}
		</a>
	</div>
</div>

<div class="card">
	<div class="card-datatable table-responsive">
		{{ $dataTable->table(['class' => 'datatables-open-carts table table-hover']) }}
	</div>
</div>
@endsection

@push('scripts')
{{ $dataTable->scripts() }}
@endpush
