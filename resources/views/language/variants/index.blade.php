@extends('layouts/layoutMaster')

@section('title', 'Variantes de Idioma')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<h4 class="py-3 breadcrumb-wrapper mb-4">
  <span class="text-muted fw-light">Configuración /</span> Variantes de Idioma
</h4>

@if(session('success'))
<div class="alert alert-success">
  {{ session('success') }}
</div>
@endif

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Variantes de Idioma</h5>
        <a href="{{ route('language-variants.create') }}" class="btn btn-primary">
          <i class="bx bx-plus me-1"></i> Nueva Variante
        </a>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Idioma Base</th>
                <th>País</th>
                <th>Nombre Nativo</th>
              </tr>
            </thead>
            <tbody>
              @forelse($variants as $variant)
                <tr>
                  <td>{{ $variant->code }}</td>
                  <td>
                    @if($variant->flag)
                      <span class="fi fi-{{ strtolower($variant->flag) }} me-2"></span>
                    @endif
                    {{ $variant->name }}
                  </td>
                  <td>
                    @php
                      $baseLanguage = $languages->firstWhere('code', $variant->base_language);
                    @endphp
                    {{ $baseLanguage ? $baseLanguage->name : $variant->base_language }}
                  </td>
                  <td>{{ strtoupper($variant->country_code ?? '') }}</td>
                  <td>{{ $variant->native_name }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center">No hay variantes de idioma registradas</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection 