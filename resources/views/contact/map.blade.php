@extends('layouts.layoutMaster')

@section('title', 'Mapear Campos')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span>
            Mapear
        </h4>
        <p class="text-muted">Mapea los campos para importar los contactos</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Vista Previa y Mapeo</h5>
                <p class="text-muted">Selecciona a qué campo corresponde cada columna del archivo</p>
            </div>
            <div class="card-body">
                <form action="{{ route('contact.process-mapping') }}" method="POST">
                    @csrf

                    <div class="row mb-4">
                        <div class="col-12">
                            @foreach($headers as $index => $header)
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label"><strong>{{ $header }}</strong></label>
                                    </div>
                                    <div class="col-md-8">
                                        <select name="mapping[{{ $index }}]" class="form-select select2-mapping" data-column="{{ $index }}">
                                            <option value="">No importar esta columna</option>
                                            @foreach($availableFields as $field => $label)
                                                <option value="{{ $field }}"
                                                    {{ in_array($header, ['First Name', 'Middle Name', 'Last Name']) && $field === 'name' ? 'selected' : '' }}
                                                    {{ $header === 'E-mail 1 - Value' && $field === 'email' ? 'selected' : '' }}
                                                    {{ $header === 'Phone 1 - Value' && $field === 'phone' ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <h6>Vista Previa de Datos</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            @foreach($headers as $header)
                                                <th>
                                                    @php
                                                        $isPhoneHeader = strpos(strtolower($header), 'phone') !== false ||
                                                                        strpos(strtolower($header), 'tel') !== false ||
                                                                        strpos(strtolower($header), 'móvil') !== false ||
                                                                        strpos(strtolower($header), 'celular') !== false;
                                                    @endphp

                                                    @if($isPhoneHeader)
                                                        <i class="ti ti-phone text-primary me-1"></i>
                                                    @endif
                                                    {{ $header }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($rows, 0, 5) as $row)
                                            <tr>
                                                @foreach($row as $index => $cell)
                                                    <td>
                                                        @php
                                                            $header = $headers[$index] ?? '';
                                                            $isPhone = strpos(strtolower($header), 'phone') !== false ||
                                                                      strpos(strtolower($header), 'tel') !== false ||
                                                                      strpos(strtolower($header), 'móvil') !== false ||
                                                                      strpos(strtolower($header), 'celular') !== false ||
                                                                      (is_numeric($cell) && strlen($cell) >= 9 && strlen($cell) <= 15);
                                                        @endphp

                                                        @if($isPhone && !empty($cell))
                                                            <div class="d-flex align-items-center">
                                                                <i class="ti ti-phone text-primary me-2"></i>
                                                                <span class="badge bg-label-primary">{{ $cell }}</span>
                                                            </div>
                                                        @else
                                                            {{ $cell }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if(count($rows) > 5)
                                <div class="text-muted mt-2">
                                    Mostrando 5 de {{ count($rows) }} filas
                                </div>
                            @endif

                            @php
                                $phoneCount = 0;
                                $emailCount = 0;
                                foreach(array_slice($rows, 0, 20) as $row) {
                                    foreach($row as $index => $cell) {
                                        $header = $headers[$index] ?? '';
                                        if (!empty($cell)) {
                                            // Count phones
                                            if (strpos(strtolower($header), 'phone') !== false ||
                                                strpos(strtolower($header), 'tel') !== false ||
                                                (is_numeric($cell) && strlen($cell) >= 9 && strlen($cell) <= 15)) {
                                                $phoneCount++;
                                            }
                                            // Count emails
                                            if (strpos(strtolower($header), 'email') !== false ||
                                                strpos(strtolower($header), 'mail') !== false ||
                                                filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                                                $emailCount++;
                                            }
                                        }
                                    }
                                }
                            @endphp

                            @if($phoneCount > 0 || $emailCount > 0)
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="ti ti-info-circle me-2"></i>
                                            <div>
                                                <strong>Datos detectados:</strong>
                                                @if($phoneCount > 0)
                                                    <span class="badge bg-primary ms-2">
                                                        <i class="ti ti-phone me-1"></i>{{ $phoneCount }} teléfonos
                                                    </span>
                                                @endif
                                                @if($emailCount > 0)
                                                    <span class="badge bg-success ms-2">
                                                        <i class="ti ti-mail me-1"></i>{{ $emailCount }} emails
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Additional Import Settings -->
                    <div class="row mt-4 border-top pt-4">
                        <div class="col-12">
                            <h5 class="mb-3"><i class="ti ti-settings me-2"></i>Configuración de Importación</h5>
                        </div>

                        <!-- Categories Selection -->
                        <div class="col-md-6 mb-3">
                            <x-categories-select
                                id="categories"
                                label="Categorías"
                                moduleKey="contacts"
                                helpText="Selecciona una o más categorías para asignar a todos los contactos importados"
                            />
                        </div>

                        <!-- Status Selection -->
                        <div class="col-md-6 mb-3">
                            <x-input-select
                                id="status_id"
                                label="Estado del Contacto"
                                :options="$statuses"
                                :value="1"
                                :required="true"
                                helpText="Todos los contactos importados tendrán este estado"
                            />
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-file-import me-1"></i>
                            Importar Datos
                        </button>
                        <a href="{{ route('contact-list') }}" class="btn btn-secondary">
                            <i class="ti ti-x me-1"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Initialize Select2 for mapping selects
    $('.select2-mapping').select2({
        placeholder: 'Seleccionar campo',
        allowClear: true,
        width: '100%'
    });

    // Initialize Select2 for categories (handled by component)
    // Initialize Select2 for status (handled by component)
});
</script>
@endsection
@endsection
