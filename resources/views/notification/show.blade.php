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
    const scheduleBtn = document.getElementById('digest-suggestion-schedule');
    const scheduleLabelEl = document.getElementById('digest-suggestion-schedule-label');
    const copyBtn = document.getElementById('digest-suggestion-copy');
    const copyDefaultLabel = @json(__('app.performance_digest_suggestion_copy', [], 'es'));
    const copyDoneLabel = @json(__('app.performance_digest_suggestion_copied', [], 'es'));
    const howToRespondLabel = @json(__('app.performance_digest_response_hint_label', [], 'es'));
    const suggestedReplyLabel = @json(__('app.performance_digest_suggested_reply_label', [], 'es'));
    const receivedAtLabel = @json(__('app.performance_digest_message_received_at', [], 'es'));
    const scheduleUrl = @json(route('notification.schedule-digest-reply', $notification));
    const cancelScheduleBaseUrl = @json(url('/notification/'.$notification->id.'/schedule-digest-reply'));
    const scheduleErrorLabel = @json(__('app.performance_digest_schedule_error', [], 'es'));
    const scheduleCancelLabel = @json(__('app.performance_digest_schedule_cancel', [], 'es'));
    const scheduleCancelErrorLabel = @json(__('app.performance_digest_schedule_cancel_error', [], 'es'));
    const scheduledBadgeTemplate = @json(__('app.performance_digest_scheduled_badge', ['datetime' => '__DATETIME__'], 'es'));
    const scheduleEmailLabel = @json(__('app.performance_digest_schedule_email', [], 'es'));
    const scheduleWhatsAppLabel = @json(__('app.performance_digest_schedule_whatsapp', [], 'es'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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

    function formatJsonForDisplay(value) {
        const text = String(value || '').trim();
        if (text === '' || (text.charAt(0) !== '{' && text.charAt(0) !== '[')) {
            return { text: String(value || ''), isJson: false };
        }
        try {
            return { text: JSON.stringify(JSON.parse(text), null, 2), isJson: true };
        } catch (error) {
            return { text: String(value || ''), isJson: false };
        }
    }

    function scheduledBadgeHtml(label) {
        const text = scheduledBadgeTemplate.replace('__DATETIME__', escapeHtml(label || ''));
        return '<span class="badge bg-label-success align-self-center"><i class="ti ti-clock me-1"></i>' + text + '</span>';
    }

    function renderScheduleActionArea(context) {
        if (context.scheduled_message_id) {
            return scheduledBadgeHtml(context.scheduled_label || '')
                + '<button type="button" class="btn btn-label-danger btn-sm digest-cancel-schedule"'
                + ' data-scheduled-message-id="' + escapeHtml(context.scheduled_message_id) + '"'
                + ' data-message-index="' + escapeHtml(context.message_index ?? '') + '"'
                + ' data-highlight-key="' + escapeHtml(context.highlight_key || '') + '">'
                + '<i class="ti ti-x me-1"></i>' + escapeHtml(scheduleCancelLabel) + '</button>';
        }

        if (context.schedule_action) {
            const scheduleButtonLabel = context.schedule_action === 'email'
                ? scheduleEmailLabel
                : (context.schedule_action === 'whatsapp' ? scheduleWhatsAppLabel : (context.action_label || ''));

            return '<button type="button" class="btn btn-primary btn-sm digest-schedule-reply"'
                + ' data-message-index="' + escapeHtml(context.message_index ?? '') + '"'
                + ' data-highlight-key="' + escapeHtml(context.highlight_key || '') + '"'
                + ' data-digest-message-id="' + escapeHtml(context.digest_message_id || '') + '"'
                + ' data-schedule-action="' + escapeHtml(context.schedule_action) + '"'
                + ' data-schedule-recipient="' + escapeHtml(context.schedule_recipient || '') + '"'
                + ' data-schedule-subject="' + escapeHtml(context.schedule_subject || '') + '">'
                + '<i class="ti ti-clock me-1"></i>' + escapeHtml(scheduleButtonLabel) + '</button>';
        }

        if (context.action_url) {
            return '<a href="' + escapeHtml(context.action_url) + '" class="btn btn-primary btn-sm" target="_blank" rel="noopener">'
                + '<i class="ti ti-external-link me-1"></i>' + escapeHtml(context.action_label || '') + '</a>';
        }

        return '';
    }

    function applyScheduledStateToItem(item, payload) {
        if (item.detail_mode === 'messages' && item.messages && item.messages.length) {
            const index = payload.message_index !== null && payload.message_index !== undefined && payload.message_index !== ''
                ? Number(payload.message_index)
                : null;

            if (index !== null && item.messages[index]) {
                item.messages[index].scheduled_message_id = payload.scheduled_message_id || null;
                item.messages[index].scheduled_at = payload.scheduled_at || null;
                item.messages[index].scheduled_label = payload.scheduled_label || null;
                item.messages[index].schedule_action = payload.scheduled_message_id ? null : item.messages[index].schedule_action;
                return;
            }
        }

        item.scheduled_message_id = payload.scheduled_message_id || null;
        item.scheduled_at = payload.scheduled_at || null;
        item.scheduled_label = payload.scheduled_label || null;
        if (payload.scheduled_message_id) {
            item.schedule_action = null;
            item.action_url = null;
            item.action_label = null;
        }
    }

    function restoreScheduleMeta(context) {
        if (context.channel === 'email' && context.schedule_recipient) {
            context.schedule_action = 'email';
            context.action_label = scheduleEmailLabel;
        } else if (context.channel === 'whatsapp' && context.schedule_recipient) {
            context.schedule_action = 'whatsapp';
            context.action_label = scheduleWhatsAppLabel;
        } else if (context.key === 'email_unread' && context.schedule_recipient) {
            context.schedule_action = 'email';
            context.action_label = scheduleEmailLabel;
        } else if ((context.key === 'whatsapp_unread' || context.key === 'whatsapp_inbound') && context.schedule_recipient) {
            context.schedule_action = 'whatsapp';
            context.action_label = scheduleWhatsAppLabel;
        }
    }

    function clearScheduledStateOnItem(item, messageIndex) {
        if (item.detail_mode === 'messages' && item.messages && item.messages.length) {
            const index = messageIndex !== null && messageIndex !== undefined && messageIndex !== ''
                ? Number(messageIndex)
                : null;

            if (index !== null && item.messages[index]) {
                const message = item.messages[index];
                message.scheduled_message_id = null;
                message.scheduled_at = null;
                message.scheduled_label = null;
                restoreScheduleMeta(message);
                return;
            }
        }

        item.scheduled_message_id = null;
        item.scheduled_at = null;
        item.scheduled_label = null;
        restoreScheduleMeta(item);
    }

    function bindCancelScheduleButton(button, onSuccess) {
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const scheduledMessageId = button.getAttribute('data-scheduled-message-id');
            if (!scheduledMessageId || !cancelScheduleBaseUrl || !csrfToken) {
                return;
            }

            const originalHtml = button.innerHTML;
            button.disabled = true;

            fetch(cancelScheduleBaseUrl + '/' + scheduledMessageId, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (result.ok && result.data.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(button);
                    }
                    return;
                }

                alert(result.data.message || scheduleCancelErrorLabel);
                button.disabled = false;
                button.innerHTML = originalHtml;
            }).catch(function () {
                alert(scheduleCancelErrorLabel);
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
        });
    }

    function bindScheduleButton(button, getBody, getContext) {
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const body = getBody();
            if (!body || !scheduleUrl || !csrfToken) {
                return;
            }

            const originalHtml = button.innerHTML;
            button.disabled = true;

            const context = getContext ? getContext() : {};

            fetch(scheduleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    schedule_action: button.getAttribute('data-schedule-action'),
                    schedule_recipient: button.getAttribute('data-schedule-recipient'),
                    schedule_subject: button.getAttribute('data-schedule-subject') || '',
                    highlight_key: context.highlight_key || '',
                    digest_message_id: context.digest_message_id || null,
                    body: body,
                }),
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (result.ok && result.data.success) {
                    if (typeof context.onScheduled === 'function') {
                        context.onScheduled(result.data, button);
                    }
                    return;
                }

                alert(result.data.message || scheduleErrorLabel);
                button.disabled = false;
                button.innerHTML = originalHtml;
            }).catch(function () {
                alert(scheduleErrorLabel);
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
        });
    }

    function renderMessageCards(messages, highlightKey) {
        if (!messagesContainer) {
            return;
        }

        messagesContainer.innerHTML = messages.map(function (message, index) {
            const actionArea = renderScheduleActionArea({
                scheduled_message_id: message.scheduled_message_id,
                scheduled_label: message.scheduled_label,
                schedule_action: message.schedule_action,
                schedule_recipient: message.schedule_recipient,
                schedule_subject: message.schedule_subject,
                action_url: message.action_url,
                action_label: message.action_label,
                message_index: index,
                highlight_key: highlightKey,
                digest_message_id: message.id || '',
            });
            const previewFormatted = formatJsonForDisplay(message.preview || '');
            const suggestionFormatted = formatJsonForDisplay(message.suggestion || '');
            const previewBlock = previewFormatted.isJson
                ? '<pre class="mb-0 text-body small font-monospace" style="white-space: pre-wrap;">' + escapeHtml(previewFormatted.text) + '</pre>'
                : '<p class="mb-0 text-body" style="white-space: pre-wrap;">' + escapeHtml(previewFormatted.text) + '</p>';

            return ''
                + '<div class="border rounded p-3 mb-3 digest-message-thread" data-message-index="' + index + '">'
                + '  <div class="d-flex justify-content-between align-items-start gap-2 mb-2">'
                + '    <div><h6 class="mb-0">' + escapeHtml(message.contact_label || message.contact_name || '') + '</h6>'
                + '    <small class="text-muted">' + escapeHtml(receivedAtLabel) + ': ' + escapeHtml(message.received_at || '') + '</small></div>'
                + '  </div>'
                + '  <div class="bg-lighter rounded p-3 mb-3">'
                +      previewBlock
                + '  </div>'
                + '  <p class="small text-muted mb-1"><strong>' + escapeHtml(howToRespondLabel) + ':</strong> ' + escapeHtml(message.response_hint || '') + '</p>'
                + '  <label class="form-label small fw-medium mb-1">' + escapeHtml(suggestedReplyLabel) + '</label>'
                + '  <textarea class="form-control mb-2 digest-message-suggestion' + (suggestionFormatted.isJson ? ' font-monospace' : '') + '" rows="4" readonly>' + escapeHtml(suggestionFormatted.text) + '</textarea>'
                + '  <div class="d-flex flex-wrap gap-2 digest-message-actions">'
                + '    <button type="button" class="btn btn-label-secondary btn-sm digest-message-copy" data-message-index="' + index + '"><i class="ti ti-copy me-1"></i>' + escapeHtml(copyDefaultLabel) + '</button>'
                + actionArea
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

        messagesContainer.querySelectorAll('.digest-schedule-reply').forEach(function (button) {
            bindScheduleButton(button, function () {
                const index = button.getAttribute('data-message-index');
                const textarea = messagesContainer.querySelector('.digest-message-thread[data-message-index="' + index + '"] .digest-message-suggestion');
                return textarea ? textarea.value : '';
            }, function () {
                return {
                    highlight_key: button.getAttribute('data-highlight-key') || '',
                    digest_message_id: button.getAttribute('data-digest-message-id') || null,
                    message_index: button.getAttribute('data-message-index'),
                    onScheduled: function (data) {
                        const highlightKey = button.getAttribute('data-highlight-key');
                        const item = items.find(function (entry) { return entry.key === highlightKey; });
                        if (item) {
                            applyScheduledStateToItem(item, {
                                message_index: button.getAttribute('data-message-index'),
                                scheduled_message_id: data.scheduled_message_id,
                                scheduled_at: data.scheduled_at,
                                scheduled_label: data.scheduled_label,
                            });
                            renderMessageCards(item.messages, highlightKey);
                        }
                    },
                };
            });
        });

        messagesContainer.querySelectorAll('.digest-cancel-schedule').forEach(function (button) {
            bindCancelScheduleButton(button, function () {
                const highlightKey = button.getAttribute('data-highlight-key');
                const item = items.find(function (entry) { return entry.key === highlightKey; });
                if (item) {
                    clearScheduledStateOnItem(item, button.getAttribute('data-message-index'));
                    renderMessageCards(item.messages, highlightKey);
                }
            });
        });
    }

    function renderSinglePanelScheduleActions(item) {
        const container = document.getElementById('digest-single-schedule-actions');
        if (!container) {
            return;
        }

        container.innerHTML = renderScheduleActionArea({
            scheduled_message_id: item.scheduled_message_id,
            scheduled_label: item.scheduled_label,
            schedule_action: item.schedule_action,
            schedule_recipient: item.schedule_recipient,
            schedule_subject: item.schedule_subject,
            action_url: item.action_url,
            action_label: item.action_label,
            highlight_key: item.key,
            digest_message_id: '',
            message_index: '',
        });

        container.querySelectorAll('.digest-schedule-reply').forEach(function (button) {
            bindScheduleButton(button, function () {
                return textEl ? textEl.value : '';
            }, function () {
                return {
                    highlight_key: item.key,
                    onScheduled: function (data) {
                        applyScheduledStateToItem(item, {
                            scheduled_message_id: data.scheduled_message_id,
                            scheduled_at: data.scheduled_at,
                            scheduled_label: data.scheduled_label,
                        });
                        renderSinglePanelScheduleActions(item);
                    },
                };
            });
        });

        container.querySelectorAll('.digest-cancel-schedule').forEach(function (button) {
            bindCancelScheduleButton(button, function () {
                clearScheduledStateOnItem(item, null);
                renderSinglePanelScheduleActions(item);
            });
        });
    }

    function updateSinglePanelActions(item) {
        if (scheduleBtn) {
            scheduleBtn.classList.add('d-none');
        }
        if (actionEl) {
            actionEl.classList.add('d-none');
        }

        renderSinglePanelScheduleActions(item);
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
            renderMessageCards(item.messages, item.key);
        } else {
            messagesPanel.classList.add('d-none');
            singlePanel.classList.remove('d-none');
            const suggestionFormatted = formatJsonForDisplay(item.suggestion || '');
            textEl.value = suggestionFormatted.text;
            if (suggestionFormatted.isJson) {
                textEl.classList.add('font-monospace');
            } else {
                textEl.classList.remove('font-monospace');
            }

            updateSinglePanelActions(item);
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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Notifications') }}/</span> <x-notification-subject :subject="$notification->subject" /></h4>
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
    <div class="col-md-8">
        <!-- Message Content -->
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-message me-2"></i>{{ __('Message Content') }}
            </h5>
            <div class="card-body">
                @if($dailyPerformanceInsight ?? null)
                    <div class="mb-3">
                        <label class="form-label fw-medium">{{ __('app.performance_insight_column_headline') }}</label>
                        <p class="text-body mb-0"><x-notification-subject :subject="$dailyPerformanceInsight->headline" /></p>
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
                        <p class="text-body mb-0"><x-notification-subject :subject="$notification->subject" /></p>
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

    <div class="col-md-4">
        <div class="card mb-4">
            <h5 class="card-header">
                <i class="ti ti-bell me-2"></i>{{ __('Notification Information') }}
            </h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">Tipo de notificación</label>
                        <p class="text-body mb-0">{{ $notification->type->name }}</p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Estado</label>
                        <div>
                            {!! $notification->status_badge !!}
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Contacto</label>
                        <p class="text-body mb-0">
                            @if($notification->contact)
                                <a href="{{ route('contact.show', $notification->contact->id) }}" class="text-decoration-none">
                                    {{ $notification->contact->name }} {{ $notification->contact->surname }}
                                </a>
                            @else
                                <span class="text-danger">Contacto no disponible</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Email del contacto</label>
                        <p class="text-body mb-0">{{ $notification->contact ? $notification->contact->email : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($performanceDigestHighlights))
<div class="row">
    <div class="col-12">
        <div class="card mb-4 d-none" id="digest-suggestion-card">
            <h5 class="card-header">
                <i class="ti ti-sparkles me-2"></i>{{ __('app.performance_digest_suggestion_card_title', [], 'es') }}
            </h5>
            <div class="card-body">
                <p class="text-muted small mb-2" id="digest-suggestion-label"></p>
                <p class="text-muted small mb-3">{{ __('app.performance_digest_future_ai_note', [], 'es') }}</p>

                <div id="digest-suggestion-messages" class="d-none">
                    <div id="digest-messages-container"></div>
                </div>

                <div id="digest-suggestion-single">
                    <textarea class="form-control mb-3" id="digest-suggestion-text" rows="5" readonly></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-label-secondary btn-sm" id="digest-suggestion-copy">
                            <i class="ti ti-copy me-1"></i>{{ __('app.performance_digest_suggestion_copy', [], 'es') }}
                        </button>
                        <div class="d-flex flex-wrap gap-2" id="digest-single-schedule-actions"></div>
                        <button type="button" class="btn btn-primary btn-sm d-none" id="digest-suggestion-schedule">
                            <i class="ti ti-clock me-1"></i><span id="digest-suggestion-schedule-label"></span>
                        </button>
                        <a href="#" class="btn btn-primary btn-sm d-none" id="digest-suggestion-action" target="_blank" rel="noopener">
                            <i class="ti ti-external-link me-1"></i><span id="digest-suggestion-action-label"></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection 