@extends('layouts/layoutMaster')

@section('title', $product ? __('Edit product') : __('Create product'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Products') }}/</span> {{ $product ? __('Edit') : __('Create') }}</h4>
            <p class="text-muted">{{ __('Products from your WooCommerce store') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('product.index') }}" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">{{ $product ? __('Edit product') : __('Create product') }}</h5>
        <form class="card-body" action="{{ $product ? route('product.update', $product['id']) : route('product.store') }}" method="POST">
            @csrf
            @if ($product)
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
                        value="{{ old('name', optional($product)['name'] ?? '') }}" />
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="publish" {{ old('status', optional($product)['status'] ?? 'publish') === 'publish' ? 'selected' : '' }}>{{ __('Published') }}</option>
                        <option value="draft" {{ old('status', optional($product)['status'] ?? '') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                        <option value="pending" {{ old('status', optional($product)['status'] ?? '') === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <x-input-general id="price" label="{{ __('Price') }}"
                        value="{{ old('price', optional($product)['price'] ?? '') }}" />
                </div>
                <div class="col-md-4">
                    <x-input-general id="regular_price" label="{{ __('Regular price') }}"
                        value="{{ old('regular_price', optional($product)['regular_price'] ?? '') }}" />
                </div>
                <div class="col-md-4">
                    <x-input-general id="sale_price" label="{{ __('Sale price') }}"
                        value="{{ old('sale_price', optional($product)['sale_price'] ?? '') }}" />
                </div>

                <div class="col-md-6">
                    <label for="stock_status" class="form-label">{{ __('Stock status') }}</label>
                    <select id="stock_status" name="stock_status" class="form-select @error('stock_status') is-invalid @enderror">
                        <option value="instock" {{ old('stock_status', optional($product)['stock_status'] ?? 'instock') === 'instock' ? 'selected' : '' }}>{{ __('In stock') }}</option>
                        <option value="outofstock" {{ old('stock_status', optional($product)['stock_status'] ?? '') === 'outofstock' ? 'selected' : '' }}>{{ __('Out of stock') }}</option>
                    </select>
                    @error('stock_status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="manage_stock" class="form-label">{{ __('Manage stock') }}</label>
                    <select id="manage_stock" name="manage_stock" class="form-select @error('manage_stock') is-invalid @enderror">
                        @php $manageStock = old('manage_stock', isset(optional($product)['manage_stock']) && (optional($product)['manage_stock'] ?? false) ? '1' : '0'); @endphp
                        <option value="0" {{ $manageStock === '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                        <option value="1" {{ $manageStock === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                    </select>
                    @error('manage_stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <x-input-general id="stock_quantity" label="{{ __('Stock quantity') }}"
                        value="{{ old('stock_quantity', optional($product)['stock_quantity'] ?? '') }}" />
                </div>

                <div class="col-12">
                    <x-input-textarea id="short_description" label="{{ __('Short description') }}" rows="2"
                        value="{{ old('short_description', optional($product)['short_description'] ?? '') }}" />
                </div>
                <div class="col-12">
                    <x-input-textarea id="description" label="{{ __('Description') }}" rows="5"
                        value="{{ old('description', optional($product)['description'] ?? '') }}" />
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Save') }}</button>
                <a href="{{ route('product.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
