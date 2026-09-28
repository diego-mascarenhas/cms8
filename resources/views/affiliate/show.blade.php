@extends('layouts/layoutMaster')

@section('title', $team->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Afiliados&nbsp;/</span> {{ $team->name }}</h4>
        <p class="text-muted">Referidos de este afiliado y las comisiones que le corresponden.</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('affiliate.index') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-1"></i> Volver</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <span class="text-muted d-block">Afiliado</span>
                <span class="fw-medium">{{ $team->name }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted d-block">Email</span>
                <span>{{ $team->owner?->email ?? '—' }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted d-block">Código de referido</span>
                <span>{{ $team->stripe_id ?: '—' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <h5 class="card-title mb-1">Referidos</h5>
            <p class="text-muted mb-0">Cada código cus_ o sub_ queda asociado a este afiliado.</p>
        </div>
        @if ($canAssign)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignCodeModal">
                <i class="ti ti-plus me-1"></i> Asignar código
            </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Referido</th>
                    <th>Códigos</th>
                    <th class="text-end">Porcentaje</th>
                    <th class="text-end">Comisión</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($referrals as $referral)
                    <tr class="align-top">
                        <td>
                            <div class="d-flex flex-column">
                                <span>{{ $referral['name'] }}</span>
                                <small class="text-muted">{{ $referral['email'] ?? '—' }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                @foreach ($referral['codes'] as $code)
                                    <span>
                                        {{ $code['code'] }}@if ($code['plan']) <span class="text-muted">({{ $code['plan'] }})</span>@endif
                                        @if ($code['subscriber'])
                                            <span class="text-muted small">· {{ $code['subscriber'] }}</span>
                                        @endif
                                        @if ($code['direct_percent'])
                                            <span class="text-muted small">· Percibe {{ rtrim(rtrim(number_format((float) $code['direct_percent'], 2, ',', '.'), '0'), ',') }}%</span>
                                        @endif
                                        @if ($code['assignee'])
                                            <span class="text-muted small">· Asignado a {{ $code['assignee'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-column align-items-end gap-2">
                                @foreach ($referral['codes'] as $code)
                                    <span>{{ rtrim(rtrim(number_format((float) $code['percent'], 2, ',', '.'), '0'), ',') }}%</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-end">
                            @if ($referral['commissions_by_currency'] === [])
                                <span class="text-muted">—</span>
                            @else
                                @foreach ($referral['commissions_by_currency'] as $currency => $cents)
                                    <div>{{ $currency }} {{ number_format($cents / 100, 2, ',', '.') }}</div>
                                @endforeach
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex flex-column align-items-center gap-2">
                                @foreach ($referral['codes'] as $code)
                                    @if ($code['can_unlink'])
                                        <a href="javascript:;" class="text-secondary" title="Desvincular" onclick="confirmUnlinkReferral(@js($code['code']))">
                                            <i class="ti ti-trash ti-sm"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Este afiliado todavía no tiene referidos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@unless ($canAssign)
    <div class="alert alert-warning">Este equipo no puede recibir códigos de referido. Necesita un cliente Stripe (cus_) y no puede ser, a su vez, un referido.</div>
@endunless

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Comisiones</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Referido</th>
                    <th>Factura</th>
                    <th class="text-end">Cobro</th>
                    <th class="text-end">Porcentaje</th>
                    <th class="text-end">Comisión</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($commissions as $commission)
                    <tr>
                        <td>{{ $commission->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $commission->payingTeam?->name ?? '—' }}</td>
                        <td>{{ $commission->stripe_invoice_id }}</td>
                        <td class="text-end">{{ strtoupper((string) $commission->currency) }} {{ number_format($commission->amount_paid_cents / 100, 2, ',', '.') }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $commission->commission_percent, 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="text-end">{{ strtoupper((string) $commission->currency) }} {{ number_format($commission->commission_amount_cents / 100, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Todavía no hay comisiones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<form id="unlink-referral-form" method="POST" action="{{ route('affiliate.referrals.destroy', $team) }}" class="d-none">
    @csrf
    @method('DELETE')
    <input type="hidden" name="subscription_code" id="unlink-subscription-code" value="">
</form>

@if ($canAssign)
    <div class="modal fade" id="assignCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('affiliate.store') }}">
                @csrf
                <input type="hidden" name="team_id" value="{{ $team->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar código</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <x-input-general id="subscription_code" label="Código cus_ o sub_ (*)" value="{{ old('subscription_code') }}" maxlength="64" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@section('page-script')
<script>
    function confirmUnlinkReferral(code) {
        Swal.fire({
            title: '¿Desvincular este código?',
            text: code,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Desvincular',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            document.getElementById('unlink-subscription-code').value = code;
            document.getElementById('unlink-referral-form').submit();
        });
    }

    @if ($errors->has('subscription_code') || $errors->has('team_id'))
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('assignCodeModal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    @endif
</script>
@endsection
