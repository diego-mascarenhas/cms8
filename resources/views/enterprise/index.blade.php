@extends('layouts/layoutMaster')

@section('title', __('Enterprises'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
@endsection

<style>
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-out;
    }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
});
</script>
@endpush

@section('content')
    @if (session('success'))
        <div id="toast-container" class="toast-top-right">
            <div class="toast toast-success" aria-live="polite" style="display: block;">
                <div class="toast-client">{{ session('success') }}</div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toastElement = document.getElementById('toast-container');
                var toast = new bootstrap.Toast(toastElement, {
                    animation: true,
                    delay: 1000,
                    autohide: true
                });
                toast.show();
            });
        </script>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Enterprises') }}</h4>
            <p class="text-muted">{{ __('Manage enterprises and billing addresses') }}</p>
        </div>
        @can('create', \App\Models\Enterprise::class)
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#placesSearchModal">
                <i class="ti ti-brand-google me-1"></i> {{ __('Search business') }}
            </button>
        </div>
        @endcan
    </div>

    @can('create', \App\Models\Enterprise::class)
    <div class="modal fade" id="placesSearchModal" tabindex="-1" aria-labelledby="placesSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="placesSearchModalLabel">{{ __('Search business') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="placesSearchStep">
                        <form id="placesSearchForm" class="mb-3">
                            <div class="input-group">
                                <input type="text" id="placesTextQuery" class="form-control" placeholder="{{ __('e.g. restaurant Madrid') }}" maxlength="500" autocomplete="off">
                                <button type="submit" class="btn btn-primary" id="placesSearchBtn">
                                    <i class="ti ti-search"></i>
                                </button>
                            </div>
                        </form>
                        <div id="placesError" class="alert alert-danger py-2" role="alert" style="display: none;"></div>
                        <div id="placesResultsContainer" class="list-group" style="max-height: 320px; overflow-y: auto; display: none;"></div>
                        <div id="placesLoading" class="text-center text-muted py-3" style="display: none;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>{{ __('Loading...') }}
                        </div>
                        <div id="placesEmpty" class="text-muted text-center py-3" style="display: none;">{{ __('Enter a search term and click search.') }}</div>
                    </div>
                    <div id="placesDetailsStep" style="display: none;">
                        <button type="button" class="btn btn-label-secondary btn-sm mb-3" id="placesBackToSearch">
                            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to results') }}
                        </button>
                        <div id="placesDetailsSummary" class="mb-3"></div>
                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#placesApiResponseCollapse" aria-expanded="false">
                                {{ __('Full API response') }}
                            </button>
                            <div class="collapse mt-2" id="placesApiResponseCollapse">
                                <pre id="placesApiResponseJson" class="bg-dark text-light p-3 rounded small" style="max-height: 280px; overflow: auto; white-space: pre-wrap; word-break: break-all;"></pre>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary w-100 mt-2" id="placesUseForClientBtn">
                            <i class="ti ti-user-plus me-1"></i>{{ __('Use to create client') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <form id="placesUseForClientForm" method="POST" action="{{ route('places.use-for-client') }}" style="display: none;">
        @csrf
        <input type="hidden" name="place_id" id="placesUseForClientPlaceId">
    </form>
    @endcan

    <div class="card">
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    @can('create', \App\Models\Enterprise::class)
    <script>
    (function() {
        var form = document.getElementById('placesSearchForm');
        var queryInput = document.getElementById('placesTextQuery');
        var searchBtn = document.getElementById('placesSearchBtn');
        var resultsContainer = document.getElementById('placesResultsContainer');
        var loadingEl = document.getElementById('placesLoading');
        var emptyEl = document.getElementById('placesEmpty');
        var useForm = document.getElementById('placesUseForClientForm');
        var placeIdInput = document.getElementById('placesUseForClientPlaceId');
        var errorEl = document.getElementById('placesError');
        var searchStep = document.getElementById('placesSearchStep');
        var detailsStep = document.getElementById('placesDetailsStep');
        var detailsSummary = document.getElementById('placesDetailsSummary');
        var apiResponseJson = document.getElementById('placesApiResponseJson');
        var backBtn = document.getElementById('placesBackToSearch');
        var useForClientBtn = document.getElementById('placesUseForClientBtn');
        var debounceTimer;
        var selectedPlaceId = null;

        if (!form) return;

        function showLoading(show) {
            loadingEl.style.display = show ? 'block' : 'none';
            if (show) {
                resultsContainer.style.display = 'none';
                emptyEl.style.display = 'none';
                if (errorEl) errorEl.style.display = 'none';
            }
        }

        function showError(message) {
            loadingEl.style.display = 'none';
            emptyEl.style.display = 'none';
            resultsContainer.style.display = 'none';
            if (errorEl) {
                errorEl.textContent = message || 'Error searching. Try again.';
                errorEl.style.display = 'block';
            }
        }

        function showResults(places) {
            loadingEl.style.display = 'none';
            emptyEl.style.display = 'none';
            if (errorEl) errorEl.style.display = 'none';
            resultsContainer.style.display = 'block';
            resultsContainer.innerHTML = '';
            if (!places || places.length === 0) {
                resultsContainer.innerHTML = '<div class="list-group-item text-muted">' + (typeof __placesNoResults !== 'undefined' ? __placesNoResults : 'No results.') + '</div>';
                return;
            }
            places.forEach(function(p) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.innerHTML = '<div class="fw-medium">' + escapeHtml(p.name) + '</div><small class="text-muted">' + escapeHtml(p.formatted_address) + '</small>';
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectedPlaceId = p.id;
                    loadPlaceDetails(p.id);
                });
                resultsContainer.appendChild(a);
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSearchStep() {
            if (searchStep) searchStep.style.display = 'block';
            if (detailsStep) detailsStep.style.display = 'none';
        }

        function showDetailsStep() {
            if (searchStep) searchStep.style.display = 'none';
            if (detailsStep) detailsStep.style.display = 'block';
        }

        function loadPlaceDetails(placeId) {
            showLoading(true);
            var url = '{{ url("/places/details") }}/' + encodeURIComponent(placeId);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    showLoading(false);
                    if (data.message && !data.name) {
                        showError(data.message);
                        return;
                    }
                    renderPlaceDetails(data);
                    showDetailsStep();
                })
                .catch(function() {
                    showLoading(false);
                    showError('Connection error. Check your network and try again.');
                });
        }

        function renderPlaceDetails(data) {
            var lines = [];
            if (data.name) lines.push('<strong>' + escapeHtml(data.name) + '</strong>');
            if (data.formatted_address || data.address) lines.push(escapeHtml(data.formatted_address || data.address));
            if (data.phone) lines.push(escapeHtml(data.phone));
            if (data.website) lines.push('<a href="' + escapeHtml(data.website) + '" target="_blank" rel="noopener">' + escapeHtml(data.website) + '</a>');
            if (data.locality || data.postal_code) lines.push((data.postal_code || '') + ' ' + (data.locality || ''));
            detailsSummary.innerHTML = lines.length ? '<div class="small">' + lines.join('<br>') + '</div>' : '';
            apiResponseJson.textContent = data.api_response
                ? JSON.stringify(data.api_response, null, 2)
                : JSON.stringify(data, null, 2);
            var collapse = document.getElementById('placesApiResponseCollapse');
            if (collapse && window.bootstrap && bootstrap.Collapse) {
                var bsCollapse = bootstrap.Collapse.getInstance(collapse);
                if (bsCollapse) bsCollapse.hide();
            }
        }

        function doSearch() {
            var q = (queryInput && queryInput.value) ? queryInput.value.trim() : '';
            if (q.length === 0) {
                resultsContainer.style.display = 'none';
                loadingEl.style.display = 'none';
                emptyEl.style.display = 'block';
                return;
            }
            showLoading(true);
            var url = '{{ route("places.search") }}?text_query=' + encodeURIComponent(q);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) {
                    return r.json().then(function(data) {
                        if (!r.ok) {
                            showError(data.message || 'Error ' + r.status);
                            return;
                        }
                        showResults(data.places || []);
                    });
                })
                .catch(function(err) {
                    showError('Connection error. Check your network and try again.');
                });
        }

        if (backBtn) backBtn.addEventListener('click', showSearchStep);

        if (useForClientBtn) useForClientBtn.addEventListener('click', function() {
            if (selectedPlaceId) {
                placeIdInput.value = selectedPlaceId;
                useForm.submit();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            doSearch();
        });

        queryInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                if (queryInput.value.trim().length >= 2) doSearch();
            }, 400);
        });

        document.getElementById('placesSearchModal').addEventListener('show.bs.modal', function() {
            showSearchStep();
            emptyEl.style.display = 'block';
            resultsContainer.style.display = 'none';
            loadingEl.style.display = 'none';
            if (errorEl) errorEl.style.display = 'none';
        });

        function removeModalBackdrops() {
            document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        document.getElementById('placesSearchModal').addEventListener('hidden.bs.modal', removeModalBackdrops);
    })();
    </script>
    @endcan
@endpush

@section('vendor-script')
    <script src="{{ asset('vendors/data-tables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendors/data-tables/extensions/responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
    <script src="{{ asset('vendors/fullcalendar/lib/moment.min.js') }}"></script>
    <script src="{{ asset('js/moment/' . app()->getLocale() . '.js') }}"></script>
@endsection
