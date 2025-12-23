<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Envíos</h5>
            <div class="d-flex align-items-center gap-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="form-control form-control-sm"
                    placeholder="Buscar..."
                    style="width: 200px;">
                <div wire:loading.delay>
                    <span class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(count($deliveries) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>CONTACTO</th>
                                <th>ESTADO DE ENTREGA</th>
                                <th class="text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deliveries as $delivery)
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $delivery['contact_name'] }}</strong><br>
                                            <small class="text-muted">{{ $delivery['contact_email'] }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($delivery['status_text'] === 'Fallido')
                                            <span class="text-danger">
                                                <i class="ti ti-x me-1"></i>
                                                Fallido
                                            </span><br>
                                            @if($delivery['sent_at'])
                                                <small class="text-muted">Intentado: {{ $delivery['sent_at'] }}</small>
                                            @endif
                                        @elseif($delivery['delivered_at'])
                                            <span class="text-info">
                                                <i class="ti ti-check me-1"></i>
                                                Entregado: {{ $delivery['delivered_at'] }}
                                            </span><br>
                                            <small class="text-muted">Enviado: {{ $delivery['sent_at'] }}</small>
                                        @elseif($delivery['sent_at'])
                                            @if($delivery['status_text'] === 'Programado')
                                                <span class="text-warning">
                                                    <i class="ti ti-clock me-1"></i>
                                                    Programado: {{ $delivery['sent_at'] }}
                                                </span>
                                            @else
                                                <span class="text-success">
                                                    <i class="ti ti-send me-1"></i>
                                                    Enviado: {{ $delivery['sent_at'] }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted">
                                                <i class="ti ti-clock me-1"></i>
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center align-items-center">
                                            @if($delivery['has_opened'])
                                                <i class="ti ti-eye text-success me-2" title="Abierto"></i>
                                            @endif
                                            @if($delivery['has_clicked'])
                                                <i class="ti ti-mouse text-primary me-2" title="Clickeado"></i>
                                            @endif
                                            @if($delivery['status_text'] !== 'Programado')
                                                <a href="javascript:;" class="text-primary" onclick="resendDelivery({{ $delivery['id'] }})" title="Reenviar">
                                                    <i class="ti ti-refresh ti-sm"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-3">
                    {{ $deliveries->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="ti ti-inbox ti-lg text-muted"></i>
                    <p class="text-muted mt-2">No se encontraron envíos</p>
                </div>
            @endif
        </div>
    </div>
</div>
