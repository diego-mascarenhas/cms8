@extends('layouts/layoutMaster')

@section('title', isset($customerFare) ? 'Editar Tarifa' : 'Nueva Tarifa')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<h4 class="py-3 breadcrumb-wrapper mb-4">
  <span class="text-muted fw-light">Tarifas /</span> {{ isset($customerFare) ? 'Editar' : 'Nueva' }}
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <h5 class="card-header">{{ isset($customerFare) ? 'Editar Tarifa' : 'Nueva Tarifa' }}</h5>
            <div class="card-body">
                <form method="POST" action="{{ isset($customerFare) ? route('customer-fare.update', $customerFare->id) : route('customer-fare.store') }}" class="row g-3">
                    @csrf
                    @if(isset($customerFare))
                        @method('PUT')
                    @endif

                    <div class="col-md-6">
                        <x-team-users-select 
                            id="customer_id" 
                            label="Colaborador" 
                            :selected="isset($customerFare) ? $customerFare->customer_id : (request()->has('collaborator_id') ? request('collaborator_id') : null)" 
                            :showNull="true" 
                        />
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="fare_id">Tipo de Tarifa</label>
                        <select name="fare_id" id="fare_id" class="form-select @error('fare_id') is-invalid @enderror" required>
                            <option value="">Seleccione tipo de tarifa</option>
                            @foreach($fares as $fare)
                                <option value="{{ $fare->id }}" {{ isset($customerFare) && $customerFare->fare_id == $fare->id ? 'selected' : '' }} 
                                    data-unit="{{ $fare->unit->type ?? '' }}" 
                                    data-block="{{ $fare->block->name ?? '' }}">
                                    {{ $fare->name }} ({{ $fare->unit->type ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                        @error('fare_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <x-variant-language-select 
                            name="language_origin_id" 
                            id="language_origin_id" 
                            :value="isset($customerFare) ? $customerFare->language_origin_id : null" 
                            label="Idioma Origen"
                        />
                    </div>

                    <div class="col-md-6">
                        <x-variant-language-select 
                            name="language_destination_id" 
                            id="language_destination_id" 
                            :value="isset($customerFare) ? $customerFare->language_destination_id : null" 
                            label="Idioma Destino"
                        />
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="currency_id">Moneda</label>
                        <select name="currency_id" id="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                            <option value="">Seleccione moneda</option>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->code }}" {{ isset($customerFare) && $customerFare->currency_id == $currency->code ? 'selected' : '' }}>
                                    {{ $currency->name }} ({{ $currency->symbol }})
                                </option>
                            @endforeach
                        </select>
                        @error('currency_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="amount">Precio</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ isset($customerFare) ? $customerFare->amount : old('amount') }}" required>
                            <span class="input-group-text unit-label">{{ isset($customerFare) && $customerFare->fare && $customerFare->fare->unit ? '/'.$customerFare->fare->unit->type : '' }}</span>
                        </div>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="negotiable" id="negotiable" value="1" {{ isset($customerFare) && $customerFare->negotiable ? 'checked' : '' }}>
                            <label class="form-check-label" for="negotiable">
                                Tarifa negociable
                            </label>
                        </div>
                    </div>

                    <div class="col-12 text-end">
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary me-1">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ isset($customerFare) ? 'Actualizar' : 'Guardar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function() {
        const fareSelect = document.getElementById('fare_id');
        const unitLabel = document.querySelector('.unit-label');
        
        if (fareSelect) {
            fareSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const unitType = selectedOption.getAttribute('data-unit');
                unitLabel.textContent = unitType ? '/' + unitType : '';
            });
        }
    });
</script>

@stack('page-script')
@endsection 