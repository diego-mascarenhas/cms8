@extends('layouts/layoutMaster')

@section('title', 'Actividad de IA')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
    <script>
        $(function () {
            $('#assistant-activity-table').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    emptyTable: 'No se encontró actividad del asistente para este rango.'
                }
            });
        });
    </script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Actividad de IA</h4>
        <p class="text-muted">Conversaciones del asistente del equipo, uso de tokens, modelo y costos estimados</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Mensajes del asistente</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalMessages) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Tokens totales</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalTokens) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Costo estimado (USD)</small>
                <h4 class="mb-0">${{ number_format($totalEstimatedCostUsd, 6) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Mensajes de conversaciones</h5>
            <small class="text-muted">Proveedor/modelo por defecto: {{ $defaultProvider }} / {{ $defaultModel }}</small>
        </div>
        <form method="GET" action="{{ route('assistant.activity') }}" class="d-flex align-items-end gap-2">
            <div>
                <label for="start_date" class="form-label mb-1">Desde</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>
            <div>
                <label for="end_date" class="form-label mb-1">Hasta</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary waves-effect waves-light">Aplicar</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table id="assistant-activity-table" class="table table-hover">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Conversación</th>
                    <th>Modelo</th>
                    <th class="text-end">Entrada</th>
                    <th class="text-end">Salida</th>
                    <th class="text-end">Tokens totales</th>
                    <th class="text-end">USD estimado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($messages as $message)
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                <small class="text-muted">{{ $message->created_at?->diffForHumans() }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->conversation?->user?->name ?? $message->user?->name ?? 'Desconocido' }}</span>
                                <small class="text-muted">{{ $message->conversation?->user?->email ?? $message->user?->email }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->conversation?->title ?? 'Sin título' }}</span>
                                <small class="text-muted">{{ $message->conversation_id }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $message->model_name }}</span>
                                <small class="text-muted">{{ $message->provider_name }}</small>
                            </div>
                        </td>
                        <td class="text-end">{{ number_format((int) $message->prompt_tokens_value) }}</td>
                        <td class="text-end">{{ number_format((int) $message->completion_tokens_value) }}</td>
                        <td class="text-end">{{ number_format((int) $message->total_tokens_value) }}</td>
                        <td class="text-end">${{ number_format((float) $message->estimated_cost_usd, 6) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No se encontró actividad del asistente para este equipo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
