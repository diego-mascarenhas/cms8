@extends('layouts/layoutMaster')

@section('title', __('Notification Details'))

@section('page-script')
@if(!empty($performanceDigestHighlights))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const items = @json($performanceDigestHighlights);
    const card = document.getElementById('digest-suggestion-card');
    const labelEl = document.getElementById('digest-suggestion-label');
    const singlePanel = document.getElementById('digest-suggestion-single');
    const messagesPanel = document.getElementById('digest-suggestion-messages');
    const messagesContainer = document.getElementById('digest-messages-container');
    const textEl = document.getElementById('digest-suggestion-text');
    const actionEl = document.getElementById('digest-suggestion-action');
    const actionLabelEl = document.getElementById('digest-suggestion-action-label');
    const copyBtn = document.getElementById('digest-suggestion-copy');
    const copyDefaultLabel = @json(__('app.performance_digest_suggestion_copy'));
    const copyDoneLabel = @json(__('app.performance_digest_suggestion_copied'));
    const howToRespondLabel = @json(__('app.performance_digest_response_hint_label'));
    const suggestedReplyLabel = @json(__('app.performance_digest_suggested_reply_label'));
    const receivedAtLabel = @json(__('app.performance_digest_message_received_at'));
    if (!card || !items.length) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderMessageCards(messages) {
        if (!messagesContainer) {
            return;
        }

        messagesContainer.innerHTML = messages.map(function (message, index) {
            const actionButton = message.action_url
                ? '<a href="' + escapeHtml(message.action_url) + '" class="btn btn-primary btn-sm" target="_blank" rel="noopener"><i class="ti ti-external-link me-1"></i>' + escapeHtml(message.action_label || '') + '</a>'
                : '';

            return ''
                + '<div class="border rounded p-3 mb-3 digest-message-thread" data-message-index="' + index + '">'
                + '  <div class="d-flex justify-content-between align-items-start gap-2 mb-2">'
                + '    <div><h6 class="mb-0">' + escapeHtml(message.contact_label || message.contact_name || '') + '</h6>'
                + '    <small class="text-muted">' + escapeHtml(receivedAtLabel) + ': ' + escapeHtml(message.received_at || '') + '</small></div>'
                + '  </div>'
                + '  <div class="bg-lighter rounded p-3 mb-3">'
                + '    <p class="mb-0 text-body" style="white-space: pre-wrap;">' + escapeHtml(message.preview || '') + '</p>'
                + '  </div>'
                + '  <p class="small text-muted mb-1"><strong>' + escapeHtml(howToRespondLabel) + ':</strong> ' + escapeHtml(message.response_hint || '') + '</p>'
                + '  <label class="form-label small fw-medium mb-1">' + escapeHtml(suggestedReplyLabel) + '</label>'
                + '  <textarea class="form-control mb-2 digest-message-suggestion" rows="4" readonly>' + escapeHtml(message.suggestion || '') + '</textarea>'
                + '  <div class="d-flex flex-wrap gap-2">'
                + '    <button type="button" class="btn btn-label-secondary btn-sm digest-message-copy" data-message-index="' + index + '"><i class="ti ti-copy me-1"></i>' + escapeHtml(copyDefaultLabel) + '</button>'
                + actionButton
                + '  </div>'
                + '</div>';
        }).join('');

        messagesContainer.querySelectorAll('.digest-message-copy').forEach(function (button) {
            button.addEventListener('click', function () {
                const index = button.getAttribute('data-message-index');
                const textarea = messagesContainer.querySelector('.digest-message-thread[data-message-index="' + index + '"] .digest-message-suggestion');
                if (!textarea || !textarea.value) {
                    return;
                }
                navigator.clipboard.writeText(textarea.value).then(function () {
                    button.textContent = copyDoneLabel;
                    window.setTimeout(function () {
                        button.innerHTML = '<i class="ti ti-copy me-1"></i>' + copyDefaultLabel;
                    }, 2000);
                });
            });
        });
    }

    function showItem(key) {
        const item = items.find(function (entry) { return entry.key === key; });
        if (!item) {
            return;
        }

        document.querySelectorAll('[data-digest-highlight-key]').forEach(function (el) {
            el.classList.remove('active', 'fw-semibold', 'text-primary');
        });
        const active = document.querySelector('[data-digest-highlight-key="' + key + '"]');
        if (active) {
            active.classList.add('active', 'fw-semibold', 'text-primary');
        }

        labelEl.textContent = item.label;

        if (item.detail_mode === 'messages' && item.messages && item.messages.length) {
            singlePanel.classList.add('d-none');
            messagesPanel.classList.remove('d-none');
            renderMessageCards(item.messages);
        } else {
            messagesPanel.classList.add('d-none');
            singlePanel.classList.remove('d-none');
            textEl.value = item.suggestion;

            if (item.action_url) {
                actionEl.href = item.action_url;
                if (actionLabelEl) {
                    actionLabelEl.textContent = item.action_label || '';
                }
                actionEl.classList.remove('d-none');
            } else {
                actionEl.classList.add('d-none');
            }
        }

        card.classList.remove('d-none');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.querySelectorAll('[data-digest-highlight-key]').forEach(function (button) {
        button.addEventListener('click', function () {
            showItem(button.getAttribute('data-digest-highlight-key'));
        });
    });

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            if (!textEl.value) {
                return;
            }
            navigator.clipboard.writeText(textEl.value).then(function () {
                copyBtn.textContent = copyDoneLabel;
                window.setTimeout(function () {
                    copyBtn.textContent = copyDefaultLabel;
                }, 2000);
            });
        });
    }

});
</script>
@endif
@endsection

