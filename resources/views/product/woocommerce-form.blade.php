@extends('layouts/layoutMaster')

@section('title', $product ? __('Edit product') : __('Create product'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

@section('page-script')
    <script>
        function initProductEditors() {
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
                    placeholder: '{{ __('Brief summary for listings and search.') }}'
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
                        placeholder: '{{ __('Full product description.') }}'
                    });
                    var descContent = document.querySelector('#description').value;
                    if (descContent && descContent.trim() !== '' && descContent.trim() !== '<p><br></p>') {
                        descEditor.root.innerHTML = descContent;
                    }
                } catch (e) {
                    console.warn('Quill description init:', e);
                }

                var form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        if (shortDescEditor) document.querySelector('#short_description').value = shortDescEditor.root.innerHTML;
                        if (descEditor) document.querySelector('#description').value = descEditor.root.innerHTML;
                    }, { once: true });
                }
            }, 50);
            return true;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (!initProductEditors()) {
                    window.addEventListener('load', function tryAgain() {
                        initProductEditors();
                    });
                }
            });
        } else {
            if (!initProductEditors()) {
                window.addEventListener('load', initProductEditors);
            }
        }
    </script>
@endsection

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

                <div class="col-md-6">
                    <x-input-general id="regular_price" label="{{ __('Regular price') }}"
                        value="{{ old('regular_price', optional($product)['regular_price'] ?? optional($product)['price'] ?? '') }}" />
                    <div class="form-text">{{ __('Normal list price before any discount.') }}</div>
                </div>
                <div class="col-md-6">
                    <x-input-general id="sale_price" label="{{ __('Sale price') }}"
                        value="{{ old('sale_price', optional($product)['sale_price'] ?? '') }}" />
                    <div class="form-text">{{ __('Discounted price when on sale. Leave empty if not on sale.') }}</div>
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
                    <label class="form-label d-block mb-2" for="short_description">{{ __('Short description') }}</label>
                    <div id="short-description-toolbar" class="border rounded-top">
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-link"></button>
                        </span>
                    </div>
                    <div id="short-description-editor" class="border border-top-0 rounded-bottom" style="height: 100px; background: white;"></div>
                    <input type="hidden" id="short_description" name="short_description" value="{{ old('short_description', optional($product)['short_description'] ?? '') }}">
                    @error('short_description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mt-4 pt-3 border-top">
                    <h6 class="form-label mb-2" for="description">{{ __('Description') }}</h6>
                    <div id="description-toolbar" class="border rounded-top">
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-strike"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-header" value="1"></button>
                            <button class="ql-header" value="2"></button>
                            <button class="ql-blockquote"></button>
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                            <button class="ql-link"></button>
                            <button class="ql-image"></button>
                        </span>
                    </div>
                    <div id="description-editor" class="border border-top-0 rounded-bottom bg-white" style="height: 220px;"></div>
                    <input type="hidden" id="description" name="description" value="{{ old('description', optional($product)['description'] ?? '') }}">
                    @error('description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
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
