@extends('layouts/layoutMaster')

@section('title', __('Import products'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Products') }}/</span> {{ __('Import') }}</h4>
		<p class="text-muted mb-0">{{ __('Upload a CSV to create or update your catalogue. Existing products are matched by code.') }}</p>
	</div>
	<div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
		<a href="{{ route('product.import.template') }}" class="btn btn-label-secondary">
			<i class="ti ti-download me-1"></i>{{ __('Download template') }}
		</a>
		<a href="{{ route('product.import.sample') }}" class="btn btn-label-secondary">
			<i class="ti ti-photo me-1"></i>{{ __('Demo catalogue (:count products)', ['count' => $demoProductCount]) }}
		</a>
		<a href="{{ route('product.index') }}" class="btn btn-label-secondary">
			<i class="ti ti-arrow-left me-1"></i>{{ __('Back to products') }}
		</a>
	</div>
</div>

@if (session('error'))
<div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
	<ul class="mb-0">
		@foreach ($errors->all() as $error)
		<li>{{ $error }}</li>
		@endforeach
	</ul>
</div>
@endif

@if (session('import_errors'))
<div class="alert alert-warning">
	<h6 class="alert-heading mb-2">{{ __('Rows that could not be imported') }}</h6>
	<ul class="mb-0">
		@foreach (session('import_errors') as $importError)
		<li>{{ $importError }}</li>
		@endforeach
	</ul>
</div>
@endif

<div class="row">
	<div class="col-lg-6">
		<div class="card mb-4">
			<h5 class="card-header">{{ __('Upload file') }}</h5>
			<form class="card-body" action="{{ route('product.import.store') }}" method="POST" enctype="multipart/form-data">
				@csrf
				<div class="mb-3">
					<label for="file" class="form-label">{{ __('CSV file') }}</label>
					<input class="form-control" type="file" id="file" name="file" accept=".csv, text/csv, text/plain, application/csv" required>
					<div class="form-text">{{ __('Comma or semicolon separated, UTF-8, up to 5 MB.') }}</div>
				</div>
				<button type="submit" class="btn btn-primary">
					<i class="ti ti-upload me-1"></i>{{ __('Import products') }}
				</button>
			</form>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card mb-4">
			<h5 class="card-header">{{ __('Expected columns') }}</h5>
			<div class="card-body">
				<p class="mb-2"><span class="badge bg-label-danger">{{ __('Required') }}</span></p>
				<p class="mb-4"><code>{{ implode(', ', $requiredColumns) }}</code></p>

				<p class="mb-2"><span class="badge bg-label-secondary">{{ __('Optional') }}</span></p>
				<p class="mb-4"><code>{{ implode(', ', $optionalColumns) }}</code></p>

				<ul class="mb-0 ps-3 text-muted small">
					<li>{{ __('code identifies the product: an existing code updates the product, a new one creates it.') }}</li>
					<li>{{ __('category is created automatically when the name does not exist yet.') }}</li>
					<li>{{ __('currency uses the ISO code (ARS, USD, EUR). store empty or “todas” means every branch; several names go separated by |.') }}</li>
					<li>{{ __('catalog_status accepts publish, draft, pending or private; only published products are offered by the assistant.') }}</li>
					<li>{{ __('whatsapp_enabled defaults to 1 so the product is sellable from the chat.') }}</li>
					<li>{{ __('brand is created automatically when the name does not exist yet.') }}</li>
					<li>{{ __('size_options, color_options and flavor_options (gustos / toppings) accept several values separated by |. Shop builds one variant per combination.') }}</li>
					<li>{{ __('assortment_size is the combo size (e.g. 12 for a dozen). Use it with flavor_options.') }}</li>
				</ul>
			</div>
		</div>
	</div>
</div>
@endsection
