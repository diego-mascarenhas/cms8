@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('ProspectFlow - Buscar contactos'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<style>
.prospectflow-demo-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
.prospectflow-demo-row { flex: 1; display: flex; flex-wrap: nowrap; min-height: 0; }
.prospectflow-demo-row .col-lg-7 { min-height: 0; display: flex; flex-direction: column; }
.prospectflow-demo-row .col-lg-5 { min-height: 0; display: flex; flex-direction: column; }
.prospectflow-left { flex: 1; min-height: 0; overflow: auto; }
.prospectflow-right { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.prospectflow-right .card-body { flex: 1; min-height: 0; overflow: auto; }
#people-results-wrap table { font-size: 0.875rem; }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="prospectflow-demo-wrapper authentication-bg">
  <div class="row g-0 prospectflow-demo-row mx-0">
    <!-- Left: search + results + email gate -->
    <div class="col-12 col-lg-7 p-4 auth-cover-bg auth-cover-bg-color prospectflow-left">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <h4 class="mb-0">{{ __('ProspectFlow') }}</h4>
        <a href="{{ route('login') }}" class="btn btn-sm btn-label-secondary">
          <i class="ti ti-arrow-left me-1"></i>{{ __('Volver al inicio de sesión') }}
        </a>
      </div>

      <p class="text-muted mb-4">{{ __('Busca perfiles por títulos, ubicación o sector. Te mostramos los primeros 10 resultados; introduce tu email para continuar.') }}</p>

      <div class="card mb-4">
        <h5 class="card-header">{{ __('Filtros') }}</h5>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="person_titles" class="form-label">{{ __('Títulos (separados por coma)') }}</label>
              <textarea class="form-control" id="person_titles" name="person_titles" rows="2" placeholder="sales manager, director"></textarea>
            </div>
            <div class="col-md-4">
              <label for="person_locations" class="form-label">{{ __('Ubicación') }}</label>
              <input type="text" class="form-control" id="person_locations" name="person_locations" placeholder="Spain, Madrid">
            </div>
            <div class="col-md-4">
              <label for="person_seniorities" class="form-label">{{ __('Seniority') }}</label>
              <select class="form-select select2-select" id="person_seniorities" name="person_seniorities" multiple>
                <option value="owner">Owner</option>
                <option value="founder">Founder</option>
                <option value="c_suite">C-Suite</option>
                <option value="vp">VP</option>
                <option value="director">Director</option>
                <option value="manager">Manager</option>
                <option value="senior">Senior</option>
                <option value="entry">Entry</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="q_organization_domains_list" class="form-label">{{ __('Dominios empresa') }}</label>
              <input type="text" class="form-control" id="q_organization_domains_list" name="q_organization_domains_list" placeholder="empresa.com">
            </div>
            <div class="col-md-4">
              <label for="q_keywords_people" class="form-label">{{ __('Palabras clave') }}</label>
              <input type="text" class="form-control" id="q_keywords_people" name="q_keywords_people" placeholder="technology">
            </div>
            <div class="col-12">
              <button type="button" class="btn btn-primary" id="btn-search-people">
                <i class="ti ti-search me-1"></i> {{ __('Buscar') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="people-loading" class="text-center py-4 d-none"><span class="spinner-border"></span> {{ __('Buscando...') }}</div>
      <div id="people-zero-results" class="alert alert-warning d-none mb-3">{{ __('No se encontraron personas con estos filtros. Prueba con títulos o palabras clave.') }}</div>

      <div id="people-results-wrap" class="d-none">
        <h5 class="mb-2">{{ __('Resultados') }} <span id="people-total" class="text-muted"></span></h5>
        <div class="table-responsive mb-4">
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>{{ __('Nombre') }}</th>
                <th>{{ __('Título') }}</th>
                <th>{{ __('Empresa') }}</th>
              </tr>
            </thead>
            <tbody id="people-tbody"></tbody>
          </table>
        </div>

        <div id="email-gate" class="card border-primary">
          <div class="card-body">
            <h6 class="card-title">{{ __('Introduce tu email para continuar') }}</h6>
            <p class="text-muted small mb-3">{{ __('Te permitirá guardar o exportar estos resultados y acceder a más búsquedas.') }}</p>
            <form id="form-email-gate" class="row g-2 align-items-end">
              <div class="col-md-6">
                <label for="lead_email" class="form-label visually-hidden">{{ __('Email') }}</label>
                <input type="email" class="form-control" id="lead_email" name="email" placeholder="tu@email.com" required>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">{{ __('Enviar') }}</button>
              </div>
            </form>
            <p id="email-gate-success" class="text-success small mt-2 mb-0 d-none"></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: copy -->
    <div class="col-12 col-lg-5 p-4 bg-body border-start prospectflow-right d-none d-lg-flex">
      <div class="card w-100">
        <div class="card-header">
          <h5 class="mb-0">{{ __('¿Qué es ProspectFlow?') }}</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            {{ __('Herramienta de prospección para encontrar contactos y empresas por criterios: cargo, sector, ubicación o palabras clave.') }}
          </p>
          <h6 class="text-body mb-2">{{ __('Búsqueda por perfiles') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Define títulos (manager, director, founder), ubicación o dominio de empresa. Verás una vista previa de los primeros resultados; con tu email podrás acceder a la lista completa e integrarla en tu CRM.') }}
          </p>
          <h6 class="text-body mb-2">{{ __('Datos enriquecidos') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Una vez registrado, podrás exportar resultados, añadirlos como contactos y enriquecer perfiles con email y teléfono desde la plataforma.') }}
          </p>
          <p class="small text-muted mb-0">
            {{ __('Introduce tu email tras la búsqueda para desbloquear y guardar los resultados.') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var csrf = '{{ csrf_token() }}';
    var urlSearch = '{{ route("prospectflow.search") }}';
    var urlLead = '{{ route("prospectflow.lead") }}';

    function parseList(val) {
        if (!val || !String(val).trim()) return [];
        return String(val).split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function getPeopleFilters() {
        var titles = parseList(document.getElementById('person_titles').value);
        var locations = parseList(document.getElementById('person_locations').value);
        var seniorities = Array.from(document.getElementById('person_seniorities').selectedOptions).map(function(o) { return o.value; });
        var domains = parseList(document.getElementById('q_organization_domains_list').value);
        var data = { _token: csrf, page: 1, per_page: 10 };
        if (titles.length) data.person_titles = titles;
        if (locations.length) data.person_locations = locations;
        if (seniorities.length) data.person_seniorities = seniorities;
        if (domains.length) data.q_organization_domains_list = domains;
        var kw = document.getElementById('q_keywords_people').value;
        if (kw) data.q_keywords = kw;
        return data;
    }

    document.getElementById('btn-search-people').addEventListener('click', function() {
        var payload = getPeopleFilters();
        document.getElementById('people-results-wrap').classList.add('d-none');
        document.getElementById('people-zero-results').classList.add('d-none');
        document.getElementById('people-loading').classList.remove('d-none');

        fetch(urlSearch, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
        .then(function(res) {
            document.getElementById('people-loading').classList.add('d-none');
            if (!res.ok) {
                toastr.error(res.json.message || '{{ __("Error al buscar.") }}');
                return;
            }
            var people = res.json.people || [];
            var total = res.json.total_entries || 0;
            document.getElementById('people-total').textContent = '(' + people.length + ')';
            var tbody = document.getElementById('people-tbody');
            tbody.innerHTML = '';
            if (people.length === 0) {
                document.getElementById('people-zero-results').classList.remove('d-none');
            } else {
                people.forEach(function(p) {
                    var tr = document.createElement('tr');
                    var lastName = p.last_name || p.last_name_obfuscated || '';
                    var name = (p.first_name || '') + ' ' + lastName;
                    tr.innerHTML =
                        '<td>' + (name.trim() || '—') + '</td>' +
                        '<td>' + (p.title || '—') + '</td>' +
                        '<td>' + (p.organization_name || '—') + '</td>';
                    tbody.appendChild(tr);
                });
                document.getElementById('people-results-wrap').classList.remove('d-none');
                document.getElementById('email-gate-success').classList.add('d-none');
            }
        })
        .catch(function() {
            document.getElementById('people-loading').classList.add('d-none');
            toastr.error('{{ __("Error de conexión.") }}');
        });
    });

    document.getElementById('form-email-gate').addEventListener('submit', function(e) {
        e.preventDefault();
        var email = document.getElementById('lead_email').value.trim();
        if (!email) return;
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        var formData = new FormData();
        formData.append('_token', csrf);
        formData.append('email', email);
        fetch(urlLead, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: formData
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
        .then(function(res) {
            btn.disabled = false;
            if (res.ok && res.json.success) {
                document.getElementById('email-gate-success').textContent = res.json.message || '{{ __("Gracias, hemos recibido tu email.") }}';
                document.getElementById('email-gate-success').classList.remove('d-none');
                document.getElementById('form-email-gate').querySelector('input[name="email"]').value = '';
            } else {
                toastr.error(res.json.message || '{{ __("Error al enviar.") }}');
            }
        })
        .catch(function() {
            btn.disabled = false;
            toastr.error('{{ __("Error de conexión.") }}');
        });
    });
})();
</script>
@endsection

@section('page-script')
<script>
(function() {
    if (window.$ && $.fn.select2) {
        $('#person_seniorities').select2({
            width: '100%',
            placeholder: '{{ __("Seleccionar seniority") }}',
            allowClear: true
        });
    }
})();
</script>
@endsection
