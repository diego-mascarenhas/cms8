@extends('layouts/layoutMaster')

@section('title', __('Campaña'))

@section('content')
@php
    /** @var array<string, int|float> $deliveryStats */
    $addMessageTemplateSelectQuery = array_filter([
        'type' => $campaign->type,
        'title' => $campaign->name,
        'campaign_id' => $campaign->id,
    ], fn ($value): bool => $value !== null && $value !== '');
    $classicEditorNewMessageUrl = route('campaigns.templates.select', $addMessageTemplateSelectQuery);
    $automationsList = array_values(is_array($storedAutomations ?? null) ? $storedAutomations : []);
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
            @if ($campaign->messages->isNotEmpty())
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
                {{ __('Los mensajes se crean en el editor; aquí ajustas la línea de tiempo (orden, esperas, condiciones y automatizaciones adicionales). Para título y zona horaria usa') }}
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
        @else
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
                        if ($errorKey === 'automations' || str_starts_with((string) $errorKey, 'automations.')) {
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
                            $seqStepHasError = $errors->has('sequence.'.$idx.'.sort_order')
                                || $errors->has('sequence.'.$idx.'.delay_minutes_after_previous')
                                || $errors->has('sequence.'.$idx.'.condition_preset');
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
                                            title="{{ __('Orden, espera y condición') }}"
                                        >
                                            <i class="ti ti-settings ti-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <div
                                    id="seq-step-settings-{{ $seqMessage->id }}"
                                    class="collapse {{ $seqStepHasError ? 'show' : '' }}"
                                >
                                    <div class="row g-2 align-items-end pt-2 mt-1 border-top">
                                        <div class="col-6 col-sm-auto">
                                            <label class="form-label small mb-0" for="seq-sort-{{ $seqMessage->id }}">{{ __('Orden') }}</label>
                                            <input
                                                id="seq-sort-{{ $seqMessage->id }}"
                                                type="number"
                                                name="sequence[{{ $idx }}][sort_order]"
                                                class="form-control form-control-sm @error('sequence.'.$idx.'.sort_order') is-invalid @enderror"
                                                min="0"
                                                max="10000"
                                                style="min-width: 4.5rem;"
                                                value="{{ $sortOld }}"
                                            >
                                            @error('sequence.'.$idx.'.sort_order')
                                                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$idx.'.sort_order') }}</div>
                                            @enderror
                                        </div>
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
                                    @if ($loop->first)
                                        <p class="text-muted small mt-2 mb-0">{{ __('En el primer paso, la espera y la condición suelen usarse poco; aplican sobre todo a partir del segundo.') }}</p>
                                    @else
                                        <p class="text-muted small mt-2 mb-0">{{ __('Estos valores definen cuándo se envía este paso respecto al anterior. Entre pasos verás un resumen con la misma información.') }}</p>
                                    @endif
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
                                $nextConditionLabel = match ($nextPreset)
                                {
                                    'opened' => __('Solo si abrió el anterior'),
                                    'clicked' => __('Solo si hizo clic en el anterior'),
                                    default => __('Sin condición (tras la espera)'),
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
                                        <p class="text-muted small mb-1 fw-semibold">{{ __('Reglas de campaña para este paso') }}</p>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li>
                                                {{ __('Orden') }}:
                                                <span class="text-body-secondary">{{ $nextPivot->sort_order }}</span>
                                            </li>
                                            <li>
                                                {{ __('Espera tras el paso anterior') }}:
                                                <span class="text-body-secondary">
                                                    @if ($nextDelayMins !== null && $nextDelayMins !== '')
                                                        {{ (int) $nextDelayMins }} {{ __('min') }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </li>
                                            <li>
                                                {{ __('Condición') }}:
                                                <span class="text-body-secondary">{{ $nextConditionLabel }}</span>
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

                <h6 class="mt-4 pt-3 border-top mb-2">{{ __('Automatizaciones adicionales') }}</h6>
                <p class="text-muted small mb-3">
                    {{ __('Disparadores y canal (tipo de mensaje); opcionalmente un mensaje ya creado en tu equipo. Se guardan junto con la secuencia al pulsar guardar.') }}
                </p>
                <div id="automations-rows" class="mb-3"></div>
                <button type="button" class="btn btn-sm btn-label-primary waves-effect waves-light mb-3" id="btn-add-automation">
                    <i class="ti ti-plus ti-sm me-1"></i>{{ __('Agregar automatización') }}
                </button>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                        <i class="ti ti-device-floppy me-1"></i>{{ __('Guardar cambios') }}
                    </button>
                </div>
            </form>

            <template id="automation-row-template">
    <div class="automation-row border rounded p-3 mb-3" data-automation-row>
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="text-muted small">{{ __('Automatización') }} <span data-automation-index></span></span>
            <button
                type="button"
                class="btn btn-link text-danger automation-remove p-0 m-0 border-0 shadow-none lh-1 text-decoration-none"
                title="{{ __('Quitar') }}"
            >
                <i class="ti ti-trash ti-sm"></i>
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Disparador') }}</label>
                <select class="form-select form-select-sm" data-field="trigger" name="">
                    <option value="">{{ __('Selecciona…') }}</option>
                    <option value="after_previous_sent">{{ __('Tras enviar el paso anterior') }}</option>
                    <option value="if_opened_previous">{{ __('Si abrió el paso anterior') }}</option>
                    <option value="if_not_opened_previous">{{ __('Si no abrió el paso anterior') }}</option>
                    <option value="delay_after_enrollment">{{ __('Tras el alta en la secuencia') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Espera (h)') }}</label>
                <input type="number" class="form-control form-control-sm" data-field="delay_hours" name="" min="0" max="8760" placeholder="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Canal (tipo)') }}</label>
                <select class="form-select form-select-sm" data-field="channel_type_id" name="">
                    <option value="">{{ __('Selecciona…') }}</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Mensaje (opcional)') }}</label>
                <select class="form-select form-select-sm" data-field="message_id" name="">
                    <option value="">{{ __('Ninguno') }}</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Notas') }}</label>
                <input type="text" class="form-control form-control-sm" data-field="notes" name="" maxlength="500" placeholder="{{ __('Uso interno') }}">
            </div>
        </div>
    </div>
</template>
        @endif
    </div>
</div>

@endsection

@section('page-script')
<script>
(function () {
    var container = document.getElementById('automations-rows');
    var template = document.getElementById('automation-row-template');
    var btnAdd = document.getElementById('btn-add-automation');
    if (!container || !template || !btnAdd) return;

    var messageTypes = @json($messageTypesJson);
    var automationMessages = @json($automationMessagesJson);
    var initialRows = @json($automationsList);

    function fillChannelOptions(select) {
        messageTypes.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = String(t.id);
            opt.textContent = t.name;
            select.appendChild(opt);
        });
    }

    function fillMessageOptions(select) {
        automationMessages.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = m.name + ' (' + m.type_name + ')';
            select.appendChild(opt);
        });
    }

    function wireRow(row) {
        row.querySelector('.automation-remove').addEventListener('click', function () {
            row.remove();
            renumberRows();
        });
    }

    function renumberRows() {
        var rows = container.querySelectorAll('[data-automation-row]');
        rows.forEach(function (row, i) {
            var label = row.querySelector('[data-automation-index]');
            if (label) label.textContent = String(i + 1);
            row.querySelectorAll('[data-field]').forEach(function (el) {
                var field = el.getAttribute('data-field');
                el.name = 'automations[' + i + '][' + field + ']';
            });
        });
    }

    function addRow(data) {
        data = data || {};
        var frag = template.content.cloneNode(true);
        var row = frag.querySelector('[data-automation-row]');
        var trig = row.querySelector('[data-field="trigger"]');
        var delay = row.querySelector('[data-field="delay_hours"]');
        var chan = row.querySelector('[data-field="channel_type_id"]');
        var msg = row.querySelector('[data-field="message_id"]');
        var notes = row.querySelector('[data-field="notes"]');
        fillChannelOptions(chan);
        fillMessageOptions(msg);
        if (data.trigger) trig.value = data.trigger;
        if (data.delay_hours !== undefined && data.delay_hours !== null) delay.value = String(data.delay_hours);
        if (data.channel_type_id) chan.value = String(data.channel_type_id);
        if (data.message_id) msg.value = String(data.message_id);
        if (data.notes) notes.value = data.notes;
        container.appendChild(row);
        wireRow(row);
        renumberRows();
    }

    btnAdd.addEventListener('click', function () {
        addRow({});
    });

    if (initialRows.length > 0) {
        initialRows.forEach(function (r) { addRow(r); });
    }
})();
</script>
@endsection
