<div class="row g-4">
    <!-- CAC Card -->
    <div class="col-md-6 col-lg-4">
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
                <h6 class="card-title mb-1">Last week</h6>
                <h4 class="card-title mb-1">194,54€</h4>
                <small class="text-danger fw-semibold"><i class="ti ti-arrow-down-right"></i> 12.2%</small>
            </div>
        </div>
    </div>

    <!-- LTV Card -->
    <div class="col-md-6 col-lg-4">
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
                <h6 class="card-title mb-1">Acumulado</h6>
                <h4 class="card-title mb-1">2431,67€</h4>
                <small class="text-success fw-semibold"><i class="ti ti-arrow-up-right"></i> 25.2%</small>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Notas</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Objetivo:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Bloqueos:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Situación personal:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Dónde vive:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Estilo de vida:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Pasatiempos:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Deportes:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Relaciones personales:</dt>
                    <dd class="col-sm-9"></dd>

                    <dt class="col-sm-3">Relación con el dinero:</dt>
                    <dd class="col-sm-9"></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Emotional History -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Histórico emocional</h5>
                <button type="button" class="btn btn-primary btn-sm">
                    + Añadir informe
                </button>
            </div>
            <div class="card-body">
                <ul class="timeline mb-0">
                    @php
                        $emotionalHistory = [
                            ['date' => '09-07-2024', 'description' => 'Primer resultado conseguido', 'emoji' => '🥳'],
                            ['date' => '01-07-2024', 'description' => 'Agobio por sentirse perdida', 'emoji' => '😤'],
                            ['date' => '25-06-2024', 'description' => 'Tutoría para resolver un bloqueo', 'emoji' => '😅'],
                            ['date' => '18-06-2024', 'description' => 'Avanza bien', 'emoji' => '😊'],
                            ['date' => '11-06-2024', 'description' => 'Motivada, le cuesta el foco', 'emoji' => '🙂'],
                            ['date' => '04-06-2024', 'description' => 'Motivación a tope, cuidado', 'emoji' => '😁'],
                        ];
                    @endphp

                    @foreach($emotionalHistory as $entry)
                        <li class="timeline-item timeline-item-transparent">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">{{ $entry['date'] }}</h6>
                                    <small class="text-muted">{{ $entry['emoji'] }}</small>
                                </div>
                                <p class="mb-2">{{ $entry['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- Files -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Archivos</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-file-text me-2"></i>
                            <span>Contrato de alta Servicio Molón #1</span>
                            <small class="text-muted ms-auto">09-07-2024</small>
                        </div>
                    </li>
                    <li>
                        <div class="d-flex align-items-center">
                            <i class="ti ti-file-text me-2"></i>
                            <span>Briefing para conocerte</span>
                            <small class="text-muted ms-auto">09-07-2024</small>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>