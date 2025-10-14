<div class="position-relative">
    <!-- Search Input -->
    <div class="navbar-nav align-items-center">
        <div class="nav-item d-flex align-items-center">
            <div class="position-relative">
                <input
                    type="text"
                    class="form-control border-0 shadow-none ps-5 ps-sm-7"
                    placeholder="Buscar..."
                    wire:model.live.debounce.300ms="query"
                    wire:focus="$set('showResults', true)"
                    wire:blur="$set('showResults', false)"
                >
                <i class="ti ti-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            </div>
        </div>
    </div>

    <!-- Search Results Dropdown -->
    @if($showResults && !empty($query))
        <div class="navbar-search-suggestion position-absolute top-100 start-0 mt-0" style="z-index: 9999; width: 450px;">
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
                                    <a href="{{ $contact['url'] }}" class="d-flex align-items-center px-0 py-2 text-decoration-none hover-bg-light">
                                        <i class="ti ti-user me-2 text-muted"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">{{ $contact['name'] }}</h6>
                                            <small class="text-muted">{{ $contact['subtitle'] }}</small>
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
                                    <a href="{{ $enterprise['url'] }}" class="d-flex align-items-center px-0 py-2 text-decoration-none hover-bg-light">
                                        <i class="ti ti-building me-2 text-muted"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">{{ $enterprise['name'] }}</h6>
                                            <small class="text-muted">{{ $enterprise['subtitle'] }}</small>
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
                                    <a href="{{ $service['url'] }}" class="d-flex align-items-center px-0 py-2 text-decoration-none hover-bg-light">
                                        <i class="ti ti-world me-2 text-muted"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">{{ $service['name'] }}</h6>
                                            <small class="text-muted">{{ $service['subtitle'] }}</small>
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
                                    <a href="{{ $project['url'] }}" class="d-flex align-items-center px-0 py-2 text-decoration-none hover-bg-light">
                                        <i class="ti ti-folder me-2 text-muted"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">{{ $project['name'] }}</h6>
                                            <small class="text-muted">{{ $project['subtitle'] }}</small>
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
                                    <a href="{{ $invoice['url'] }}" class="d-flex align-items-center px-0 py-2 text-decoration-none hover-bg-light">
                                        <i class="ti ti-file-invoice me-2 text-muted"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">{{ $invoice['name'] }}</h6>
                                            <small class="text-muted">{{ $invoice['subtitle'] }}</small>
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

    <style>
    .hover-bg-light:hover {
        background-color: rgba(0,0,0,0.05);
    }
    .navbar-search-suggestion {
        max-height: 400px;
        overflow-y: auto;
    }
    </style>
</div>
