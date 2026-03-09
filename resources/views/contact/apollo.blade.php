@extends('layouts/layoutMaster')

@section('title', 'Buscar contactos')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
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
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contactos/</span> Buscar contactos</h4>
        <p class="text-muted">Busca personas por filtros y añade los resultados como contactos. @if(isset($remainingProspectCredits)) <strong>{{ __('Créditos de prospectos') }}: {{ $remainingProspectCredits }}</strong> @endif</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-1">Define tu búsqueda</h5>
        <p class="text-muted mb-4">Indica títulos, ubicación, posición o palabras clave. Añade los resultados como contactos.</p>
        <div class="row g-3">
            <div class="col-12">
                <label for="person_titles" class="form-label">Títulos</label>
                <input type="text" class="form-control" id="person_titles" name="person_titles" placeholder="director comercial, gerente de ventas">
                <div class="form-text">Indica uno o más títulos de puesto, separados por coma (por ejemplo: director comercial, gerente de ventas).</div>
            </div>
            <div class="col-md-6">
                <label for="person_locations" class="form-label">Ubicación de la persona</label>
                <input type="text" class="form-control" id="person_locations" name="person_locations" placeholder="España, Madrid">
                <div class="form-text">País o ciudad donde reside la persona.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label d-block">Posición</label>
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
                <label for="organization_locations_people" class="form-label">Ubicación de la empresa</label>
                <input type="text" class="form-control" id="organization_locations_people" name="organization_locations_people" placeholder="California">
                <div class="form-text">País o ciudad donde tiene la sede la empresa.</div>
            </div>
            <div class="col-12">
                <label for="q_keywords_people" class="form-label">Palabras clave</label>
                <input type="text" class="form-control" id="q_keywords_people" name="q_keywords_people" placeholder="tecnología, software">
            </div>
            <div class="col-12 pt-2">
                <button type="button" class="btn btn-primary" id="btn-search-people" disabled>
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
        <div>
            <h5 class="card-title mb-0">{{ __('Resultados de búsqueda') }}</h5>
            <p class="text-muted small mb-0" id="people-results-count"></p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="btn-import-selected" style="display: none;">
                <i class="ti ti-user-plus me-1"></i> {{ __('Importar seleccionados') }}
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="people-zero-results" class="alert alert-warning d-none mb-3" role="alert">
            No se encontraron personas con estos filtros. Para obtener resultados, añade al menos <strong>títulos</strong> (ej. manager, sales) o <strong>palabras clave</strong>; solo seniority suele devolver 0 resultados.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" id="apollo-people-table" style="width:100%">
                <thead>
                    <tr>
                        <th class="dt-body-center" style="width: 2rem;">
                            <input type="checkbox" class="form-check-input" id="apollo-select-all" title="Seleccionar todos" aria-label="Seleccionar todos">
                        </th>
                        <th>Nombre</th>
                        <th>Título</th>
                        <th>Empresa</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: elegir categoría al importar --}}
