@extends('layouts/layoutMaster')

@section('title', 'Asignar código de afiliado')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Afiliados&nbsp;/</span> Asignar código</h4>
        <p class="text-muted">Vinculá un cliente ya suscrito con el código cus_ o sub_ de idoneo-affiliates.</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Código del referido</h5>
    <form class="card-body" action="{{ route('affiliate.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <x-input-select
                    id="team_id"
                    label="Afiliado (*)"
                    :options="$affiliates"
                    value="{{ old('team_id', $team?->id ?? '') }}"
                    placeholder="Seleccionar…"
                    :allow-clear="false"
                />
            </div>
            <div class="col-md-6">
                <x-input-general id="subscription_code" label="Código cus_ o sub_ (*)" value="{{ old('subscription_code') }}" maxlength="64" />
            </div>
        </div>
        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <a href="{{ route('affiliate.index') }}" class="btn btn-label-secondary">Cancelar</a>
            </div>
        </div>
    </form>
</div>
@endsection
