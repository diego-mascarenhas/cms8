@extends('layouts/layoutMaster')

@section('title', __('payment_sync.mercadopago.index_title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('payment_sync.mercadopago.index_title') }}</h4>
        <p class="text-muted">{{ __('payment_sync.mercadopago.index_subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('payment_sync.mercadopago.back_payments') }}
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

<div class="card">
    <div class="card-body">
        @if ($syncs->isEmpty())
            <p class="text-muted mb-0">{{ __('payment_sync.mercadopago.empty') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('payment_sync.mercadopago.columns.date') }}</th>
                            <th>{{ __('payment_sync.mercadopago.columns.amount') }}</th>
                            <th>{{ __('payment_sync.mercadopago.columns.payer') }}</th>
                            <th>{{ __('payment_sync.mercadopago.columns.description') }}</th>
                            <th>{{ __('payment_sync.mercadopago.columns.external_id') }}</th>
                            <th class="text-center">{{ __('payment_sync.mercadopago.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($syncs as $sync)
                            @php
                                $currency = strtoupper((string) $sync->currency);
                                $cents = (int) $sync->amount_net_cents;
                                $amount = in_array($currency, ['CLP', 'UYU', 'PYG'], true)
                                    ? (float) $cents
                                    : round($cents / 100, 2);
                            @endphp
                            <tr>
                                <td>{{ $sync->charge_created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ number_format($amount, 2, ',', '.') }} {{ $currency }}</td>
                                <td>
                                    @if ($sync->lacksIdentifiablePayer())
                                        <span class="text-muted">{{ __('payment_sync.mercadopago.payer_unknown') }}</span>
                                    @else
                                        <span class="text-nowrap">
                                            @if ($sync->customer_id)
                                                <code>{{ $sync->customer_id }}</code>
                                            @endif
                                            @if ($sync->customer_id && $sync->customer_email)
                                                <span class="text-muted"> · </span>
                                            @endif
                                            @if ($sync->customer_email)
                                                <span class="text-muted">{{ $sync->customer_email }}</span>
                                            @endif
                                            @if (! $sync->customer_id && ! $sync->customer_email)
                                                —
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $sync->description ?: '—' }}</td>
                                <td><code>{{ $sync->external_id }}</code></td>
                                <td class="text-center">
                                    <a href="{{ route('payments.syncs.mercadopago.assign', $sync) }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-link me-1"></i>{{ __('payment_sync.mercadopago.assign_action') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $syncs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
