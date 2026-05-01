@extends('layouts/layoutMaster')

@section('title', __('Campaña'))

@section('content')
@php
    /** @var array<string, int|float> $deliveryStats */
    /** @var array<int, array<int, array<string, mixed>>> $automationsGroupedByStepMessageId */
    $addMessageTemplateSelectQuery = array_filter([
        'type' => $campaign->type,
        'title' => $campaign->name,
        'campaign_id' => $campaign->id,
    ], fn ($value): bool => $value !== null && $value !== '');
    $classicEditorNewMessageUrl = route('campaigns.templates.select', $addMessageTemplateSelectQuery);
@endphp
@if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
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

@include('partials.email-smtp-dns-alerts')

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
                    @if ($campaign->created_at)
                        <dt class="col-sm-5 text-muted">{{ __('Creación') }}</dt>
                        <dd class="col-sm-7">{{ $campaign->created_at->translatedFormat('d M Y H:i') }}</dd>
                    @endif
                    @if ($campaign->updated_at)
                        <dt class="col-sm-5 text-muted">{{ __('Actualización') }}</dt>
                        <dd class="col-sm-7">{{ $campaign->updated_at->translatedFormat('d M Y H:i') }}</dd>
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

<div class="card card-action mb-4">
    <div class="card-header">
        <div class="card-action-title">
            <h5 class="mb-0">
                @if ($campaign->type === \App\Enums\CampaignType::Sequences->value)
                    {{ __('Secuencia de mensajes') }}
                @else
                    {{ __('Mensajes vinculados') }}
                @endif
            </h5>
        </div>
        <div class="card-action-element d-flex flex-wrap gap-2 justify-content-end">
            @if ($campaign->type === \App\Enums\CampaignType::Sequences->value && $campaign->messages->isNotEmpty())
                <a href="{{ $classicEditorNewMessageUrl }}" class="btn btn-sm btn-label-primary waves-effect waves-light fw-semibold">
                    <i class="ti ti-plus me-1"></i>{{ __('Añadir mensaje') }}
                </a>
            @endif
            <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-sm btn-label-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Editar campaña') }}
            </a>
        </div>
    </div>
    <div class="card-body">
        @if ($campaign->type === \App\Enums\CampaignType::Sequences->value)
            <p class="text-muted small mb-4">
                {{ __('Define en cada paso el orden, la espera, la condición y, si aplica, una automatización en otro canal. Título y zona horaria:') }}
                <a href="{{ route('campaigns.edit', $campaign) }}">{{ __('Editar campaña') }}</a>.
            </p>
        @endif

        @if ($campaign->messages->isEmpty())
            <div class="text-center py-5 px-3 border border-dashed rounded">
                <p class="text-muted mb-3">{{ __('Todavía no hay mensajes en esta campaña.') }}</p>
                <a href="{{ $classicEditorNewMessageUrl }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-plus me-1"></i>{{ __('Crear el primer mensaje') }}
                </a>
            </div>
        @elseif ($campaign->type === \App\Enums\CampaignType::Sequences->value)
            <form action="{{ route('campaigns.sequence.update', $campaign) }}" method="POST" class="mb-0">
                @csrf
                @method('PATCH')
                <input type="hidden" name="manage_automations" value="1">
                @php
                    $sequenceFormErrorMessages = [];
                    foreach ($errors->keys() as $errorKey) {
                        if ($errorKey === 'sequence' || str_starts_with((string) $errorKey, 'sequence.')) {
                            foreach ($errors->get($errorKey) as $msg) {
                                $sequenceFormErrorMessages[] = $msg;
                            }
                        }
                    }
                @endphp
                @if (count($sequenceFormErrorMessages) > 0)
                    <div class="alert alert-danger mb-3" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($sequenceFormErrorMessages as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <ul class="timeline mb-0 ms-1">
                    @foreach ($campaign->messages as $seqMessage)
                        @php
                            $idx = $loop->index;
                            $preset = 'none';
                            $conds = $seqMessage->pivot->conditions;
                            if (is_array($conds))
                            {
                                $r = $conds['require_previous'] ?? '';
                                if ($r === 'opened')
                                {
                                    $preset = 'opened';
                                }
                                elseif ($r === 'clicked')
                                {
                                    $preset = 'clicked';
                                }
                            }
                            $pivotDelay = $seqMessage->pivot->delay_minutes_after_previous;
                            $sortOld = old('sequence.'.$idx.'.sort_order', $seqMessage->pivot->sort_order);
                            $delayOld = old('sequence.'.$idx.'.delay_minutes_after_previous', $pivotDelay !== null ? $pivotDelay : '');
                            $presetOld = old('sequence.'.$idx.'.condition_preset', $preset);
                            $storedRules = $automationsGroupedByStepMessageId[$seqMessage->id] ?? [];
                            $ruleRows = count($storedRules) > 0 ? $storedRules : [[]];
                            $seqStepHasError = $errors->has('sequence.'.$idx.'.sort_order')
                                || $errors->has('sequence.'.$idx.'.delay_minutes_after_previous')
                                || $errors->has('sequence.'.$idx.'.condition_preset');
                            foreach ($errors->keys() as $errKey)
                            {
                                if (str_starts_with((string) $errKey, 'sequence.'.$idx.'.automations'))
                                {
                                    $seqStepHasError = true;
                                    break;
                                }
                            }
                        @endphp
                        <li class="timeline-item timeline-item-transparent pb-3">
                            <span class="timeline-point timeline-point-primary"></span>
                            <div class="timeline-event w-100">
                                <input type="hidden" name="sequence[{{ $idx }}][message_id]" value="{{ $seqMessage->id }}">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div class="me-2">
                                        <h6 class="mb-0">{{ __('Paso :n', ['n' => $loop->iteration]) }}</h6>
                                        <span class="text-muted small">{{ $seqMessage->name }}</span>
                                        @if ($seqMessage->type)
                                            <span class="text-muted small d-block">{{ $seqMessage->type->name }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center flex-shrink-0">
                                        <a href="{{ route('message.show', $seqMessage->id) }}" class="btn btn-xs btn-label-primary">
                                            <i class="ti ti-external-link ti-sm me-1"></i>{{ __('Ver mensaje') }}
                                        </a>
                                        <button
                                            class="btn btn-xs btn-label-secondary waves-effect waves-light"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#seq-step-settings-{{ $seqMessage->id }}"
                                            aria-expanded="{{ $seqStepHasError ? 'true' : 'false' }}"
                                            aria-controls="seq-step-settings-{{ $seqMessage->id }}"
                                            title="{{ __('Configuración del paso') }}"
                                        >
                                            <i class="ti ti-settings ti-sm me-1"></i>{{ __('Configuración') }}
                                        </button>
                                    </div>
                                </div>
                                <div
                                    id="seq-step-settings-{{ $seqMessage->id }}"
                                    class="collapse {{ $seqStepHasError ? 'show' : '' }}"
                                >
                                    <input type="hidden" name="sequence[{{ $idx }}][sort_order]" value="{{ $sortOld }}">
                                    @if ($loop->first)
                                        <input type="hidden" name="sequence[{{ $idx }}][delay_minutes_after_previous]" value="{{ $delayOld }}">
                                        <input type="hidden" name="sequence[{{ $idx }}][condition_preset]" value="none">
                                    @else
                                        <div class="row g-2 align-items-end pt-2 mt-1 border-top">
                                            <div class="col-6 col-sm-auto">
                                                <label class="form-label small mb-0" for="seq-delay-{{ $seqMessage->id }}">{{ __('Espera') }} ({{ __('min') }})</label>
                                                <input
                                                    id="seq-delay-{{ $seqMessage->id }}"
                                                    type="number"
                                                    name="sequence[{{ $idx }}][delay_minutes_after_previous]"
                                                    class="form-control form-control-sm @error('sequence.'.$idx.'.delay_minutes_after_previous') is-invalid @enderror"
                                                    min="0"
                                                    placeholder="—"
                                                    style="min-width: 5rem;"
                                                    value="{{ $delayOld }}"
                                                >
                                                @error('sequence.'.$idx.'.delay_minutes_after_previous')
                                                    <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$idx.'.delay_minutes_after_previous') }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-12 col-sm">
                                                <label class="form-label small mb-0" for="seq-cond-{{ $seqMessage->id }}">{{ __('Condición') }}</label>
                                                <select
                                                    id="seq-cond-{{ $seqMessage->id }}"
                                                    name="sequence[{{ $idx }}][condition_preset]"
                                                    class="form-select form-select-sm @error('sequence.'.$idx.'.condition_preset') is-invalid @enderror"
                                                >
                                                    <option value="none" @selected($presetOld === 'none')>{{ __('Sin condición (tras la espera)') }}</option>
                                                    <option value="opened" @selected($presetOld === 'opened')>{{ __('Solo si abrió el anterior') }}</option>
                                                    <option value="clicked" @selected($presetOld === 'clicked')>{{ __('Solo si hizo clic en el anterior') }}</option>
                                                </select>
                                                @error('sequence.'.$idx.'.condition_preset')
                                                    <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$idx.'.condition_preset') }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <p class="text-muted small mt-2 mb-0">{{ __('El resumen entre pasos en la línea de tiempo repite la espera y la condición.') }}</p>
                                        <p class="text-muted small mt-1 mb-0">{{ __('Si eliges condición sobre el paso anterior, quien no la cumpla no recibe este envío: para ese contacto la secuencia puede «saltarse» este paso. No es un desvío automático al siguiente mensaje; para caminos distintos (p. ej. otro canal si no abrió) define reglas en la sección de ramificación.') }}</p>
                                    @endif

                                    <hr class="my-3">
                                    <p class="small fw-semibold text-muted mb-1">{{ __('Ramificaciones / automatización (opcional)') }}</p>
                                    <p class="text-muted small mb-2">{{ __('Aquí defines disparadores y mensajes alternativos (varias reglas por paso). Complementa la condición del bloque anterior: esa condición solo incluye o excluye este correo de la secuencia; las reglas de abajo permiten envíos adicionales según abrir, clics o el alta. Las horas mínimas entre envíos en la ficha del mensaje son independientes y aplican también fuera de la secuencia.') }}</p>
                                    <div
                                        class="step-automations-list mb-2"
                                        data-step-index="{{ $idx }}"
                                    >
                                        @foreach ($ruleRows as $ridx => $ruleRow)
                                            @include('campaigns.partials.sequence-step-automation-rule', [
                                                'stepIndex' => $idx,
                                                'ruleIndex' => $ridx,
                                                'defaults' => is_array($ruleRow) ? $ruleRow : [],
                                                'messageTypes' => $messageTypes,
                                                'automationMessages' => $automationMessages,
                                            ])
                                        @endforeach
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-label-primary seq-add-automation-rule mb-1 waves-effect waves-light"
                                        data-step-index="{{ $idx }}"
                                    >
                                        <i class="ti ti-plus ti-xs me-1"></i>{{ __('Añadir regla') }}
                                    </button>
                                </div>
                            </div>
                        </li>
                        @if (! $loop->last)
                            @php
                                $nextMessage = $campaign->messages[$loop->index + 1];
                                $nextPivot = $nextMessage->pivot;
                                $nextDelayMins = $nextPivot->delay_minutes_after_previous;
                                $nextPreset = 'none';
                                $nextConds = $nextPivot->conditions;
                                if (is_array($nextConds))
                                {
                                    $nr = $nextConds['require_previous'] ?? '';
                                    if ($nr === 'opened')
                                    {
                                        $nextPreset = 'opened';
                                    }
                                    elseif ($nr === 'clicked')
                                    {
                                        $nextPreset = 'clicked';
                                    }
                                }
                                $nextConditionSummaryLabel = match ($nextPreset)
                                {
                                    'opened' => __('Solo si el contacto abrió el correo anterior'),
                                    'clicked' => __('Solo si el contacto hizo clic en el correo anterior'),
                                    default => __('Sin exigir abrir ni clic: tras la espera se puede enviar'),
                                };
                            @endphp
                            <li class="timeline-item timeline-item-transparent pb-3 pt-0">
                                <span class="timeline-point timeline-point-secondary"></span>
                                <div class="timeline-event py-0 w-100">
                                    <small class="text-muted d-flex align-items-center gap-1 flex-wrap">
                                        <i class="ti ti-arrow-down"></i>
                                        {{ __('Siguiente') }}:
                                        <span class="text-body-secondary fw-semibold">{{ $nextMessage->name }}</span>
                                    </small>
                                    <div class="mt-2 ps-1 border-start border-2 border-primary ms-1">
                                        <p class="text-muted small mb-1 fw-semibold">{{ __('Parámetros del paso destino (este mensaje)') }}</p>
                                        <p class="text-muted small mb-2 mb-md-1">{{ __('La espera y el requisito se guardan en la configuración de «:name», no en el paso de arriba. Ahí decides si ese envío se hace y con qué condiciones respecto al correo previo.', ['name' => $nextMessage->name]) }}</p>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li>
                                                {{ __('Espera mínima tras el correo anterior') }}:
                                                <span class="text-body-secondary">
                                                    @if ($nextDelayMins !== null && $nextDelayMins !== '')
                                                        {{ (int) $nextDelayMins }} {{ __('min') }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </li>
                                            <li>
                                                {{ __('Requisito sobre el correo anterior') }}:
                                                <span class="text-body-secondary">{{ $nextConditionSummaryLabel }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    @if ((int) ($nextMessage->min_hours_between_emails ?? 0) > 0)
                                        <small class="d-block text-muted mt-2 mb-0">
                                            <span class="fw-semibold text-body-secondary">{{ __('Límite en la ficha del mensaje') }}:</span>
                                            {{ __(':h h mínimas entre correos para este contacto.', ['h' => (int) $nextMessage->min_hours_between_emails]) }}
                                        </small>
                                    @endif
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                        <i class="ti ti-device-floppy me-1"></i>{{ __('Guardar cambios') }}
                    </button>
                </div>
            </form>
        @else
            <p class="text-muted small mb-4">
                {{ __('En una difusión, cada ítem es un mensaje Mailer en sí mismo: la programación y el envío masivo se gestionan desde la ficha del mensaje; aquí solo ves la campaña y el contenido vinculado.') }}
            </p>
            <ul class="list-unstyled mb-0">
                @foreach ($campaign->messages as $broadcastMessage)
                    @php
                        $editBroadcastContentUrl = route('campaigns.classic-editor', [
                            'type' => $campaign->type,
                            'title' => $campaign->name,
                            'campaign_id' => $campaign->id,
                            'message_id' => $broadcastMessage->id,
                            'template_id' => (int) ($broadcastMessage->template_id ?? 0),
                        ]);
                    @endphp
                    <li class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <h6 class="mb-1">{{ $broadcastMessage->name }}</h6>
                                @if ($broadcastMessage->type)
                                    <span class="text-muted small">{{ $broadcastMessage->type->name }}</span>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('message.show', $broadcastMessage->id) }}" class="btn btn-sm btn-label-primary waves-effect waves-light">
                                    <i class="ti ti-external-link ti-sm me-1"></i>{{ __('Ver mensaje') }}
                                </a>
                                <a href="{{ $editBroadcastContentUrl }}" class="btn btn-sm btn-label-secondary waves-effect waves-light">
                                    <i class="ti ti-edit ti-sm me-1"></i>{{ __('Editar contenido') }}
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    function reindexStepRules(listEl) {
        listEl.querySelectorAll('.step-automation-rule').forEach(function (row, i) {
            row.querySelectorAll('[name]').forEach(function (el) {
                var n = el.getAttribute('name');
                if (!n) {
                    return;
                }
                el.setAttribute('name', n.replace(/\[automations\]\[\d+\]/, '[automations][' + i + ']'));
            });
            var lbl = row.querySelector('[data-rule-label]');
            if (lbl) {
                lbl.textContent = i + 1;
            }
        });
    }

    function clearAutomationRow(row) {
        row.querySelectorAll('select').forEach(function (sel) {
            sel.selectedIndex = 0;
        });
        row.querySelectorAll('input[type="number"]').forEach(function (inp) {
            inp.value = '';
        });
        row.querySelectorAll('input[type="text"]').forEach(function (inp) {
            inp.value = '';
        });
    }

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('.seq-add-automation-rule');
        if (addBtn) {
            var stepIdx = addBtn.getAttribute('data-step-index');
            var list = document.querySelector('.step-automations-list[data-step-index="' + stepIdx + '"]');
            if (!list) {
                return;
            }
            var rows = list.querySelectorAll('.step-automation-rule');
            var last = rows[rows.length - 1];
            if (!last) {
                return;
            }
            var clone = last.cloneNode(true);
            clearAutomationRow(clone);
            clone.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            clone.querySelectorAll('.invalid-feedback').forEach(function (el) {
                el.remove();
            });
            list.appendChild(clone);
            reindexStepRules(list);
            return;
        }
        var rmBtn = e.target.closest('.step-automation-remove');
        if (rmBtn) {
            var row = rmBtn.closest('.step-automation-rule');
            var list = rmBtn.closest('.step-automations-list');
            if (!row || !list) {
                return;
            }
            var allRows = list.querySelectorAll('.step-automation-rule');
            if (allRows.length > 1) {
                row.remove();
                reindexStepRules(list);
            } else {
                clearAutomationRow(row);
            }
        }
    });
})();
</script>
@endpush
