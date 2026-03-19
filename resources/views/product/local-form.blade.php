@extends('layouts/layoutMaster')

@php
	$isEdit = isset($product) && $product;
	$defaultCurrencyId = $defaultCurrencyId ?? ($currencies->first()?->id);
	$catalogStatusOld = old('catalog_status', $isEdit ? $product->catalog_status->value : 'publish');
	$stockStatusOld = old('stock_status', $isEdit ? $product->stock_status->value : 'instock');
	$manageStockOld = old('manage_stock', $isEdit ? ($product->manage_stock ? '1' : '0') : '0');
	$sizeOptionsOld = old('size_options', $isEdit ? implode(', ', $product->size_options ?? []) : '');
	$colorOptionsOld = old('color_options', $isEdit ? implode(', ', $product->color_options ?? []) : '');
@endphp

@section('title', $isEdit ? __('Edit product') : __('Create product'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

@section('page-script')
<script>
	function initLocalProductEditors() {
		if (typeof Quill === 'undefined') return false;
		var shortEl = document.querySelector('#short-description-editor');
		var descEl = document.querySelector('#description-editor');
		var shortToolbar = document.querySelector('#short-description-toolbar');
		var descToolbar = document.querySelector('#description-toolbar');
		if (!shortEl || !descEl || !shortToolbar || !descToolbar) return false;

		var shortDescEditor = null;
		var descEditor = null;
		try {
			shortDescEditor = new Quill(shortEl, {
				theme: 'snow',
				modules: { toolbar: shortToolbar },
				placeholder: @json(__('Brief summary for listings and search.'))
			});
			var shortDescContent = document.querySelector('#short_description').value;
			if (shortDescContent && shortDescContent.trim() !== '' && shortDescContent.trim() !== '<p><br></p>') {
				shortDescEditor.root.innerHTML = shortDescContent;
			}
		} catch (e) {
			console.warn('Quill short description init:', e);
		}

		setTimeout(function() {
			try {
				descEditor = new Quill(descEl, {
					theme: 'snow',
					modules: { toolbar: descToolbar },
					placeholder: @json(__('Full product description.'))
				});
				var descContent = document.querySelector('#description').value;
				if (descContent && descContent.trim() !== '' && descContent.trim() !== '<p><br></p>') {
					descEditor.root.innerHTML = descContent;
				}
			} catch (e) {
				console.warn('Quill description init:', e);
			}

			var form = document.querySelector('form.card-body');
			if (form) {
				form.addEventListener('submit', function() {
					if (shortDescEditor) document.querySelector('#short_description').value = shortDescEditor.root.innerHTML;
					if (descEditor) document.querySelector('#description').value = descEditor.root.innerHTML;
				}, { once: true });
			}
		}, 50);
		return true;
	}

	function syncManageStockUi() {
		var sel = document.getElementById('manage_stock');
		var qty = document.getElementById('stock_quantity');
		if (!sel || !qty) return;
		var on = sel.value === '1';
		qty.disabled = !on;
		if (!on) qty.value = '';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			if (!initLocalProductEditors()) {
				window.addEventListener('load', initLocalProductEditors);
			}
			syncManageStockUi();
			var ms = document.getElementById('manage_stock');
			if (ms) ms.addEventListener('change', syncManageStockUi);
		});
	} else {
		if (!initLocalProductEditors()) {
			window.addEventListener('load', initLocalProductEditors);
		}
		syncManageStockUi();
		var ms = document.getElementById('manage_stock');
		if (ms) ms.addEventListener('change', syncManageStockUi);
	}

	$(function() {
		if ($.fn.select2) {
			$('#currency_id').select2();
		}
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Products') }}/</span> {{ $isEdit ? __('Edit') : __('Create') }}</h4>
		<p class="text-muted">{{ __('Create and edit products stored in Humano. No external store connection required.') }}</p>
	</div>
	<div class="mt-3 mt-md-0">
		<a href="{{ route('product.index') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
	</div>
</div>

<div class="card">
	<h5 class="card-header">{{ $isEdit ? __('Edit product') : __('Create product') }}</h5>
	<form class="card-body" action="{{ $isEdit ? route('product.update', $product->id) : route('product.store') }}" method="POST">
		@csrf
		@if ($isEdit)
			@method('PUT')
		@endif

		@if (session('error'))
			<div class="alert alert-danger alert-dismissible" role="alert">
				{{ session('error') }}
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		@endif

		<div class="row g-3">
			<div class="col-md-8">
				<x-input-general id="name" label="{{ __('Name') }} (*)"
					value="{{ old('name', $isEdit ? $product->name : '') }}" />
			</div>
			<div class="col-md-4">
				<x-input-general id="code" label="{{ __('Code') }} (*)"
					value="{{ old('code', $isEdit ? $product->code : '') }}" />
			</div>
			<div class="col-md-4">
				<label for="catalog_status" class="form-label">{{ __('Status') }} (*)</label>
				<select id="catalog_status" name="catalog_status" class="form-select @error('catalog_status') is-invalid @enderror" required>
					<option value="publish" {{ $catalogStatusOld === 'publish' ? 'selected' : '' }}>{{ __('Published') }}</option>
					<option value="draft" {{ $catalogStatusOld === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
					<option value="pending" {{ $catalogStatusOld === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
					<option value="private" {{ $catalogStatusOld === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
				</select>
				@error('catalog_status')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-12">
				<label for="image" class="form-label">{{ __('Product image URL') }}</label>
				<input type="url" name="image" id="image" class="form-control @error('image') is-invalid @enderror"
					placeholder="https://"
					value="{{ old('image', $isEdit ? $product->image : '') }}" />
				<div class="form-text">{{ __('HTTPS link to the product photo (optional).') }}</div>
				@error('image')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<div class="form-group">
					<label for="price" class="form-label">{{ __('Regular price') }} (*)</label>
					<input type="number" step="0.01" min="0" name="price" id="price"
						class="form-control @error('price') is-invalid @enderror"
						value="{{ old('price', $isEdit ? $product->price : '') }}" required />
					<div class="form-text">{{ __('Normal list price before any discount.') }}</div>
					@error('price')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
					<label for="sale_price" class="form-label">{{ __('Sale price') }}</label>
					<input type="number" step="0.01" min="0" name="sale_price" id="sale_price"
						class="form-control @error('sale_price') is-invalid @enderror"
						value="{{ old('sale_price', $isEdit && $product->sale_price !== null ? $product->sale_price : '') }}" />
					<div class="form-text">{{ __('Discounted price when on sale. Leave empty if not on sale.') }}</div>
					@error('sale_price')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>

			<div class="col-md-6">
				<label for="stock_status" class="form-label">{{ __('Stock status') }} (*)</label>
				<select id="stock_status" name="stock_status" class="form-select @error('stock_status') is-invalid @enderror" required>
					<option value="instock" {{ $stockStatusOld === 'instock' ? 'selected' : '' }}>{{ __('In stock') }}</option>
					<option value="outofstock" {{ $stockStatusOld === 'outofstock' ? 'selected' : '' }}>{{ __('Out of stock') }}</option>
					<option value="onbackorder" {{ $stockStatusOld === 'onbackorder' ? 'selected' : '' }}>{{ __('On backorder') }}</option>
				</select>
				@error('stock_status')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-3">
				<label for="manage_stock" class="form-label">{{ __('Manage stock') }} (*)</label>
				<select id="manage_stock" name="manage_stock" class="form-select @error('manage_stock') is-invalid @enderror" required>
					<option value="0" {{ $manageStockOld === '0' ? 'selected' : '' }}>{{ __('No') }}</option>
					<option value="1" {{ $manageStockOld === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
				</select>
				@error('manage_stock')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-3">
				<label for="stock_quantity" class="form-label">{{ __('Stock quantity') }}</label>
				<input type="number" min="0" step="1" name="stock_quantity" id="stock_quantity"
					class="form-control @error('stock_quantity') is-invalid @enderror"
					value="{{ old('stock_quantity', $isEdit ? $product->stock_quantity : '') }}"
					{{ $manageStockOld === '0' ? 'disabled' : '' }} />
				@error('stock_quantity')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				@php
					$currencyOptions = $currencies->map(fn ($c) => ['id' => $c->id, 'name' => $c->code.' — '.$c->name])->values()->all();
				@endphp
				<x-input-select
					id="currency_id"
					label="{{ __('Currency') }} (*)"
					:options="$currencyOptions"
					:value="old('currency_id', $isEdit ? $product->currency_id : $defaultCurrencyId)"
					:placeholder="__('Select')"
					:required="true"
				/>
			</div>
			<div class="col-md-6">
				@php
					$storeOptions = ($stores ?? collect())->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()->all();
				@endphp
				<x-input-select
					id="store_id"
					label="{{ __('Store') }}"
					:options="$storeOptions"
					:value="old('store_id', $isEdit ? $product->store_id : ($defaultStoreId ?? ''))"
					:placeholder="__('Select')"
				/>
			</div>
			<div class="col-md-6">
				<x-module-categories-select
					id="category_id"
					label="{{ __('Category') }} (*)"
					moduleKey="products"
					:selected="old('category_id', $isEdit ? $product->category_id : '')"
					:allowEmpty="false"
				/>
			</div>

			<div class="col-md-6">
				<label for="size_options" class="form-label">{{ __('Sizes') }}</label>
				<input type="text" id="size_options" name="size_options" class="form-control @error('size_options') is-invalid @enderror" value="{{ $sizeOptionsOld }}" placeholder="S, M, L, XL" />
				<div class="form-text">{{ __('Comma separated values.') }}</div>
				@error('size_options')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-6">
				<label for="color_options" class="form-label">{{ __('Colors') }}</label>
				<input type="text" id="color_options" name="color_options" class="form-control @error('color_options') is-invalid @enderror" value="{{ $colorOptionsOld }}" placeholder="Negro, Blanco, Azul" />
				<div class="form-text">{{ __('Comma separated values.') }}</div>
				@error('color_options')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-12">
				<label class="form-label d-block mb-2" for="short_description">{{ __('Short description') }}</label>
				<div id="short-description-toolbar" class="border rounded-top">
					<span class="ql-formats">
						<button type="button" class="ql-bold"></button>
						<button type="button" class="ql-italic"></button>
						<button type="button" class="ql-underline"></button>
						<button type="button" class="ql-link"></button>
					</span>
				</div>
				<div id="short-description-editor" class="border border-top-0 rounded-bottom" style="height: 100px; background: white;"></div>
				<input type="hidden" id="short_description" name="short_description" value="{{ old('short_description', $isEdit ? $product->short_description : '') }}">
				@error('short_description')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-12 mt-4 pt-3 border-top">
				<h6 class="form-label mb-2">{{ __('Description') }}</h6>
				<div id="description-toolbar" class="border rounded-top">
					<span class="ql-formats">
						<button type="button" class="ql-bold"></button>
						<button type="button" class="ql-italic"></button>
						<button type="button" class="ql-underline"></button>
						<button type="button" class="ql-strike"></button>
					</span>
					<span class="ql-formats">
						<button type="button" class="ql-header" value="1"></button>
						<button type="button" class="ql-header" value="2"></button>
						<button type="button" class="ql-blockquote"></button>
						<button type="button" class="ql-list" value="ordered"></button>
						<button type="button" class="ql-list" value="bullet"></button>
						<button type="button" class="ql-link"></button>
						<button type="button" class="ql-image"></button>
					</span>
				</div>
				<div id="description-editor" class="border border-top-0 rounded-bottom bg-white" style="height: 220px;"></div>
				<input type="hidden" id="description" name="description" value="{{ old('description', $isEdit ? $product->description : '') }}">
				@error('description')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<label for="whatsapp_enabled" class="form-label">{{ __('WhatsApp') }} (*)</label>
				<select id="whatsapp_enabled" name="whatsapp_enabled" class="form-select @error('whatsapp_enabled') is-invalid @enderror" required>
					<option value="1" {{ (string) old('whatsapp_enabled', $isEdit ? ($product->whatsapp_enabled ? '1' : '0') : '1') === '1' ? 'selected' : '' }}>{{ __('Enabled') }}</option>
					<option value="0" {{ (string) old('whatsapp_enabled', $isEdit ? ($product->whatsapp_enabled ? '1' : '0') : '1') === '0' ? 'selected' : '' }}>{{ __('Disabled') }}</option>
				</select>
				@error('whatsapp_enabled')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
		</div>

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
			<a href="{{ route('product.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
		</div>
	</form>
</div>
@endsection
