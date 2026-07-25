@extends('layouts/layoutMaster')

@section('title', __('payment_sync.mercadopago.auto_assign.title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('payment_sync.mercadopago.auto_assign.title') }}</h4>
        <p class="text-muted">{{ __('payment_sync.mercadopago.auto_assign.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.syncs.mercadopago.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($done)
    <div class="card">
        <div class="card-body">
            <h5 class="mb-2">{{ __('payment_sync.mercadopago.auto_assign.done_title') }}</h5>
            <p class="text-muted mb-3">
                {{ __('payment_sync.mercadopago.auto_assign.done_summary', [
                    'accepted' => $accepted,
                    'skipped' => $skipped,
                ]) }}
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]) }}" class="btn btn-primary">
                    <i class="ti ti-refresh me-1"></i>{{ __('payment_sync.mercadopago.auto_assign.rebuild') }}
                </a>
                <a href="{{ route('payments.syncs.mercadopago.index') }}" class="btn btn-label-secondary">
                    <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
@else
    <div class="mb-3 text-muted">
        {{ __('payment_sync.mercadopago.auto_assign.progress', [
            'current' => $index + 1,
            'total' => $total,
        ]) }}
        · {{ __('payment_sync.mercadopago.auto_assign.accepted_count', ['count' => $accepted]) }}
        · {{ __('payment_sync.mercadopago.auto_assign.skipped_count', ['count' => $skipped]) }}
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ __('payment_sync.mercadopago.section_payment') }}</h5>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.date') }}</dt>
                <dd class="col-sm-9">{{ $current['payment_date'] ?? '—' }}</dd>
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.amount') }}</dt>
                <dd class="col-sm-9">{{ number_format((float) $current['amount'], 2, ',', '.') }} {{ $current['currency'] }}</dd>
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.external_id') }}</dt>
                <dd class="col-sm-9"><code>{{ $current['external_id'] }}</code></dd>
                @if (! empty($current['settlement_payer_name']))
                    <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.payer') }}</dt>
                    <dd class="col-sm-9">
                        {{ $current['settlement_payer_name'] }}
                        @if (! empty($current['settlement_payer_id_number']))
                            <div class="small text-muted">{{ $current['settlement_payer_id_number'] }}</div>
                        @endif
                    </dd>
                @endif
                @if (! empty($current['identification_code']))
                    <dt class="col-sm-3">{{ __('payment_sync.mercadopago.identification_code') }}</dt>
                    <dd class="col-sm-9"><code>{{ $current['identification_code'] }}</code></dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="card mb-4 border-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ __('payment_sync.mercadopago.auto_assign.suggestion_heading') }}</h5>
            <span class="badge bg-label-primary">
                {{ __('payment_sync.mercadopago.auto_assign.confidence', [
                    'percent' => (int) round(((float) $current['confidence']) * 100),
                ]) }}
            </span>
        </div>
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.enterprise_label') }}</dt>
                <dd class="col-sm-9">{{ $current['enterprise_name'] }}</dd>
                <dt class="col-sm-3">{{ __('Invoice') }}</dt>
                <dd class="col-sm-9">
                    @foreach ($current['invoice_ids'] as $invoiceIndex => $invoiceId)
                        <a href="{{ route('invoice.show', $invoiceId) }}" class="badge bg-label-secondary me-1 text-decoration-none">
                            {{ $current['invoice_numbers'][$invoiceIndex] ?? ('#'.$invoiceId) }}
                        </a>
                    @endforeach
                    @if (($current['kind'] ?? '') === 'paid_link')
                        <span class="badge bg-label-warning">{{ __('payment_sync.mercadopago.suggestion_paid_link') }}</span>
                    @endif
                </dd>
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.auto_assign.reason_label') }}</dt>
                <dd class="col-sm-9">{{ $current['reason'] }}</dd>
            </dl>

            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('payments.syncs.mercadopago.auto-assign.accept') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>{{ __('payment_sync.mercadopago.auto_assign.accept') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('payments.syncs.mercadopago.auto-assign.skip') }}">
                    @csrf
                    <button type="submit" class="btn btn-label-secondary">
                        <i class="ti ti-player-skip-forward me-1"></i>{{ __('payment_sync.mercadopago.auto_assign.skip') }}
                    </button>
                </form>
                @if (! empty($current['invoice_ids'][0]))
                    <a href="{{ route('invoice.show', $current['invoice_ids'][0]) }}" class="btn btn-label-info" target="_blank" rel="noopener noreferrer">
                        <i class="ti ti-file-invoice me-1"></i>{{ __('payment_sync.mercadopago.auto_assign.view_invoice') }}
                    </a>
                @endif
                <a href="{{ route('payments.syncs.mercadopago.assign', ['sync' => $current['sync_id'], 'enterprise_id' => $current['enterprise_id']]) }}" class="btn btn-label-primary">
                    <i class="ti ti-edit me-1"></i>{{ __('payment_sync.mercadopago.auto_assign.open_manual') }}
                </a>
            </div>
        </div>
    </div>
@endif
@endsection
