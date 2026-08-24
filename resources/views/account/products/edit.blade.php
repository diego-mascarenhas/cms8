@extends('layouts/layoutMaster')

@section('title', 'Editar Producto')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
@endsection

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Productos/</span> Editar</h4>
            <p class="text-muted">Editar límites y metadata del producto</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <form action="{{ route('account.products.sync', $product->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info waves-effect waves-light">
                    <i class="ti ti-refresh me-1"></i>Sincronizar desde Stripe
                </button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <h5 class="card-header">{{ $product->name }}</h5>
        <form class="card-body" action="{{ route('account.products.update-and-sync', $product->id) }}" method="POST">
            @csrf
            @method('PUT')

            @php
                $hasStripeProduct = !empty($product->stripe_product);
            @endphp

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nombre (*)</label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="stripe_product">Stripe Product ID</label>
                    <input type="text" class="form-control" id="stripe_product" name="stripe_product" value="{{ old('stripe_product', $product->stripe_product) }}" placeholder="prod_xxxxx" {{ $hasStripeProduct ? 'readonly' : '' }}>
                    <small class="text-muted" style="font-size: 0.75rem;">
                        @if($hasStripeProduct)
                            El producto ya está asociado con Stripe. Para modificarlo, usa "Sincronizar desde Stripe".
                        @else
                            ID del producto en Stripe (para sincronizar productos existentes)
                        @endif
                    </small>
                </div>

                <div class="col-md-12">
                    <label class="form-label" for="description">Descripción</label>
                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="category">Categoría</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Seleccionar...</option>
                        <option value="hosting" {{ old('category', $product->category) === 'hosting' ? 'selected' : '' }}>Hosting</option>
                        <option value="web_cloud" {{ old('category', $product->category) === 'web_cloud' ? 'selected' : '' }}>Web Cloud</option>
                        <option value="vps" {{ old('category', $product->category) === 'vps' ? 'selected' : '' }}>VPS</option>
                        <option value="domain" {{ old('category', $product->category) === 'domain' ? 'selected' : '' }}>Domain</option>
                        <option value="backups" {{ old('category', $product->category) === 'backups' ? 'selected' : '' }}>Backups</option>
                        <option value="mailer" {{ old('category', $product->category) === 'mailer' ? 'selected' : '' }}>Mailer</option>
                        <option value="shop" {{ old('category', $product->category) === 'shop' ? 'selected' : '' }}>Shop</option>
                        <option value="ads" {{ old('category', $product->category) === 'ads' ? 'selected' : '' }}>Ads</option>
                        <option value="projects" {{ old('category', $product->category) === 'projects' ? 'selected' : '' }}>Projects</option>
                        <option value="affiliates" {{ old('category', $product->category) === 'affiliates' ? 'selected' : '' }}>Affiliates</option>
                        <option value="estimator" {{ old('category', $product->category) === 'estimator' ? 'selected' : '' }}>Estimator</option>
                        <option value="mentoring" {{ old('category', $product->category) === 'mentoring' ? 'selected' : '' }}>Mentoring</option>
                        <option value="prospecting" {{ old('category', $product->category) === 'prospecting' ? 'selected' : '' }}>Prospectos</option>
                        <option value="support" {{ old('category', $product->category) === 'support' ? 'selected' : '' }}>Support</option>
                        <option value="whatsapp" {{ old('category', $product->category) === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="plan">Plan</label>
                    <input type="text" class="form-control" id="plan" name="plan" value="{{ old('plan', $product->plan) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="type">Tipo</label>
                    <input type="text" class="form-control" id="type" name="type" value="{{ old('type', $product->type) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="currency">Moneda (*)</label>
                    <select class="form-select" id="currency" name="currency" {{ $hasStripeProduct ? 'disabled' : 'required' }}>
                        <option value="usd" {{ old('currency', $product->currency) === 'usd' ? 'selected' : '' }}>USD</option>
                        <option value="eur" {{ old('currency', $product->currency) === 'eur' ? 'selected' : '' }}>EUR</option>
                        <option value="ars" {{ old('currency', $product->currency) === 'ars' ? 'selected' : '' }}>ARS</option>
                    </select>
                    @if($hasStripeProduct)
                        <input type="hidden" name="currency" value="{{ $product->currency }}">
                        <small class="text-muted">Edita el precio desde Stripe</small>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="unit_amount">Precio</label>
                    <input type="number" class="form-control" id="unit_amount" name="unit_amount" value="{{ old('unit_amount', $product->unit_amount ?? '') }}" step="0.01" min="0" {{ $hasStripeProduct ? 'disabled' : '' }}>
                    @if($hasStripeProduct)
                        <input type="hidden" name="unit_amount" value="{{ $product->unit_amount }}">
                        <small class="text-muted">Edita el precio desde Stripe</small>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="recurring_interval">Intervalo</label>
                    <select class="form-select" id="recurring_interval" name="recurring_interval" {{ $hasStripeProduct ? 'disabled' : '' }}>
                        <option value="">Seleccionar...</option>
                        <option value="day" {{ old('recurring_interval', $product->recurring_interval) === 'day' ? 'selected' : '' }}>Diario</option>
                        <option value="week" {{ old('recurring_interval', $product->recurring_interval) === 'week' ? 'selected' : '' }}>Semanal</option>
                        <option value="month" {{ old('recurring_interval', $product->recurring_interval) === 'month' ? 'selected' : '' }}>Mensual</option>
                        <option value="year" {{ old('recurring_interval', $product->recurring_interval) === 'year' ? 'selected' : '' }}>Anual</option>
                    </select>
                    @if($hasStripeProduct)
                        <input type="hidden" name="recurring_interval" value="{{ $product->recurring_interval }}">
                        <small class="text-muted">Edita el intervalo desde Stripe</small>
                    @endif
                </div>

                <input type="hidden" name="recurring_interval_count" value="{{ old('recurring_interval_count', $product->recurring_interval_count ?? 1) }}">

                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', $product->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Activo</label>
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <div class="col-12 d-flex">
                    <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar y actualizar Stripe</button>
                    <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('account.products.index') }}'">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
@endsection