<div class="modal fade" id="apolloImportCategoryModal" tabindex="-1" aria-labelledby="apolloImportCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apolloImportCategoryModalLabel">{{ __('Categoría para importar') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="apollo_import_category_modal_id" class="form-label">{{ __('Seleccionar o crear categoría') }}</label>
                <select id="apollo_import_category_modal_id" name="apollo_import_category_modal_id" class="form-select" data-placeholder="{{ __('Seleccionar o crear categoría') }}">
                    <option value="">{{ __('Seleccionar o crear categoría') }}</option>
                    @foreach($contactCategories ?? [] as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="button" class="btn btn-primary" id="apollo-import-modal-confirm">
                    <i class="ti ti-user-plus me-1"></i> {{ __('Importar') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: sin créditos de prospectos --}}
<div class="modal fade" id="prospectCreditsModal" tabindex="-1" aria-labelledby="prospectCreditsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prospectCreditsModalLabel">
                    <i class="ti ti-alert-triangle text-warning me-2"></i>{{ __('Créditos de prospectos') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('Necesitas comprar créditos para importar contactos. Contrata un plan o compra créditos en la sección de prospectos.') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary waves-effect" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <a href="{{ route('subscription.index') }}#prospectos" class="btn btn-warning waves-effect">{{ __('Ir a planes de prospectos') }}</a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    var csrf = '{{ csrf_token() }}';
    var urlPeople = '{{ route("contact.apollo.people") }}';
    var urlAddPerson = '{{ route("contact.apollo.add-person") }}';
    var urlCategoryQuickStore = '{{ route("categories.quick-store") }}';
    var canImportProspects = {{ ($canImportProspects ?? true) ? 'true' : 'false' }};
    var apolloTable = null;

    var pendingImportAction = null;

    if ($.fn.select2) {
        $('#apollo_import_category_modal_id').select2({
            dropdownParent: $('#apolloImportCategoryModal'),
            placeholder: '{{ __("Seleccionar o crear categoría") }}',
            allowClear: true,
            tags: true,
            createTag: function(params) {
                var term = $.trim(params.term);
                if (term === '') return null;
                return { id: 'new__' + term, text: term };
            },
            width: '100%'
        }).on('select2:select', function(e) {
            var data = e.params.data;
            if (data.id && String(data.id).indexOf('new__') === 0) {
                var name = data.text;
                var $sel = $(this);
                $.ajax({
                    url: urlCategoryQuickStore,
                    method: 'POST',
                    data: { _token: csrf, name: name, module_key: 'contacts' },
                    dataType: 'json'
                }).done(function(res) {
                    if (res.success && res.category) {
                        $sel.find('option').filter(function() { return String(this.value).indexOf('new__') === 0; }).remove();
                        var opt = new Option(res.category.name, res.category.id, true, true);
                        $sel.append(opt).val(res.category.id).trigger('change');
                    }
                }).fail(function() {
                    toastr.error('{{ __("Error al crear la categoría.") }}');
                    $sel.val(null).trigger('change');
                });
            }
        });
    }

    $('#apolloImportCategoryModal').on('show.bs.modal', function() {
        $('#apollo_import_category_modal_id').val(null).trigger('change');
    });

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
        $('#btn-search-people').prop('disabled', !hasSearchCriteria());
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

    function addPersonAsContact(person, categoryId) {
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
        if (categoryId && String(categoryId).indexOf('new__') !== 0) formData.append('category_id', categoryId);
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
            searching: false,
            lengthChange: false,
            ajax: function(data, callback, settings) {
                var page = Math.floor(data.start / data.length) + 1;
                var payload = getPeopleFilters(page, data.length);
                var $btn = $('#btn-search-people');
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Buscando...');
                fetch(urlPeople, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
                .then(function(res) {
                    $btn.prop('disabled', false).html('<i class="ti ti-search me-1"></i> Buscar personas');
                    if (!res.ok) {
                        toastr.error(res.json.message || 'Error al buscar.');
                        updatePeopleResultsCount(0);
                        callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        return;
                    }
                    var people = res.json.people || [];
                    var total = res.json.total_entries || 0;
                    updatePeopleResultsCount(total);
                    var zeroEl = document.getElementById('people-zero-results');
                    if (total === 0) {
                        if (zeroEl) zeroEl.classList.remove('d-none');
                    } else {
                        if (zeroEl) zeroEl.classList.add('d-none');
                    }
                    callback({ draw: data.draw, recordsTotal: total, recordsFiltered: total, data: people });
                })
                .catch(function() {
                    $btn.prop('disabled', false).html('<i class="ti ti-search me-1"></i> Buscar personas');
                    toastr.error('Error de conexión.');
                    updatePeopleResultsCount(0);
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
            },
            columns: [
                { data: null, orderable: false, searchable: false, className: 'dt-body-center', render: function(row) {
                    return '<input type="checkbox" class="form-check-input apollo-row-checkbox" value="' + (row.id || '') + '" aria-label="Seleccionar">';
                }},
                { data: null, title: 'Nombre', orderable: false, render: function(row) {
                    var ln = row.last_name || row.last_name_obfuscated || '';
                    return ((row.first_name || '') + ' ' + ln).trim() || '—';
                }},
                { data: 'title', title: 'Título', orderable: false, defaultContent: '—' },
                { data: 'organization_name', title: 'Empresa', orderable: false, defaultContent: '—' },
                { data: null, title: 'Email', orderable: false, className: 'text-center', render: function(row) {
                    if (row.has_email) return '<span class="badge bg-label-success">Sí</span>';
                    return '<span class="text-muted">—</span>';
                }},
                { data: null, title: 'Teléfono', orderable: false, className: 'text-center', render: function(row) {
                    if (row.has_phone) return '<span class="badge bg-label-success">Sí</span>';
                    return '<span class="text-muted">—</span>';
                }},
                { data: null, title: 'Acciones', orderable: false, className: 'text-center', render: function(row) {
                    if (!canImportProspects) {
                        return '<div class="d-flex justify-content-center align-items-center"><a href="javascript:;" class="text-muted btn-show-credits-modal" title="{{ __("Importar") }}"><i class="ti ti-user-plus ti-sm me-2"></i></a></div>';
                    }
                    return '<div class="d-flex justify-content-center align-items-center"><a href="javascript:;" class="text-body btn-add-person" data-id="' + (row.id || '') + '" title="Importar"><i class="ti ti-user-plus ti-sm me-2"></i></a></div>';
                }}
            ],
            order: [],
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
                updateImportButtonVisibility();
            }
        });
    }

    function getSelectedRowData() {
        if (!apolloTable) return [];
        var data = [];
        $('#apollo-people-table tbody .apollo-row-checkbox:checked').each(function() {
            var tr = $(this).closest('tr');
            var row = apolloTable.row(tr).data();
            if (row) data.push(row);
        });
        return data;
    }

    function updateImportButtonVisibility() {
        var n = $('#apollo-people-table tbody .apollo-row-checkbox:checked').length;
        $('#btn-import-selected').toggle(n > 0);
    }

    function updatePeopleResultsCount(total) {
        var el = document.getElementById('people-results-count');
        if (!el) return;
        el.textContent = total === 0 ? 'Ningún registro' : (total === 1 ? '1 registro' : total + ' registros');
    }

    $(document).on('change', '#apollo-select-all', function() {
        var checked = this.checked;
        $('#apollo-people-table tbody .apollo-row-checkbox').prop('checked', checked);
        updateImportButtonVisibility();
    });

    $(document).on('change', '#people-results-card .apollo-row-checkbox', function() {
        var total = $('#apollo-people-table tbody .apollo-row-checkbox').length;
        var checked = $('#apollo-people-table tbody .apollo-row-checkbox:checked').length;
        $('#apollo-select-all').prop('checked', total > 0 && checked === total).prop('indeterminate', checked > 0 && checked < total);
        updateImportButtonVisibility();
    });

    function getCategoryIdFromModal(callback) {
        var catVal = $('#apollo_import_category_modal_id').val();
        if (!catVal || String(catVal).indexOf('new__') !== 0) {
            callback(catVal || null);
            return;
        }
        var data = $('#apollo_import_category_modal_id').select2('data');
        var name = (data && data[0]) ? data[0].text : String(catVal).replace(/^new__/, '');
        $.ajax({
            url: urlCategoryQuickStore,
            method: 'POST',
            data: { _token: csrf, name: name, module_key: 'contacts' },
            dataType: 'json'
        }).done(function(res) {
            if (res.success && res.category) callback(res.category.id); else callback(null);
        }).fail(function() {
            toastr.error('{{ __("Error al crear la categoría.") }}');
            callback(null);
        });
    }

    $(document).on('click', '#apollo-import-modal-confirm', function() {
        var action = pendingImportAction;
        if (!action) return;
        getCategoryIdFromModal(function(catId) {
            bootstrap.Modal.getInstance(document.getElementById('apolloImportCategoryModal')).hide();
            pendingImportAction = null;
            if (action.type === 'single' && action.row) {
                addPersonAsContact(action.row, catId);
                return;
            }
            if (action.type === 'bulk' && action.selected && action.selected.length > 0) {
                var selected = action.selected;
                var btn = $('#btn-import-selected').prop('disabled', true);
                function addOne(index, categoryId) {
                    if (index >= selected.length) {
                        btn.prop('disabled', false);
                        toastr.success(selected.length === 1 ? 'Contacto importado.' : 'Se importaron ' + selected.length + ' contactos.');
                        if (selected.length === 1) return;
                        apolloTable && apolloTable.rows().every(function() {
                            var d = this.data();
                            if (selected.some(function(s) { return s.id === d.id; })) {
                                $(this.node()).find('.apollo-row-checkbox').prop('checked', false);
                            }
                        });
                        $('#apollo-select-all').prop('checked', false).prop('indeterminate', false);
                        updateImportButtonVisibility();
                        return;
                    }
                    var person = selected[index];
                    var formData = new FormData();
                    formData.append('_token', csrf);
                    formData.append('apollo_id', person.id || '');
                    formData.append('first_name', person.first_name || '');
                    formData.append('last_name_obfuscated', person.last_name_obfuscated || '');
                    formData.append('last_name', person.last_name || '');
                    formData.append('title', person.title || '');
                    formData.append('organization_name', person.organization_name || '');
                    if (person.apollo_raw) formData.append('person_data', JSON.stringify(person.apollo_raw));
                    if (categoryId && String(categoryId).indexOf('new__') !== 0) formData.append('category_id', categoryId);
                    fetch(urlAddPerson, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: formData
                    })
                    .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); })
                    .then(function(res) {
                        if (res.ok && res.json.redirect_url && selected.length === 1) {
                            toastr.success(res.json.message || 'Contacto creado.');
                            window.location.href = res.json.redirect_url;
                            return;
                        }
                        if (!res.ok) toastr.error(res.json.message || 'Error al importar.');
                        addOne(index + 1, categoryId);
                    })
                    .catch(function() {
                        toastr.error('Error de conexión.');
                        addOne(index + 1, categoryId);
                    });
                }
                addOne(0, catId);
            }
        });
    });

    $(document).on('click', '#btn-import-selected', function() {
        if (!canImportProspects) {
            new bootstrap.Modal(document.getElementById('prospectCreditsModal')).show();
            return;
        }
        var selected = getSelectedRowData();
        if (selected.length === 0) return;
        pendingImportAction = { type: 'bulk', selected: selected };
        new bootstrap.Modal(document.getElementById('apolloImportCategoryModal')).show();
    });

    $(document).on('click', '#people-results-card .btn-show-credits-modal', function() {
        new bootstrap.Modal(document.getElementById('prospectCreditsModal')).show();
    });

    $(document).on('click', '#people-results-card .btn-add-person', function() {
        if (!canImportProspects) {
            new bootstrap.Modal(document.getElementById('prospectCreditsModal')).show();
            return;
        }
        var tr = $(this).closest('tr');
        if (apolloTable && tr.length) {
            var row = apolloTable.row(tr).data();
            if (row) {
                pendingImportAction = { type: 'single', row: row };
                new bootstrap.Modal(document.getElementById('apolloImportCategoryModal')).show();
            }
        }
    });

    $('#btn-search-people').on('click', function(e) {
        e.preventDefault();
        $('#people-empty').addClass('d-none');
        $('#people-results-card').removeClass('d-none');
        initApolloDataTable();
    });

    $('#person_titles, #person_locations, #q_organization_domains_list, #organization_locations_people, #q_keywords_people').on('input', updateSearchButtonState);
    $(document).on('click', '#seniority-chips-people .chip', function() { setTimeout(updateSearchButtonState, 0); });
    updateSearchButtonState();
});
</script>
@endpush
@endsection
