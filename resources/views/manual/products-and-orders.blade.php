@extends('layouts/layoutManual')

@section('title', __('Productos y pedidos'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Productos y pedidos') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Productos') }}</h5>
        <p>{{ __('Los productos son los artículos o servicios que vendes. En el catálogo puedes crear y editar productos (nombre, precio, descripción, referencia) y usarlos en pedidos.') }}</p>

        <h5 class="mt-4">{{ __('Pedidos') }}</h5>
        <p>{{ __('Un pedido representa una compra: líneas de producto, cliente asociado y estado (pendiente, enviado, cobrado, etc.).') }}</p>

        <x-manual.role-compare section="products-and-orders" />

        <p class="mb-0">{{ __('Si usas WooCommerce, productos y pedidos se pueden sincronizar; la configuración técnica está en Ayuda.') }}</p>
    </div>
</div>
@endsection
