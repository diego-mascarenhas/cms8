@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('Búsqueda de prospectos'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<style>
.prospect-search-demo-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
.prospect-search-demo-row { flex: 1; display: flex; flex-wrap: nowrap; min-height: 0; }
.prospect-search-demo-row .col-lg-7 { min-height: 0; display: flex; flex-direction: column; }
.prospect-search-demo-row .col-lg-5 { min-height: 0; display: flex; flex-direction: column; }
.prospect-search-left { flex: 1; min-height: 0; overflow: auto; }
.prospect-search-right { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.prospect-search-right .card-body { flex: 1; min-height: 0; overflow: auto; }
#people-results-wrap table { font-size: 0.875rem; }
.apollo-seniority-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.apollo-seniority-chips .btn { margin: 0; }
.person-seniority-select { position: absolute; width: 0; height: 0; opacity: 0; pointer-events: none; }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="prospect-search-demo-wrapper authentication-bg">
  <div class="row g-0 prospect-search-demo-row mx-0">
    <!-- Left: search + results + email gate -->
    <div class="col-12 col-lg-7 p-4 auth-cover-bg auth-cover-bg-color prospect-search-left">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <h4 class="mb-0">{{ __('Búsqueda de prospectos') }}</h4>
        <a href="{{ route('login') }}" class="btn btn-sm btn-label-secondary">
          <i class="ti ti-arrow-left me-1"></i>{{ __('Volver al inicio de sesión') }}
        </a>
      </div>

      <p class="text-muted mb-4">{{ __('Busca perfiles por títulos, ubicación o sector. Te mostramos los primeros 10 resultados; introduce tu email para continuar.') }}</p>

      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title mb-1">{{ __('Define tu búsqueda') }}</h5>
          <p class="text-muted mb-4">{{ __('Indica títulos, ubicación, posición o palabras clave. Te mostramos los primeros resultados; introduce tu email para continuar.') }}</p>
          <div class="row g-3">
            <div class="col-12">
              <label for="person_titles" class="form-label">{{ __('Títulos') }}</label>
              <input type="text" class="form-control" id="person_titles" name="person_titles" placeholder="director comercial, gerente de ventas">
              <div class="form-text">{{ __('Indica uno o más títulos de puesto, separados por coma (por ejemplo: director comercial, gerente de ventas).') }}</div>
            </div>
            <div class="col-md-6">
              <label for="person_locations" class="form-label">{{ __('Ubicación de la persona') }}</label>
              <input type="text" class="form-control" id="person_locations" name="person_locations" placeholder="España, Madrid">
              <div class="form-text">{{ __('País o ciudad donde reside la persona.') }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label d-block">{{ __('Posición') }}</label>
              <div class="apollo-seniority-chips" id="seniority-chips-people" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="owner">Owner</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="founder">Founder</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="c_suite">C-Suite</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="vp">VP</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="head">Head</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="director">Director</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="manager">Manager</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="senior">Senior</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="entry">Entry</button>
                <button type="button" class="btn btn-sm btn-outline-primary chip" data-value="intern">Intern</button>
              </div>
              <select class="person-seniority-select form-select" id="person_seniorities" name="person_seniorities" multiple aria-hidden="true" tabindex="-1">
                <option value="owner">Owner</option>
                <option value="founder">Founder</option>
                <option value="c_suite">C-Suite</option>
                <option value="vp">VP</option>
                <option value="head">Head</option>
                <option value="director">Director</option>
                <option value="manager">Manager</option>
                <option value="senior">Senior</option>
                <option value="entry">Entry</option>
                <option value="intern">Intern</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="q_organization_domains_list" class="form-label">{{ __('Dominios empresa') }}</label>
              <input type="text" class="form-control" id="q_organization_domains_list" name="q_organization_domains_list" placeholder="empresa.com, ejemplo.com">
            </div>
            <div class="col-md-6">
              <label for="organization_locations_people" class="form-label">{{ __('Ubicación de la empresa') }}</label>
              <input type="text" class="form-control" id="organization_locations_people" name="organization_locations_people" placeholder="California">
              <div class="form-text">{{ __('País o ciudad donde tiene la sede la empresa.') }}</div>
            </div>
            <div class="col-12">
              <label for="q_keywords_people" class="form-label">{{ __('Palabras clave') }}</label>
              <input type="text" class="form-control" id="q_keywords_people" name="q_keywords_people" placeholder="tecnología, software">
            </div>
            <div class="col-12 pt-2">
              <button type="button" class="btn btn-primary" id="btn-search-people" disabled>
                <i class="ti ti-search me-1"></i> {{ __('Buscar') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="people-loading" class="text-center py-4 d-none"><span class="spinner-border"></span> {{ __('Buscando...') }}</div>
      <div id="people-zero-results" class="alert alert-warning d-none mb-3">{{ __('No se encontraron personas con estos filtros. Prueba con títulos o palabras clave.') }}</div>

      <div id="people-results-wrap" class="d-none">
        <div class="card mb-4">
          <div class="card-body">
            <h5 class="card-title mb-3">{{ __('Resultados') }} <span id="people-total" class="text-muted"></span></h5>
            <div class="table-responsive">
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
          </div>
        </div>

        <div id="email-gate" class="card border-primary">
          <div class="card-body">
            <h6 class="card-title">{{ __('Debes registrarte para continuar') }}</h6>
            <p class="text-muted small mb-3">{{ __('Regístrate para guardar o exportar estos resultados y acceder a más búsquedas. Tras registrarte serás redirigido a los planes de prospectos.') }}</p>
            @if (Route::has('register') && config('custom.custom.showRegister', true))
              <a href="{{ route('register.redirect-to-prospects') }}" class="btn btn-primary">
                <i class="ti ti-user-plus me-1"></i>{{ __('Registrarse') }}
              </a>
            @else
              <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="ti ti-login me-1"></i>{{ __('Iniciar sesión') }}
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <!-- Right: copy -->
    <div class="col-12 col-lg-5 p-4 bg-body border-start prospect-search-right d-none d-lg-flex">
      <div class="card w-100">
        <div class="card-header">
          <h5 class="mb-0">{{ __('¿Qué es la búsqueda de prospectos?') }}</h5>
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
            {{ __('Regístrate tras la búsqueda para desbloquear y guardar los resultados; serás redirigido a los planes de prospectos.') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var csrf = '{{ csrf_token() }}';
    var urlSearch = '{{ route("prospect-search.search") }}';

    function parseList(val) {
        if (!val || !String(val).trim()) return [];
        return String(val).split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function hasSearchCriteria() {
        var titles = parseList(document.getElementById('person_titles').value);
        var locations = parseList(document.getElementById('person_locations').value);
        var seniorities = Array.from(document.getElementById('person_seniorities').selectedOptions).map(function(o) { return o.value; });
        var domains = parseList(document.getElementById('q_organization_domains_list').value);
        var orgLocations = parseList(document.getElementById('organization_locations_people').value);
        var kw = (document.getElementById('q_keywords_people').value || '').trim();
        return titles.length > 0 || locations.length > 0 || seniorities.length > 0 || domains.length > 0 || orgLocations.length > 0 || kw.length > 0;
    }

    function updateSearchButtonState() {
        var btn = document.getElementById('btn-search-people');
        if (btn) btn.disabled = !hasSearchCriteria();
    }

    function getPeopleFilters() {
        var titles = parseList(document.getElementById('person_titles').value);
        var locations = parseList(document.getElementById('person_locations').value);
        var seniorities = Array.from(document.getElementById('person_seniorities').selectedOptions).map(function(o) { return o.value; });
        var domains = parseList(document.getElementById('q_organization_domains_list').value);
        var orgLocations = parseList(document.getElementById('organization_locations_people').value);
        var data = { _token: csrf, page: 1, per_page: 10 };
        if (titles.length) data.person_titles = titles;
        if (locations.length) data.person_locations = locations;
        if (seniorities.length) data.person_seniorities = seniorities;
        if (domains.length) data.q_organization_domains_list = domains;
        if (orgLocations.length) data.organization_locations = orgLocations;
        var kw = document.getElementById('q_keywords_people').value;
        if (kw) data.q_keywords = kw;
        return data;
    }

    ['person_titles', 'person_locations', 'q_organization_domains_list', 'organization_locations_people', 'q_keywords_people'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateSearchButtonState);
    });
    var selSeniority = document.getElementById('person_seniorities');
    if (selSeniority) selSeniority.addEventListener('change', updateSearchButtonState);
    window.updateProspectSearchButtonState = updateSearchButtonState;
    updateSearchButtonState();

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
            var showing = people.length;
            var totalLabel = total === 0 ? ' (0 {{ __("contactos") }})' : ' ({{ __("mostrando") }} ' + showing + ' {{ __("de") }} ' + total + ' {{ __("contactos") }})';
            document.getElementById('people-total').textContent = totalLabel;
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
            }
        })
        .catch(function() {
            document.getElementById('people-loading').classList.add('d-none');
            toastr.error('{{ __("Error de conexión.") }}');
        });
    });

})();
</script>
@endsection

@section('page-script')
<script>
(function() {
    // Seniority chips: sync with hidden multi-select (same as prospect/search page)
    var sel = document.getElementById('person_seniorities');
    var container = document.getElementById('seniority-chips-people');
    if (sel && container) {
        function setChipActive(btn, active) {
            if (active) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        }
        container.querySelectorAll('.chip').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var val = btn.getAttribute('data-value');
                var opt = Array.from(sel.options).filter(function(o) { return o.value === val; })[0];
                if (opt) {
                    opt.selected = !opt.selected;
                    setChipActive(btn, opt.selected);
                }
                if (window.updateProspectSearchButtonState) window.updateProspectSearchButtonState();
            });
        });
        container.querySelectorAll('.chip').forEach(function(btn) {
            var val = btn.getAttribute('data-value');
            var opt = Array.from(sel.options).filter(function(o) { return o.value === val; })[0];
            setChipActive(btn, opt && opt.selected);
        });
    }
})();
</script>
@endsection
