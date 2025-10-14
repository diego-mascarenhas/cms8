<div class="position-relative w-100">
    <!-- Search Input -->
    <div class="nav-item d-flex align-items-center w-100">
        <i class="ti ti-search ti-md me-2 me-lg-0"></i>
        <input
            type="text"
            class="form-control border-0 shadow-none ps-1 ps-sm-2"
            placeholder="Buscar..."
            aria-label="Buscar..."
            wire:model.live.debounce.300ms="query"
            wire:focus="$set('showResults', true)"
        >
    </div>

    <!-- Search Results Dropdown -->
    @if($showResults && !empty($query))
        <div class="navbar-search-suggestion position-absolute top-100 start-0" style="z-index: 2000; top: calc(100% + 20px); left: 0; width: 900px;">
            <div class="card">
                <div class="card-body p-0">
                    @if(empty(array_filter($results)))
                        <!-- No Results -->
                        <div class="text-center py-4">
                            <i class="ti ti-search fs-1 text-muted mb-3"></i>
                            <h6 class="text-muted">No se encontraron resultados</h6>
                            <p class="text-muted mb-0">Intenta con otros términos de búsqueda</p>
                        </div>
                    @else
                        <!-- Contacts -->
                        @if(!empty($results['contacts']))
                            <div class="px-3 py-2">
                                <h6 class="suggestions-header text-primary mb-2">Contactos</h6>
                                @foreach($results['contacts'] as $contact)
                                    <a href="{{ $contact['url'] }}" class="suggestion d-flex justify-content-between px-3 py-2 w-100 text-decoration-none" style="pointer-events: auto; z-index: 1;">
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-user me-2"></i>
                                            <div class="user-info">
                                                <h6 class="mb-0">{{ $contact['name'] }}</h6>
                                                <small class="text-muted">{{ $contact['subtitle'] }}</small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Enterprises -->
                        @if(!empty($results['enterprises']))
                            <div class="px-3 py-2">
                                <h6 class="suggestions-header text-primary mb-2">Empresas</h6>
                                @foreach($results['enterprises'] as $enterprise)
                                    <a href="{{ $enterprise['url'] }}" class="suggestion d-flex justify-content-between px-3 py-2 w-100 text-decoration-none" style="pointer-events: auto; z-index: 1;">
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-building me-2"></i>
                                            <div class="user-info">
                                                <h6 class="mb-0">{{ $enterprise['name'] }}</h6>
                                                <small class="text-muted">{{ $enterprise['subtitle'] }}</small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Services -->
                        @if(!empty($results['services']))
                            <div class="px-3 py-2">
                                <h6 class="suggestions-header text-primary mb-2">Servicios</h6>
                                @foreach($results['services'] as $service)
                                    <a href="{{ $service['url'] }}" class="suggestion d-flex justify-content-between px-3 py-2 w-100 text-decoration-none" style="pointer-events: auto; z-index: 1;">
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-world me-2"></i>
                                            <div class="user-info">
                                                <h6 class="mb-0">{{ $service['name'] }}</h6>
                                                <small class="text-muted">{{ $service['subtitle'] }}</small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Projects -->
                        @if(!empty($results['projects']))
                            <div class="px-3 py-2">
                                <h6 class="suggestions-header text-primary mb-2">Proyectos</h6>
                                @foreach($results['projects'] as $project)
                                    <a href="{{ $project['url'] }}" class="suggestion d-flex justify-content-between px-3 py-2 w-100 text-decoration-none" style="pointer-events: auto; z-index: 1;">
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-folder me-2"></i>
                                            <div class="user-info">
                                                <h6 class="mb-0">{{ $project['name'] }}</h6>
                                                <small class="text-muted">{{ $project['subtitle'] }}</small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Invoices -->
                        @if(!empty($results['invoices']))
                            <div class="px-3 py-2">
                                <h6 class="suggestions-header text-primary mb-2">Facturas</h6>
                                @foreach($results['invoices'] as $invoice)
                                    <a href="{{ $invoice['url'] }}" class="suggestion d-flex justify-content-between px-3 py-2 w-100 text-decoration-none" style="pointer-events: auto; z-index: 1;">
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-file-invoice me-2"></i>
                                            <div class="user-info">
                                                <h6 class="mb-0">{{ $invoice['name'] }}</h6>
                                                <small class="text-muted">{{ $invoice['subtitle'] }}</small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('livewire:initialized', () => {
    let psSearch;

    // Close dropdown when clicking outside
    document.addEventListener('mousedown', function(event) {
        const searchInput = document.querySelector('[wire\\:model\\.live\\.debounce\\.300ms="query"]');
        const searchWrapper = searchInput?.closest('.navbar-search-wrapper');
        const dropdown = document.querySelector('.navbar-search-suggestion');

        // Check if click is outside search area AND not on the dropdown itself
        if (searchWrapper && !searchWrapper.contains(event.target) &&
            dropdown && !dropdown.contains(event.target)) {
            @this.set('showResults', false);
        }
    });

    // Initialize Perfect Scrollbar when dropdown appears
    Livewire.hook('morph.updated', () => {
        const dropdown = document.querySelector('.navbar-search-suggestion');
        if (dropdown && typeof PerfectScrollbar !== 'undefined') {
            if (psSearch) {
                psSearch.destroy();
            }
            psSearch = new PerfectScrollbar(dropdown, {
                wheelPropagation: false,
                suppressScrollX: true
            });
        }
    });
});
</script>
@endpush
