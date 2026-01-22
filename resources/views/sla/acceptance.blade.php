@extends('layouts/layoutMaster')

@section('title', 'Aceptación de SLA')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Aceptación de Acuerdo de Nivel de Servicio (SLA)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Información del Producto</h6>
                            <p class="mb-0">
                                <strong>Producto:</strong> {{ $product->name }}<br>
                                @if($product->description)
                                <strong>Descripción:</strong> {{ $product->description }}<br>
                                @endif
                                @if($product->plan)
                                <strong>Plan:</strong> {{ $product->plan }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4>{{ $sla->title }}</h4>
                        <p class="text-muted">Versión: {{ $sla->version }}</p>
                    </div>

                    <div class="mb-4">
                        <div class="card bg-light">
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                {!! $sla->content !!}
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('sla.accept', ['token' => $acceptance->token]) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="accepted_by_name" class="form-label">Nombre completo (opcional)</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="accepted_by_name" 
                                   name="accepted_by_name" 
                                   value="{{ old('accepted_by_name', $acceptance->accepted_by_name ?: ($ownerName ?? '')) }}"
                                   placeholder="Tu nombre completo">
                        </div>

                        <div class="alert alert-warning">
                            <strong>⚠️ Importante:</strong> Al hacer clic en "Aceptar SLA", confirmas que has leído y comprendido los términos del acuerdo. Esta aceptación quedará registrada y vinculada a tu suscripción.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i>Aceptar SLA
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-label-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