@section('content')
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Notifications') }}/</span> {{ $notification->subject }}</h4>
        <p class="text-muted">{{ __('Notification Details') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if(!$notification->is_sent)
            @can('notification.edit')
            <a href="{{ route('notification.edit', $notification->id) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>Editar notificación
            </a>
            @endcan
            @can('notification.send')
            <form action="{{ route('notification.send', $notification->id) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-success waves-effect waves-light" 
                        onclick="return confirm('¿Estás seguro de que quieres enviar esta notificación?')">
                    <i class="ti ti-send me-1"></i>Enviar notificación
                </button>
            </form>
            @endcan
        @else
            @can('notification.resend')
            <form action="{{ route('notification.resend', $notification->id) }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-warning waves-effect waves-light" 
                        onclick="return confirm('¿Estás seguro de que quieres reenviar esta notificación?')">
                    <i class="ti ti-repeat me-1"></i>Reenviar notificación
                </button>
            </form>
            @endcan
        @endif
        @if(! empty($notification->metadata['action_url']))
        <a href="{{ $notification->metadata['action_url'] }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-chart-bar me-1"></i>{{ __('app.performance_insight_notification_view') }}
        </a>
        @endif
        @if($notification->contact)
        <a href="{{ route('collaborator.notifications', $notification->contact->id) }}" class="btn btn-info waves-effect waves-light">
            <i class="ti ti-bell me-1"></i>Ver notificaciones del colaborador
        </a>
        @endif
        <a href="{{ route('notification-list') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>Volver al listado
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Notification Details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-bell me-2"></i>{{ __('Notification Information') }}
            </h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Tipo de notificación</label>
                        <p class="text-body">{{ $notification->type->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Estado</label>
                        <div>
                            {!! $notification->status_badge !!}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Contacto</label>
                        <p class="text-body">
                            @if($notification->contact)
                                <a href="{{ route('contact.show', $notification->contact->id) }}" class="text-decoration-none">
                                    {{ $notification->contact->name }} {{ $notification->contact->surname }}
                                </a>
                            @else
                                <span class="text-danger">Contacto no disponible</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Email del contacto</label>
                        <p class="text-body">{{ $notification->contact ? $notification->contact->email : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Content -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-message me-2"></i>{{ __('Message Content') }}
            </h5>
            <div class="card-body">
                @if($dailyPerformanceInsight ?? null)
                    <div class="mb-3">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_column_headline') }}</label>
                        <p class="text-body mb-0">{{ $dailyPerformanceInsight->headline }}</p>
                    </div>
                    @if(filled($dailyPerformanceInsight->focus))
                    <div class="mb-3">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_column_focus') }}</label>
                        <p class="text-body mb-0">{{ $dailyPerformanceInsight->focus }}</p>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_column_message') }}</label>
                        <div class="border rounded p-3 bg-light mb-0">
                            @if(filled($dailyPerformanceInsight->message))
                                {!! nl2br(e($dailyPerformanceInsight->message)) !!}
                            @elseif(filled($notification->message))
                                {!! $notification->formatted_message !!}
                            @else
                                <span class="text-muted">{{ __('app.performance_insight_notification_empty_body') }}</span>
                            @endif
                        </div>
                    </div>
                    @if(!empty($performanceDigestHighlights))
                    <div class="mb-0">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_notification_highlights') }}</label>
                        <p class="text-muted small mb-2">{{ __('app.performance_digest_suggestion_card_hint') }}</p>
                        <ul class="list-unstyled mb-0" id="digest-highlight-list">
                            @foreach($performanceDigestHighlights as $highlight)
                                <li class="mb-1">
                                    <button type="button"
                                        class="btn btn-link btn-sm text-start text-body p-0 border-0 shadow-none text-decoration-none"
                                        data-digest-highlight-key="{{ $highlight['key'] }}">
                                        <i class="ti ti-point-filled ti-xs me-1 text-primary"></i>{{ $highlight['label'] }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @elseif(!empty($dailyPerformanceInsight->context_snapshot['highlights'] ?? []))
                    <div class="mb-0">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_notification_highlights') }}</label>
                        <ul class="list-unstyled mb-0">
                            @foreach($dailyPerformanceInsight->context_snapshot['highlights'] as $highlight)
                                <li class="mb-1 text-body"><i class="ti ti-point-filled ti-xs me-1 text-primary"></i>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    <p class="text-muted small mt-3 mb-0">
                        {{ __('app.performance_insight_notification_ratio', ['ratio' => number_format((float) $dailyPerformanceInsight->performance_ratio, 2)]) }}
                    </p>
                @else
                    <div class="mb-3">
                        <label class="form-label fw-medium">{{ __('Asunto') }}</label>
                        <p class="text-body mb-0">{{ $notification->subject }}</p>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-medium">{{ __('Mensaje') }}</label>
                        <div class="border rounded p-3 bg-light mb-0">
                            @if(filled($notification->message))
                                {!! $notification->formatted_message !!}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if(!empty($performanceDigestHighlights))
        <div class="card mb-4 d-none" id="digest-suggestion-card">
            <h5 class="card-header">
                <i class="ti ti-sparkles me-2"></i>{{ __('app.performance_digest_suggestion_card_title') }}
            </h5>
            <div class="card-body">
                <p class="text-muted small mb-2" id="digest-suggestion-label"></p>
                <p class="text-muted small mb-3">{{ __('app.performance_digest_future_ai_note') }}</p>

                <div id="digest-suggestion-messages" class="d-none">
                    <div id="digest-messages-container"></div>
                </div>

                <div id="digest-suggestion-single">
                    <textarea class="form-control mb-3" id="digest-suggestion-text" rows="5" readonly></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-label-secondary btn-sm" id="digest-suggestion-copy">
                            <i class="ti ti-copy me-1"></i>{{ __('app.performance_digest_suggestion_copy') }}
                        </button>
                        <a href="#" class="btn btn-primary btn-sm d-none" id="digest-suggestion-action" target="_blank" rel="noopener">
                            <i class="ti ti-external-link me-1"></i><span id="digest-suggestion-action-label"></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($notification->is_sent && $notification->email_metadata)
        <!-- Email Metadata -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-info-circle me-2"></i>{{ __('Delivery Information') }}
            </h5>
            <div class="card-body">
                @php
                    $metadata = json_decode($notification->email_metadata, true);
                @endphp
                <div class="row g-3">
                    @if(isset($metadata['message_id']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Message ID</label>
                        <p class="text-body font-monospace">{{ $metadata['message_id'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['to']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Enviado a</label>
                        <p class="text-body">{{ $metadata['to'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['from']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Enviado desde</label>
                        <p class="text-body">{{ $metadata['from'] }}</p>
                    </div>
                    @endif
                    @if(isset($metadata['sent_at']))
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Hora de envío</label>
                        <p class="text-body">{{ $metadata['sent_at'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Notification Timeline -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-clock me-2"></i>{{ __('Timeline') }}
            </h5>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-point bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Notificación creada</h6>
                            <small class="text-muted">{{ $notification->formatted_created_date }}</small>
                        </div>
                    </div>
                    @if($notification->is_sent)
                    <div class="timeline-item">
                        <div class="timeline-point bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Notificación enviada</h6>
                            <small class="text-muted">{{ $notification->formatted_sent_date }}</small>
                        </div>
                    </div>
                    @endif
                    @if($notification->is_read)
                    <div class="timeline-item">
                        <div class="timeline-point bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Notificación leída</h6>
                            <small class="text-muted">{{ $notification->read_at ? $notification->read_at->format('d/m/Y H:i') : 'Fecha desconocida' }}</small>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 