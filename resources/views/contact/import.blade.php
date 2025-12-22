@extends('layouts.layoutMaster')

@section('title', 'Importar Contactos')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span>
            Importar
        </h4>
        <p class="text-muted">Sube el archivo a importar para luego mapear los campos</p>
    </div>
</div>


<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <h5 class="card-header">Subir Archivo</h5>
            <div class="card-body">
                <form action="{{ route('contact.upload-file-mapping') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label">Selecciona un archivo Excel o CSV</label>
                        <input class="form-control" type="file" id="file" name="file" accept=".csv, .xlsx, .xls, .txt, text/csv, text/plain, application/csv">
                        <div class="form-text">Formatos permitidos: .xlsx, .xls, .csv, .txt</div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-upload me-1"></i>
                        Subir y Continuar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
