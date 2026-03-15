@extends('layouts/layoutManual')

@section('title', __('Productos y pedidos'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Productos y pedidos') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Productos') }}</h5>
                <p>{{ __('Los productos son los artículos o servicios que vendes (por ejemplo un curso, una licencia, un producto físico). En el catálogo de productos puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear y editar productos con nombre, precio, descripción, referencia y demás datos que uses.') }}</li>
                    <li>{{ __('Mantener un listado ordenado que luego usas al crear pedidos o facturas.') }}</li>
                    <li>{{ __('Añadir productos a pedidos como líneas con cantidad y precio.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Pedidos') }}</h5>
                <p>{{ __('Un pedido representa una compra de un cliente. Puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear pedidos y añadir líneas de producto (cantidad, precio, descuento si aplica).') }}</li>
                    <li>{{ __('Vincular el pedido a un cliente o contacto para saber quién compra.') }}</li>
                    <li>{{ __('Editar y ver el detalle del pedido y su estado (pendiente, enviado, cobrado, etc.).') }}</li>
                </ul>

                <p class="mb-0">{{ __('Si usas WooCommerce, los productos y pedidos se pueden sincronizar con tu tienda; la configuración técnica se explica en la sección de Ayuda.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
