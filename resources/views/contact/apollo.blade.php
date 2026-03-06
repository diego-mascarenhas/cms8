@extends('layouts/layoutMaster')

@section('title', 'Buscar contactos')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span> Buscar contactos</h4>
        <p class="text-muted">Busca personas y empresas por filtros y añade resultados como contactos.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('contact-list') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver a contactos
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-people" data-bs-toggle="tab" data-bs-target="#content-people" type="button" role="tab">Personas</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-organizations" data-bs-toggle="tab" data-bs-target="#content-organizations" type="button" role="tab">Empresas</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            {{-- People tab --}}
            <div class="tab-pane fade show active" id="content-people" role="tabpanel">
                <div class="card mb-4">
                    <h5 class="card-header">Filtros (personas)</h5>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="person_titles" class="form-label">Títulos (uno por línea o separados por coma)</label>
                                <textarea class="form-control" id="person_titles" name="person_titles" rows="2" placeholder="sales manager, director"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="person_locations" class="form-label">Ubicación personas</label>
                                <input type="text" class="form-control" id="person_locations" name="person_locations" placeholder="Spain, Madrid">
                            </div>
                            <div class="col-md-4">
                                <label for="person_seniorities" class="form-label">Seniority</label>
                                <select class="form-select" id="person_seniorities" name="person_seniorities" multiple>
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
                            <div class="col-md-4">
                                <label for="q_organization_domains_list" class="form-label">Dominios empresa (ej. empresa.com)</label>
                                <input type="text" class="form-control" id="q_organization_domains_list" name="q_organization_domains_list" placeholder="empresa.com, ejemplo.com">
                            </div>
                            <div class="col-md-4">
                                <label for="organization_locations_people" class="form-label">Ubicación sede empresa</label>
                                <input type="text" class="form-control" id="organization_locations_people" name="organization_locations_people" placeholder="California">
                            </div>
                            <div class="col-md-4">
                                <label for="q_keywords_people" class="form-label">Palabras clave</label>
                                <input type="text" class="form-control" id="q_keywords_people" name="q_keywords_people" placeholder="keywords">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" id="btn-search-people">
                                    <i class="ti ti-search me-1"></i> Buscar personas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="people-results-wrap" class="d-none">
                    <h5 class="mb-2">Resultados <span id="people-total" class="text-muted"></span></h5>
                    <div id="people-zero-results" class="alert alert-warning d-none mb-3" role="alert">
                        No se encontraron personas con estos filtros. Para obtener resultados, añade al menos <strong>títulos</strong> (ej. manager, sales) o <strong>palabras clave</strong>; solo seniority suele devolver 0 resultados.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Título</th>
                                    <th>Empresa</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="people-tbody"></tbody>
                        </table>
                    </div>
                    <div id="people-pagination" class="mt-2"></div>
                </div>
                <div id="people-empty" class="alert alert-info d-none">Usa los filtros y pulsa "Buscar personas".</div>
                <div id="people-loading" class="text-center py-4 d-none"><span class="spinner-border"></span> Buscando...</div>
            </div>

            {{-- Organizations tab --}}
            <div class="tab-pane fade" id="content-organizations" role="tabpanel">
                <p class="text-muted small">La búsqueda de empresas consume créditos de tu plan.</p>
                <div class="card mb-4">
                    <h5 class="card-header">Filtros (empresas)</h5>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="q_organization_domains" class="form-label">Dominios (separados por coma)</label>
                                <input type="text" class="form-control" id="q_organization_domains" name="q_organization_domains" placeholder="empresa.com, ejemplo.com">
                            </div>
                            <div class="col-md-4">
                                <label for="organization_locations" class="form-label">Ubicación</label>
                                <input type="text" class="form-control" id="organization_locations" name="organization_locations" placeholder="Spain">
                            </div>
                            <div class="col-md-4">
                                <label for="q_keywords_org" class="form-label">Palabras clave</label>
                                <input type="text" class="form-control" id="q_keywords_org" name="q_keywords_org" placeholder="technology">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" id="btn-search-organizations">
                                    <i class="ti ti-search me-1"></i> Buscar empresas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="organizations-results-wrap" class="d-none">
                    <h5 class="mb-2">Resultados <span id="organizations-total" class="text-muted"></span></h5>
                    <div id="organizations-zero-results" class="alert alert-warning d-none mb-3" role="alert">
                        No se encontraron empresas. Prueba con otros filtros (dominios, ubicación o palabras clave).
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Dominio</th>
                                    <th>Empleados</th>
                                    <th>Ubicación</th>
                                </tr>
                            </thead>
                            <tbody id="organizations-tbody"></tbody>
                        </table>
                    </div>
                    <div id="organizations-pagination" class="mt-2"></div>
                </div>
                <div id="organizations-empty" class="alert alert-info">Usa los filtros y pulsa "Buscar empresas".</div>
                <div id="organizations-loading" class="text-center py-4 d-none"><span class="spinner-border"></span> Buscando...</div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var csrf = '{{ csrf_token() }}';
    var urlPeople = '{{ route("contact.apollo.people") }}';
    var urlOrgs = '{{ route("contact.apollo.organizations") }}';
    var urlAddPerson = '{{ route("contact.apollo.add-person") }}';

    function parseList(val) {
        if (!val || !String(val).trim()) return [];
        return String(val).split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function getPeopleFilters(page) {
        page = page || 1;
        var titles = parseList(document.getElementById('person_titles').value);
        var locations = parseList(document.getElementById('person_locations').value);
        var seniorities = Array.from(document.getElementById('person_seniorities').selectedOptions).map(function(o) { return o.value; });
        var domains = parseList(document.getElementById('q_organization_domains_list').value);
        var orgLocations = parseList(document.getElementById('organization_locations_people').value);
        var data = { _token: csrf, page: page, per_page: 25 };
        if (titles.length) data.person_titles = titles;
        if (locations.length) data.person_locations = locations;
        if (seniorities.length) data.person_seniorities = seniorities;
        if (domains.length) data.q_organization_domains_list = domains;
        if (orgLocations.length) data.organization_locations = orgLocations;
        var kw = document.getElementById('q_keywords_people').value;
        if (kw) data.q_keywords = kw;
        return data;
    }

    function getOrgFilters(page) {
        page = page || 1;
        var domains = parseList(document.getElementById('q_organization_domains').value);
        var locations = parseList(document.getElementById('organization_locations').value);
        var data = { _token: csrf, page: page, per_page: 25 };
        if (domains.length) data.q_organization_domains = document.getElementById('q_organization_domains').value.trim();
        if (locations.length) data.organization_locations = locations;
        var kw = document.getElementById('q_keywords_org').value;
        if (kw) data.q_keywords = kw;
        return data;
    }

    function searchPeople(page) {
        var payload = getPeopleFilters(page);
        document.getElementById('people-empty').classList.add('d-none');
        document.getElementById('people-results-wrap').classList.add('d-none');
        document.getElementById('people-loading').classList.remove('d-none');
        fetch(urlPeople, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j, status: r.status }; }); })
        .then(function(res) {
            document.getElementById('people-loading').classList.add('d-none');
            if (!res.ok) {
                toastr.error(res.json.message || 'Error al buscar.');
                return;
            }
            var people = res.json.people || [];
            var total = res.json.total_entries || 0;
            var currentPage = res.json.page || 1;
            var perPage = res.json.per_page || 25;
            document.getElementById('people-total').textContent = '(' + total + ' encontrados)';
            var zeroResultsEl = document.getElementById('people-zero-results');
            if (total === 0) {
                if (zeroResultsEl) zeroResultsEl.classList.remove('d-none');
            } else {
                if (zeroResultsEl) zeroResultsEl.classList.add('d-none');
            }
            var peopleById = {};
            people.forEach(function(p) { peopleById[p.id] = p; });
            var tbody = document.getElementById('people-tbody');
            tbody.innerHTML = '';
            people.forEach(function(p) {
                var tr = document.createElement('tr');
                var lastName = p.last_name || p.last_name_obfuscated || '';
                var name = (p.first_name || '') + ' ' + lastName;
                tr.innerHTML =
                    '<td>' + (name.trim() || '—') + '</td>' +
                    '<td>' + (p.title || '—') + '</td>' +
                    '<td>' + (p.organization_name || '—') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-primary btn-add-person" data-id="' + (p.id || '') + '"><i class="ti ti-user-plus me-1"></i>Añadir como contacto</button></td>';
                tbody.appendChild(tr);
            });
            var pagination = document.getElementById('people-pagination');
            pagination.innerHTML = '';
            if (total > perPage) {
                var totalPages = Math.ceil(total / perPage);
                if (currentPage > 1) {
                    var prev = document.createElement('button');
                    prev.className = 'btn btn-sm btn-outline-secondary me-1';
                    prev.textContent = 'Anterior';
                    prev.onclick = function() { searchPeople(currentPage - 1); };
                    pagination.appendChild(prev);
                }
                pagination.appendChild(document.createTextNode(' Página ' + currentPage + ' de ' + totalPages + ' '));
                if (currentPage < totalPages) {
                    var next = document.createElement('button');
                    next.className = 'btn btn-sm btn-outline-secondary ms-1';
                    next.textContent = 'Siguiente';
                    next.onclick = function() { searchPeople(currentPage + 1); };
                    pagination.appendChild(next);
                }
            }
            document.getElementById('people-results-wrap').classList.remove('d-none');
            tbody.querySelectorAll('.btn-add-person').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var person = peopleById[btn.getAttribute('data-id')];
                    addPersonAsContact(person || { id: btn.getAttribute('data-id') });
                });
            });
        })
        .catch(function() {
            document.getElementById('people-loading').classList.add('d-none');
            toastr.error('Error de conexión.');
        });
    }

    function addPersonAsContact(person) {
        var formData = new FormData();
        formData.append('_token', csrf);
        formData.append('apollo_id', person.id || '');
        formData.append('first_name', person.first_name || '');
        formData.append('last_name_obfuscated', person.last_name_obfuscated || '');
        formData.append('last_name', person.last_name || '');
        formData.append('title', person.title || '');
        formData.append('organization_name', person.organization_name || '');
        if (person.apollo_raw) {
            formData.append('person_data', JSON.stringify(person.apollo_raw));
        }
        fetch(urlAddPerson, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: formData
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
        .then(function(res) {
            if (res.ok && res.json.redirect_url) {
                toastr.success(res.json.message || 'Contacto creado.');
                window.location.href = res.json.redirect_url;
            } else {
                toastr.error(res.json.message || 'Error al crear contacto.');
            }
        })
        .catch(function() { toastr.error('Error de conexión.'); });
    }

    function searchOrganizations(page) {
        var payload = getOrgFilters(page);
        document.getElementById('organizations-empty').classList.add('d-none');
        document.getElementById('organizations-results-wrap').classList.add('d-none');
        document.getElementById('organizations-loading').classList.remove('d-none');
        fetch(urlOrgs, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j, status: r.status }; }); })
        .then(function(res) {
            document.getElementById('organizations-loading').classList.add('d-none');
            if (!res.ok) {
                toastr.error(res.json.message || 'Error al buscar.');
                return;
            }
            var orgs = res.json.organizations || [];
            var total = res.json.total_entries || 0;
            var currentPage = res.json.page || 1;
            var perPage = res.json.per_page || 25;
            document.getElementById('organizations-total').textContent = '(' + total + ' encontrados)';
            var zeroResultsOrg = document.getElementById('organizations-zero-results');
            if (total === 0) {
                if (zeroResultsOrg) zeroResultsOrg.classList.remove('d-none');
            } else {
                if (zeroResultsOrg) zeroResultsOrg.classList.add('d-none');
            }
            var tbody = document.getElementById('organizations-tbody');
            tbody.innerHTML = '';
            orgs.forEach(function(o) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + (o.name || '—') + '</td>' +
                    '<td>' + (o.primary_domain || '—') + '</td>' +
                    '<td>' + (o.estimated_num_employees != null ? o.estimated_num_employees : '—') + '</td>' +
                    '<td>' + (o.location || '—') + '</td>';
                tbody.appendChild(tr);
            });
            var pagination = document.getElementById('organizations-pagination');
            pagination.innerHTML = '';
            if (total > perPage) {
                var totalPages = Math.ceil(total / perPage);
                if (currentPage > 1) {
                    var prev = document.createElement('button');
                    prev.className = 'btn btn-sm btn-outline-secondary me-1';
                    prev.textContent = 'Anterior';
                    prev.onclick = function() { searchOrganizations(currentPage - 1); };
                    pagination.appendChild(prev);
                }
                pagination.appendChild(document.createTextNode(' Página ' + currentPage + ' de ' + totalPages + ' '));
                if (currentPage < totalPages) {
                    var next = document.createElement('button');
                    next.className = 'btn btn-sm btn-outline-secondary ms-1';
                    next.textContent = 'Siguiente';
                    next.onclick = function() { searchOrganizations(currentPage + 1); };
                    pagination.appendChild(next);
                }
            }
            document.getElementById('organizations-results-wrap').classList.remove('d-none');
        })
        .catch(function() {
            document.getElementById('organizations-loading').classList.add('d-none');
            toastr.error('Error de conexión.');
        });
    }

    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('#btn-search-people, #btn-search-organizations') : null;
        if (!btn) return;
        e.preventDefault();
        if (btn.id === 'btn-search-people') searchPeople(1);
        else if (btn.id === 'btn-search-organizations') searchOrganizations(1);
    });
})();
</script>
@endsection
