@extends('layouts/layoutMaster')

@section('title', __('Campaña'))

@section('content')
@php
    /** @var array<string, int|float> $deliveryStats */
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Campañas de correo') }}/</span> {{ $campaign->name }}
        </h4>
        <p class="text-muted">{{ __('Envíos, entregas y engagement de esta campaña.') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
        <a href="{{ route('campaigns.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Volver') }}
        </a>
        <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>{{ __('Editar') }}
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Resumen') }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">{{ __('Tipo') }}</dt>
                    <dd class="col-sm-7">{{ $campaign->typeLabel() }}</dd>
                    <dt class="col-sm-5 text-muted">{{ __('Estado') }}</dt>
                    <dd class="col-sm-7">
                        <span class="badge {{ $campaign->statusBadgeClasses() }}">{{ $campaign->statusLabel() }}</span>
                    </dd>
                    @if ($campaign->summary)
                        <dt class="col-sm-5 text-muted">{{ __('Descripción') }}</dt>
                        <dd class="col-sm-7">{{ $campaign->summary }}</dd>
                    @endif
                    @if ($campaign->scheduled_at)
                        <dt class="col-sm-5 text-muted">{{ __('Programado') }}</dt>
                        <dd class="col-sm-7">{{ $campaign->scheduled_at->translatedFormat('d M Y H:i') }}</dd>
                    @endif
                    @if ($campaign->sent_at)
                        <dt class="col-sm-5 text-muted">{{ __('Enviado') }}</dt>
                        <dd class="col-sm-7">{{ $campaign->sent_at->translatedFormat('d M Y H:i') }}</dd>
                    @endif
                </dl>
                @if ($campaign->sends_count !== null || $campaign->opened_rate !== null)
                    <hr class="my-3">
                    <p class="text-muted small mb-1">{{ __('Métricas guardadas en la campaña') }}</p>
                    <dl class="row mb-0 small">
                        @if ($campaign->sends_count !== null)
                            <dt class="col-sm-6 text-muted">{{ __('Envíos (snapshot)') }}</dt>
                            <dd class="col-sm-6">{{ number_format((int) $campaign->sends_count) }}</dd>
                        @endif
                        @if ($campaign->opened_rate !== null)
                            <dt class="col-sm-6 text-muted">{{ __('Aperturas %') }}</dt>
                            <dd class="col-sm-6">{{ rtrim(rtrim(number_format((float) $campaign->opened_rate, 2, '.', ''), '0'), '.') }}%</dd>
                        @endif
                        @if ($campaign->clicked_rate !== null)
                            <dt class="col-sm-6 text-muted">{{ __('Clics %') }}</dt>
                            <dd class="col-sm-6">{{ rtrim(rtrim(number_format((float) $campaign->clicked_rate, 2, '.', ''), '0'), '.') }}%</dd>
                        @endif
                        @if ($campaign->unsubscribed_rate !== null)
                            <dt class="col-sm-6 text-muted">{{ __('Bajas %') }}</dt>
                            <dd class="col-sm-6">{{ rtrim(rtrim(number_format((float) $campaign->unsubscribed_rate, 2, '.', ''), '0'), '.') }}%</dd>
                        @endif
                    </dl>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Estadísticas de envíos') }}</h5>
                <small class="text-muted">{{ __('Datos en tiempo real') }}</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-primary rounded">
                                    <i class="ti ti-mail"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['total']) }}</h6>
                                <small class="text-muted">{{ __('Envíos') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="ti ti-users"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['unique_recipients']) }}</h6>
                                <small class="text-muted">{{ __('Destinatarios') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-success rounded">
                                    <i class="ti ti-send"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['sent']) }}</h6>
                                <small class="text-muted">{{ __('Enviados') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-info rounded">
                                    <i class="ti ti-check"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['delivered']) }}</h6>
                                <small class="text-muted">{{ __('Entregados') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="ti ti-eye"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['opened']) }}</h6>
                                <small class="text-muted">{{ __('Abiertos') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="ti ti-click"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['clicked']) }}</h6>
                                <small class="text-muted">{{ __('Clics') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-danger rounded">
                                    <i class="ti ti-alert-circle"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['bounced']) }}</h6>
                                <small class="text-muted">{{ __('Rebotes') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-danger rounded">
                                    <i class="ti ti-flag"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['complained']) }}</h6>
                                <small class="text-muted">{{ __('Quejas') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-danger rounded">
                                    <i class="ti ti-x"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['failed']) }}</h6>
                                <small class="text-muted">{{ __('Fallidos') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="ti ti-clock"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['pending']) }}</h6>
                                <small class="text-muted">{{ __('Pendientes') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-primary rounded">
                                    <i class="ti ti-percentage"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['open_rate'], 2) }}%</h6>
                                <small class="text-muted">{{ __('Tasa apertura') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-initial bg-label-primary rounded">
                                    <i class="ti ti-percentage"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0">{{ number_format($deliveryStats['click_rate'], 2) }}%</h6>
                                <small class="text-muted">{{ __('Tasa clics') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($campaign->messages->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Mensajes vinculados') }}</h5>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @foreach ($campaign->messages as $message)
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fw-medium">{{ $message->name }}</span>
                        <a href="{{ route('message.show', $message->id) }}" class="btn btn-sm btn-label-primary">
                            <i class="ti ti-external-link me-1"></i>{{ __('Ver mensaje') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@endsection
