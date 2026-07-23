@extends('layouts/layoutManual')

@section('title', __('E-commerce'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('E-commerce: productos, tiendas y pedidos') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Productos') }}</h5>
        <p>{{ __('Catálogo de artículos o servicios vendibles: nombre, precio, descripción, referencia. Se usan al crear pedidos y, si aplica, al facturar.') }}</p>

        <h5 class="mt-4">{{ __('Tiendas (Stores)') }}</h5>
        <p>{{ __('Define una o varias tiendas (canales de venta) asociadas al equipo. Útil si operas más de un front o sincronizas con WooCommerce.') }}</p>

        <h5 class="mt-4">{{ __('Pedidos') }}</h5>
        <p>{{ __('Compra de un cliente: líneas de producto, cantidades, precios y estado (pendiente, enviado, cobrado, etc.).') }}</p>

        <h5 class="mt-4">{{ __('WooCommerce') }}</h5>
        <p>{{ __('Puedes sincronizar productos y pedidos con tu tienda WordPress/WooCommerce.') }}</p>
        <p class="mb-0">
            <a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-sm btn-label-primary">{{ __('Configuración WooCommerce (Ayuda)') }}</a>
        </p>

        <x-manual.role-compare section="products-and-orders" />
    </div>
</div>
@endsection
