@extends('layouts/layoutMaster')

@section('title', 'Cuentas de pago')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Cuentas de pago</h4>
        <p class="text-muted">Configura las cuentas de tu empresa y las formas de pago que acepta cada una.</p>
    </div>
    @can('create', \App\Models\PaymentAccount::class)
        <div class="mt-3 mt-md-0">
            <a href="{{ route('payment-account.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Nueva cuenta
            </a>
        </div>
    @endcan
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Código</th>
                    <th>Moneda</th>
                    <th>Formas de pago aceptadas</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->code }}</td>
                        <td>{{ strtoupper((string) ($account->currency->code ?? '')) }}</td>
                        <td>
                            @if ($account->paymentTypes->isEmpty())
                                <span class="text-muted">Todas las formas activas</span>
                            @else
                                {{ $account->paymentTypes->map(fn ($type) => $type->display_name)->join(', ') }}
                            @endif
                        </td>
                        <td>
                            @if ((int) $account->status === 1)
                                <span class="badge bg-label-success">Activa</span>
                            @else
                                <span class="badge bg-label-secondary">Inactiva</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @can('update', $account)
                                <a href="{{ route('payment-account.edit', $account) }}" class="text-body" title="Editar">
                                    <i class="ti ti-edit ti-sm"></i>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No hay cuentas de pago configuradas.
                            @can('create', \App\Models\PaymentAccount::class)
                                <a href="{{ route('payment-account.create') }}">Crear la primera cuenta</a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
