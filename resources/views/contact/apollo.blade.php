@extends('layouts/layoutMaster')

@section('title', 'Buscar contactos')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('page-style')
<style>
.apollo-seniority-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.apollo-seniority-chips .btn { margin: 0; }
.person-seniority-select { position: absolute; width: 0; height: 0; opacity: 0; pointer-events: none; }
</style>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span> Buscar contactos</h4>
        <p class="text-muted">Busca personas por filtros y añade los resultados como contactos.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('contact-list') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver a contactos
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Define tu búsqueda</h5>
        <p class="text-muted small mb-4">Indica títulos, ubicación, seniority o palabras clave. Añade los resultados como contactos.</p>
        <div class="row g-3">
            <div class="col-12">
                <label for="person_titles" class="form-label">Títulos (separados por coma)</label>
                <input type="text" class="form-control" id="person_titles" name="person_titles" placeholder="director comercial, gerente de ventas">
            </div>
            <div class="col-md-6">
                <label for="person_locations" class="form-label">Ubicación</label>
                <input type="text" class="form-control" id="person_locations" name="person_locations" placeholder="España, Madrid">
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Seniority</label>
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
                <label for="q_organization_domains_list" class="form-label">Dominios empresa</label>
                <input type="text" class="form-control" id="q_organization_domains_list" name="q_organization_domains_list" placeholder="empresa.com, ejemplo.com">
            </div>
            <div class="col-md-6">
                <label for="organization_locations_people" class="form-label">Ubicación sede empresa</label>
                <input type="text" class="form-control" id="organization_locations_people" name="organization_locations_people" placeholder="California">
            </div>
            <div class="col-12">
                <label for="q_keywords_people" class="form-label">Palabras clave</label>
                <input type="text" class="form-control" id="q_keywords_people" name="q_keywords_people" placeholder="tecnología, software">
            </div>
            <div class="col-12 pt-2">
                <button type="button" class="btn btn-primary" id="btn-search-people">
                    <i class="ti ti-search me-1"></i> Buscar personas
                </button>
            </div>
        </div>
        <div id="people-empty" class="alert alert-info d-none mt-4">Usa los filtros y pulsa "Buscar personas".</div>
        <div id="people-loading" class="text-center py-4 d-none"><span class="spinner-border"></span> Buscando...</div>
    </div>
</div>

{{-- Resultados en bloque separado con DataTables --}}
<div class="card d-none mt-4" id="people-results-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0">Resultados de búsqueda</h5>
    </div>
    <div class="card-body">
        <div id="people-zero-results" class="alert alert-warning d-none mb-3" role="alert">
            No se encontraron personas con estos filtros. Para obtener resultados, añade al menos <strong>títulos</strong> (ej. manager, sales) o <strong>palabras clave</strong>; solo seniority suele devolver 0 resultados.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="apollo-people-table" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Título</th>
                        <th>Empresa</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    var csrf = '{{ csrf_token() }}';
    var urlPeople = '{{ route("contact.apollo.people") }}';
    var urlAddPerson = '{{ route("contact.apollo.add-person") }}';
    var apolloTable = null;

    // Seniority chips: sync with hidden multi-select
    (function initSeniorityChips() {
        var sel = document.getElementById('person_seniorities');
        var container = document.getElementById('seniority-chips-people');
        if (!sel || !container) return;
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
            });
        });
        container.querySelectorAll('.chip').forEach(function(btn) {
            var val = btn.getAttribute('data-value');
            var opt = Array.from(sel.options).filter(function(o) { return o.value === val; })[0];
            setChipActive(btn, opt && opt.selected);
        });
    })();

    function parseList(val) {
        if (!val || !String(val).trim()) return [];
        return String(val).split(/[\n,]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function getPeopleFilters(page, perPage) {
        page = page || 1;
        perPage = perPage || 25;
        var titles = parseList(document.getElementById('person_titles').value);
        var locations = parseList(document.getElementById('person_locations').value);
        var seniorities = Array.from(document.getElementById('person_seniorities').selectedOptions).map(function(o) { return o.value; });
        var domains = parseList(document.getElementById('q_organization_domains_list').value);
        var orgLocations = parseList(document.getElementById('organization_locations_people').value);
        var data = { _token: csrf, page: page, per_page: perPage };
        if (titles.length) data.person_titles = titles;
        if (locations.length) data.person_locations = locations;
        if (seniorities.length) data.person_seniorities = seniorities;
        if (domains.length) data.q_organization_domains_list = domains;
        if (orgLocations.length) data.organization_locations = orgLocations;
        var kw = document.getElementById('q_keywords_people').value;
        if (kw) data.q_keywords = kw;
        return data;
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

    function initApolloDataTable() {
        if (apolloTable && $.fn.DataTable.isDataTable('#apollo-people-table')) {
            apolloTable.destroy();
            apolloTable = null;
        }
        apolloTable = $('#apollo-people-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: function(data, callback, settings) {
                var page = Math.floor(data.start / data.length) + 1;
                var payload = getPeopleFilters(page, data.length);
                document.getElementById('people-loading').classList.remove('d-none');
                fetch(urlPeople, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
                .then(function(res) {
                    document.getElementById('people-loading').classList.add('d-none');
                    if (!res.ok) {
                        toastr.error(res.json.message || 'Error al buscar.');
                        callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        return;
                    }
                    var people = res.json.people || [];
                    var total = res.json.total_entries || 0;
                    var zeroEl = document.getElementById('people-zero-results');
                    if (total === 0) {
                        if (zeroEl) zeroEl.classList.remove('d-none');
                    } else {
                        if (zeroEl) zeroEl.classList.add('d-none');
                    }
                    callback({ draw: data.draw, recordsTotal: total, recordsFiltered: total, data: people });
                })
                .catch(function() {
                    document.getElementById('people-loading').classList.add('d-none');
                    toastr.error('Error de conexión.');
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
            },
            columns: [
                { data: null, title: 'Nombre', orderable: true, render: function(row) {
                    var ln = row.last_name || row.last_name_obfuscated || '';
                    return ((row.first_name || '') + ' ' + ln).trim() || '—';
                }},
                { data: 'title', title: 'Título', defaultContent: '—' },
                { data: 'organization_name', title: 'Empresa', defaultContent: '—' },
                { data: null, title: 'Acciones', orderable: false, render: function(row) {
                    return '<button type="button" class="btn btn-sm btn-primary btn-add-person" data-id="' + (row.id || '') + '"><i class="ti ti-user-plus me-1"></i>Añadir como contacto</button>';
                }}
            ],
            order: [[0, 'asc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50], [10, 25, 50]],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
                emptyTable: 'No hay resultados. Ajusta los filtros y vuelve a buscar.'
            },
            drawCallback: function() {
                if (apolloTable && apolloTable.page.info().recordsDisplay === 0) {
                    document.getElementById('people-zero-results').classList.remove('d-none');
                }
            }
        });
    }

    $(document).on('click', '#people-results-card .btn-add-person', function() {
        var tr = $(this).closest('tr');
        if (apolloTable && tr.length) {
            var row = apolloTable.row(tr).data();
            if (row) addPersonAsContact(row);
        }
    });

    $('#btn-search-people').on('click', function(e) {
        e.preventDefault();
        $('#people-empty').addClass('d-none');
        $('#people-results-card').removeClass('d-none');
        initApolloDataTable();
    });
});
</script>
@endpush
@endsection
