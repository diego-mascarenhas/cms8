
<div class="row g-4">
    <div class="col-lg-8">
        <div class="row g-4">
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
                        <h6 class="card-title mb-1">Semana pasada</h6>
                        <h4 class="card-title mb-1">0€</h4>
                        <small class="text-danger fw-semibold"><i class="ti ti-arrow-down-right"></i> 0%</small>
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
                        <h6 class="card-title mb-1">Acumulado</h6>
                        <h4 class="card-title mb-1">0€</h4>
                        <small class="text-success fw-semibold"><i class="ti ti-arrow-up-right"></i> 0%</small>
                    </div>
                </div>
            </div>

            <!-- Files -->
            <div class="col-12 opacity-50">
                <div class="card">
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