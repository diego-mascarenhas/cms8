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
                                        <select name="mapping[{{ $index }}]" class="form-select">
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
                                                <th>{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($rows, 0, 5) as $row)
                                            <tr>
                                                @foreach($row as $cell)
                                                    <td>{{ $cell }}</td>
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
@endsection