@extends('layouts/layoutMaster')

@section('title', 'Suscripciones')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Cuentas/</span> {{ $team->name }}</h4>
            <p class="text-muted">Suscripciones activas</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="{{ route('account-management') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($team->subscriptions->isEmpty())
                <div class="text-center py-5">
                    <p class="text-muted">No hay suscripciones activas para este equipo.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Precio</th>
                                <th>Creada</th>
                                <th>Próxima facturación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($team->subscriptions as $subscription)
                                <tr>
                                    <td>{{ $subscription->name }}</td>
                                    <td>
                                        @if($subscription->stripe_status === 'active')
                                            <span class="badge bg-success">Activa</span>
                                        @elseif($subscription->stripe_status === 'canceled')
                                            <span class="badge bg-secondary">Cancelada</span>
                                        @elseif($subscription->stripe_status === 'past_due')
                                            <span class="badge bg-warning">Vencida</span>
                                        @else
                                            <span class="badge bg-info">{{ ucfirst($subscription->stripe_status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $subscription->stripe_price }}</td>
                                    <td>{{ $subscription->created_at->format('d/m/Y') }}</td>
                                    <td>
                                        @if($subscription->trial_ends_at)
                                            {{ $subscription->trial_ends_at->format('d/m/Y') }}
                                        @elseif($subscription->ends_at)
                                            {{ $subscription->ends_at->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
