<div class="row g-4">
    <div class="col-lg-8">
        <div class="row g-4">
            @can('invoice.list')
            <!-- CAC Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-danger">
                                    <i class="ti ti-credit-card"></i>
                                </span>
                            </div>
                            <span>CAC</span>
                        </div>
                        <h6 class="card-title mb-1">Coste de adquisición</h6>
                        <h4 class="card-title mb-1">{{ $stripeData['metrics']['cac'] ?? '0.00' }}€</h4>
                        @if(isset($stripeData['metrics']['cac_trend']))
                            <small class="{{ $stripeData['metrics']['cac_trend'] > 0 ? 'text-danger' : 'text-success' }} fw-semibold">
                                <i class="ti ti-arrow-{{ $stripeData['metrics']['cac_trend'] > 0 ? 'up' : 'down' }}-right"></i>
                                {{ abs($stripeData['metrics']['cac_trend']) }}%
                            </small>
                        @else
                            <small class="text-muted fw-semibold">Sin datos previos</small>
                        @endif
                    </div>
                </div>
            </div>

            <!-- LTV Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="ti ti-currency-dollar"></i>
                                </span>
                            </div>
                            <span>LTV</span>
                        </div>
                        <h6 class="card-title mb-1">Valor del tiempo de vida</h6>
                        <h4 class="card-title mb-1">{{ $stripeData['metrics']['ltv'] ?? '0.00' }}€</h4>
                        @if(isset($stripeData['metrics']['ltv_trend']))
                            <small class="{{ $stripeData['metrics']['ltv_trend'] > 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                <i class="ti ti-arrow-{{ $stripeData['metrics']['ltv_trend'] > 0 ? 'up' : 'down' }}-right"></i>
                                {{ abs($stripeData['metrics']['ltv_trend']) }}%
                            </small>
                        @else
                            <small class="text-muted fw-semibold">Sin datos previos</small>
                        @endif
                    </div>
                </div>
            </div>
            @endcan

            <!-- Astral Profile -->
            @if($astralProfile)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-stars me-2"></i>
                            Perfil Astrológico
                        </h5>
                        <span class="badge bg-label-primary">
                            {{ $astralProfile['zodiac']['symbol'] }} {{ $astralProfile['zodiac']['sign'] }}
                        </span>
                    </div>
                    <div class="card-body">
                        <!-- Zodiac & North Node Info -->
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="{{ isset($astralProfile['human_design']) ? 'col-md-4' : 'col-md-6' }}">
                                <small class="text-muted d-block">Signo Solar</small>
                                <strong>{{ $astralProfile['zodiac']['sign'] }}</strong>
                                <span class="badge bg-label-{{ $astralProfile['zodiac']['element'] === 'Fuego' ? 'danger' : ($astralProfile['zodiac']['element'] === 'Tierra' ? 'success' : ($astralProfile['zodiac']['element'] === 'Aire' ? 'info' : 'primary')) }} ms-2">
                                    {{ $astralProfile['zodiac']['element'] }}
                                </span>
                            </div>
                            <div class="{{ isset($astralProfile['human_design']) ? 'col-md-4' : 'col-md-6' }}">
                                <small class="text-muted d-block">Nodo Norte</small>
                                <strong>{{ $astralProfile['north_node']['north'] }}</strong>
                            </div>
                            @if(isset($astralProfile['human_design']))
                            <div class="col-md-4">
                                <small class="text-muted d-block">Diseño Humano</small>
                                @foreach($astralProfile['human_design']['top_types'] as $index => $type)
                                    <div class="d-flex align-items-center {{ $index > 0 ? 'mt-1' : '' }}">
                                        <span class="badge bg-label-{{ $index === 0 ? 'warning' : 'secondary' }} me-2">
                                            {{ $type['probability'] }}%
                                        </span>
                                        <small class="{{ $index === 0 ? 'fw-bold' : '' }}">{{ $type['type'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        <!-- Human Design Types Details -->
                        @if(isset($astralProfile['human_design']))
                        <div class="mb-3 pb-3 border-bottom">
                            <small class="text-muted d-block mb-2">
                                <i class="ti ti-alert-circle ti-xs me-1"></i>
                                Tipos probables de Diseño Humano (estimación):
                            </small>
                            @foreach($astralProfile['human_design']['top_types'] as $type)
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-sm">{{ $type['type'] }}</strong>
                                        <span class="badge bg-label-primary">{{ $type['probability'] }}%</span>
                                    </div>
                                    <p class="text-muted mb-0" style="font-size: 0.875rem;">
                                        {{ $type['description'] }}
                                    </p>
                                </div>
                            @endforeach
                            <div class="alert alert-warning mt-2 mb-0 py-2 px-3" role="alert" style="font-size: 0.8rem;">
                                <i class="ti ti-info-circle me-1"></i>
                                <strong>Nota:</strong> {{ $astralProfile['human_design']['disclaimer'] }}
                                <a href="javascript:;" class="alert-link" data-bs-toggle="modal" data-bs-target="#astralDataModal">Completar datos aquí</a>.
                            </div>
                        </div>
                        @endif

                        <!-- AI Interpretation -->
                        <div class="mb-3">
                            <p class="mb-0" style="line-height: 1.6; text-align: justify;">
                                {{ $astralProfile['interpretation'] }}
                            </p>
                        </div>

                        <!-- Metadata -->
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <small class="text-muted">
                                <i class="ti ti-calendar ti-xs me-1"></i>
                                {{ $astralProfile['birth_date'] }} ({{ $astralProfile['age'] }} años)
                            </small>
                            <small class="text-muted">
                                <i class="ti ti-sparkles ti-xs me-1"></i>
                                Generado con AI
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Notes -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notas</h5>
                <button class="btn btn-warning btn-xs" onclick="toggleNotesEdit()">
                    <i class="ti ti-device-floppy me-1"></i>Guardar
                </button>
            </div>
            <div class="card-body">
                <textarea id="contact-notes" class="form-control" rows="10" placeholder="Escribe tus notas aquí...">{{ $data->data->notes ?? '' }}</textarea>
            </div>
        </div>
    </div>
</div>
