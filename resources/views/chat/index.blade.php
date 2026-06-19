@extends('layouts/layoutMaster')

@php
    $chatFpLocale = strtolower(substr(str_replace('_', '-', app()->getLocale()), 0, 2));
    $chatFpLocaleBundle = in_array($chatFpLocale, ['es', 'fr', 'de', 'it', 'pt'], true);
    $chatScheduleMin = \Carbon\Carbon::now(config('app.timezone'))->format('Y-m-d H:i');
@endphp

@section('title', 'Chat')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
    <style>
        #chat-qr-container,
        #chat-history-qr-container {
            position: relative;
        }
        #chat-qr-container.chat-qr-loading .chat-qr-fallback,
        #chat-history-qr-container.chat-qr-loading .chat-qr-fallback {
            display: block !important;
        }
        .chat-qr-fallback-frame {
            width: auto;
            height: auto;
            background: transparent;
            box-shadow: none;
        }
        .chat-qr-fallback-frame .chat-qr-loading-overlay {
            position: static;
            background: transparent;
            display: flex;
            padding: 0.75rem 0;
        }
        #chat-whatsapp-qr-img,
        #chat-whatsapp-qr-img-history {
            background: #fff;
            border-radius: 0.375rem;
        }
        #chat-contacts-wa-avatar .avatar-initial i {
            color: var(--bs-success);
        }
        .chat-history-header {
            min-height: 4.5rem;
        }
        #app-chat-contacts .sidebar-header {
            min-height: 4.5rem;
        }
        .recording-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--bs-danger);
            animation: chat-recording-pulse 1s ease-in-out infinite;
        }
        @keyframes chat-recording-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .typing-dots span {
            animation: typing-dot 1.4s ease-in-out infinite;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing-dot {
            0%, 60%, 100% { opacity: 0.3; }
            30% { opacity: 1; }
        }
        .assistant-markdown p { margin-bottom: 0.5em; }
        .assistant-markdown p:last-child { margin-bottom: 0; }
        .assistant-markdown strong { font-weight: 600; }
        .assistant-markdown ul, .assistant-markdown ol { padding-left: 1.25rem; margin-bottom: 0.5em; }
        /* Long URLs / unbroken strings: wrap inside bubbles (flex children default to min-width:auto) */
        #chat-history-body .chat-message .d-flex.overflow-hidden {
            min-width: 0;
        }
        #chat-history-body .chat-message .chat-message-wrapper {
            min-width: 0;
            max-width: 100%;
        }
        #chat-history-body .chat-message .chat-message-text {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        #chat-history-body .chat-message .chat-message-text a {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .assistant-empty-suggestions {
            max-height: min(420px, 58vh);
            overflow-y: auto;
        }
        #chat-send-error-bar {
            flex-shrink: 0;
        }
        .chat-scheduled-meta-trigger {
            cursor: pointer;
            color: inherit;
            border: 0;
            background: transparent;
            padding: 0;
            font: inherit;
            line-height: inherit;
            display: inline;
        }
        .chat-scheduled-meta-trigger:hover,
        .chat-scheduled-meta-trigger:focus,
        .chat-scheduled-meta-trigger:active {
            color: inherit;
            text-decoration: none;
            box-shadow: none;
            outline: none;
        }
        /* WhatsApp badge: show disconnect only on hover/focus (local driver, connected).
           Flexbox centering on a full-size overlay avoids .btn:hover overriding transform. */
        .chat-wa-badge-disconnect-wrap {
            overflow: visible;
        }
        .chat-wa-badge-disconnect-wrap .chat-wa-disconnect-hover-positioner {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
            z-index: 2;
        }
        .chat-wa-badge-disconnect-wrap.chat-wa-disconnect-enabled:hover .chat-wa-disconnect-hover-positioner,
        .chat-wa-badge-disconnect-wrap.chat-wa-disconnect-enabled:focus-within .chat-wa-disconnect-hover-positioner {
            opacity: 1;
            pointer-events: auto;
        }
        .chat-wa-badge-disconnect-wrap .chat-wa-disconnect-hover-positioner:has(.chat-wa-disconnect-hover-trigger:disabled) {
            opacity: 0 !important;
            pointer-events: none !important;
        }
        .chat-wa-badge-disconnect-wrap .chat-wa-disconnect-hover-trigger {
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.2rem;
            margin: 0;
            min-width: 100%;
            width: max-content;
            max-width: min(100vw, 18rem);
            padding: 0.28rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.15;
            white-space: nowrap;
            border-radius: 10rem;
            flex-shrink: 0;
        }
    </style>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @if ($chatFpLocaleBundle)
        <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/{{ $chatFpLocale }}.js"></script>
    @endif
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/app-chat.js') }}?v={{ @filemtime(public_path('assets/js/app-chat.js')) ?: time() }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var chatImageModal = document.getElementById('chatImageModal');
        if (chatImageModal) {
        chatImageModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var imgUrl = trigger.getAttribute('data-img');
            var modalImg = document.getElementById('chatModalImg');
            modalImg.src = imgUrl;
        });
        }

        var chatMessageAvatars = @json($chatMessageAvatars ?? []);
        function buildChatAvatarHtml(avatar, marginClass) {
            if (!avatar) {
                return '';
            }
            marginClass = marginClass || 'me-3';
            var inner = '';
            if (avatar.photo_url) {
                inner = '<img src="' + String(avatar.photo_url).replace(/"/g, '&quot;') + '" alt="" class="rounded-circle">';
            } else if (avatar.icon) {
                inner = '<span class="avatar-initial rounded-circle ' + (avatar.label_class || 'bg-label-info') + '"><i class="ti ti-' + String(avatar.icon) + ' ti-sm"></i></span>';
            } else {
                var initials = document.createElement('div');
                initials.textContent = avatar.initials || '?';
                inner = '<span class="avatar-initial rounded-circle ' + (avatar.label_class || 'bg-label-primary') + '">' + initials.innerHTML + '</span>';
            }
            return '<div class="user-avatar flex-shrink-0 ' + marginClass + '"><div class="avatar avatar-sm">' + inner + '</div></div>';
        }

        // Humano Assistant preview handling
        const formSendMessage = document.getElementById('chat-form');
        const messageInput = document.querySelector('.message-input');
        const useAiToggle = document.getElementById('use-ai-toggle');

        function showChatSendErrorBar(text) {
            var bar = document.getElementById('chat-send-error-bar');
            if (!bar) return;
            bar.textContent = text || '';
            bar.classList.remove('d-none');
        }
        function hideChatSendErrorBar() {
            var bar = document.getElementById('chat-send-error-bar');
            if (!bar) return;
            bar.textContent = '';
            bar.classList.add('d-none');
        }
        function humChatParseSendFetchResponse(r) {
            return r.text().then(function(t) {
                var data = {};
                try {
                    data = t ? JSON.parse(t) : {};
                } catch (e) {
                    data = {};
                }
                return { ok: r.ok, status: r.status, data: data };
            });
        }
        function humChatSendSucceeded(res) {
            return res.ok && (!res.data || res.data.success !== false);
        }
        function humChatSendErrorFromResult(res) {
            if (res.data && res.data.error) {
                return String(res.data.error);
            }
            if (res.data && res.data.success === false && res.data.message) {
                return String(res.data.message);
            }
            if (res.data && res.data.errors && typeof res.data.errors === 'object') {
                var keys = Object.keys(res.data.errors);
                if (keys.length && res.data.errors[keys[0]] && res.data.errors[keys[0]][0]) {
                    return String(res.data.errors[keys[0]][0]);
                }
            }
            if (res.status === 419) {
                return '{{ __("Sesión caducada. Recarga la página.") }}';
            }
            if (!res.ok) {
                return '{{ __("whatsapp.send.error.generic") }}';
            }
            return '{{ __("whatsapp.send.error.generic") }}';
        }
        const recipientInput = document.getElementById('recipient');
        const attachmentInput = document.getElementById('chat-attachments');
        const attachmentCount = document.getElementById('chat-attachment-count');
        const previewModal = new bootstrap.Modal(document.getElementById('claudePreviewModal'));
        const sendAiResponseBtn = document.getElementById('sendAiResponseBtn');
        var assistantUrl = '{{ route("chat.assistant") }}';

        var assistantMessagesListEl = document.getElementById('assistant-messages-list');
        if (assistantMessagesListEl) {
            assistantMessagesListEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.assistant-suggestion-example');
                if (!btn || !messageInput) {
                    return;
                }
                var prompt = btn.getAttribute('data-prompt');
                if (prompt) {
                    messageInput.value = prompt;
                    messageInput.focus();
                }
            });
        }

        function getChatAssistantFlowRoutingKey() {
            var sel = document.getElementById('chatAssistantFlowRoutingKey');
            return sel && sel.value ? String(sel.value).trim() : '';
        }

        function getSelectedAttachments() {
            if (!attachmentInput || !attachmentInput.files) return [];
            return Array.from(attachmentInput.files);
        }

        function updateAttachmentCount() {
            if (!attachmentCount) return;
            var selected = getSelectedAttachments();
            attachmentCount.textContent = selected.length > 0 ? (selected.length + ' adjunto(s)') : '';
        }

        function appendAttachmentsToFormData(formData) {
            var selected = getSelectedAttachments();
            selected.forEach(function (file) {
                formData.append('attachments[]', file);
            });
        }

        if (attachmentInput) {
            attachmentInput.addEventListener('change', updateAttachmentCount);
        }

        if (messageInput && formSendMessage) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    formSendMessage.requestSubmit();
                }
            });
        }

        function setChatSendButtonsDisabled(disabled) {
            var form = document.getElementById('chat-form');
            if (!form) return;
            form.querySelectorAll('.chat-send-primary-btn, [name="send_intent"]').forEach(function (btn) {
                btn.disabled = disabled;
            });
        }

        (function persistAiTogglePreference() {
            var toggleDefault = {{ json_encode($contactChatAiToggleDefault ?? $userChatAiToggleDefault ?? true) }};
            if (!useAiToggle) {
                return;
            }
            useAiToggle.checked = toggleDefault;
            useAiToggle.addEventListener('change', function() {
                var token = document.querySelector('meta[name="csrf-token"]');
                var cidEl = document.getElementById('contact-id');
                var contactId = cidEl && cidEl.value ? parseInt(cidEl.value, 10) : 0;
                if (token) {
                    var body = { on: useAiToggle.checked };
                    if (contactId > 0) {
                        body.contact_id = contactId;
                    }
                    fetch('{{ route("chat.ai-toggle-preference") }}', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.getAttribute('content') },
                        body: JSON.stringify(body)
                    }).catch(function() {});
                }
            });
        })();

        (function persistChatTeamSettingsSidebarToggles() {
            var box = document.getElementById('chat-team-settings-sidebar-toggles');
            if (!box || box.getAttribute('data-can-manage') !== '1') return;
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            if (!token) return;
            var url = '{{ route("chat.team-settings-sidebar") }}';
            var flowPairKey = 'assistant_keyword_intent_routing';
            var elDefaultFlow = document.getElementById('sidebar-default-assistant-flow-toggle');
            var elKeywordRouting = document.getElementById('sidebar-assistant-keyword-routing-toggle');
            var assistantExtraClientsSection = document.getElementById('assistant-conversations-extra-section');
            var whatsappSection = document.getElementById('whatsapp-conversations-section');

            function syncSidebarConversationsVisibility() {
                var showAssistantClientsToggle = document.getElementById('sidebar-show-assistant-conversations-toggle');
                var showWhatsAppToggle = document.getElementById('sidebar-show-whatsapp-conversations-toggle');
                if (assistantExtraClientsSection && showAssistantClientsToggle) {
                    assistantExtraClientsSection.classList.toggle('d-none', !showAssistantClientsToggle.checked);
                }
                if (whatsappSection && showWhatsAppToggle) {
                    whatsappSection.classList.toggle('d-none', !showWhatsAppToggle.checked);
                }
            }

            function syncKeywordFlowPair(changed) {
                if (!elDefaultFlow || !elKeywordRouting) return;
                if (changed === elDefaultFlow) {
                    elKeywordRouting.checked = elDefaultFlow.checked ? false : true;
                } else if (changed === elKeywordRouting) {
                    elDefaultFlow.checked = elKeywordRouting.checked ? false : true;
                }
            }
            box.querySelectorAll('input[data-team-setting-key]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var key = input.getAttribute('data-team-setting-key');
                    if (!key) return;
                    var invert = input.getAttribute('data-team-setting-invert') === '1';
                    var on = invert ? !input.checked : input.checked;
                    if (key === flowPairKey) {
                        syncKeywordFlowPair(input);
                    }
                    if (key === 'chat_show_assistant_conversations' || key === 'chat_show_whatsapp_conversations') {
                        syncSidebarConversationsVisibility();
                    }
                    fetch(url, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify({ key: key, on: on })
                    }).catch(function () {});
                });
            });
            syncSidebarConversationsVisibility();

            var aiRepliesToggle = document.getElementById('sidebar-ai-replies-toggle');
            var adminsWhenOffToggle = document.getElementById('sidebar-assistant-admins-when-off-toggle');
            var adminsWhenOffLabel = adminsWhenOffToggle ? adminsWhenOffToggle.closest('label') : null;
            function syncAdminsWhenOffToggleUi() {
                if (!adminsWhenOffToggle || !aiRepliesToggle) {
                    return;
                }
                var masterOn = aiRepliesToggle.checked;
                adminsWhenOffToggle.disabled = masterOn;
                if (adminsWhenOffLabel) {
                    adminsWhenOffLabel.classList.toggle('opacity-50', masterOn);
                }
            }
            syncAdminsWhenOffToggleUi();
            if (aiRepliesToggle) {
                aiRepliesToggle.addEventListener('change', syncAdminsWhenOffToggleUi);
            }
            window.syncChatAdminsWhenOffToggleUi = syncAdminsWhenOffToggleUi;
        })();

        let currentUserMessage = '';
        let currentAiResponse = '';
        let currentAiAudioBase64 = '';
        let currentAiAudioMime = '';
        let currentAttachmentPreviews = [];
        let localDocumentEvents = [];

        function decodeHtmlEntities(text) {
            if (!text) return '';
            var el = document.createElement('textarea');
            el.innerHTML = String(text);
            return el.value;
        }

        function renderMarkdownForChat(text) {
            if (!text) return '';
            var normalized = decodeHtmlEntities(String(text));
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                return marked.parse(normalized, { gfm: true, breaks: true });
            }
            return normalized.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
        }
        /** Pixels from bottom of scroll area; user is "following" new messages when below this threshold. */
        function chatHistoryPinThresholdPx() {
            return 120;
        }
        function chatHistoryIsPinnedToBottom(el) {
            if (!el) return true;
            var threshold = chatHistoryPinThresholdPx();
            var distance = el.scrollHeight - el.scrollTop - el.clientHeight;
            return distance <= threshold;
        }
        function chatHistoryDistanceFromBottom(el) {
            if (!el) return 0;
            return Math.max(0, el.scrollHeight - el.scrollTop - el.clientHeight);
        }
        /** After innerHTML / bulk replace: stay at bottom if user was following; else keep same offset from bottom. */
        function chatHistoryRestoreScrollAfterReplace(el, wasPinnedToBottom, distanceFromBottomBefore) {
            if (!el) return;
            var pinned = wasPinnedToBottom;
            var dist = distanceFromBottomBefore;
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (pinned) {
                        el.scrollTop = el.scrollHeight;
                    } else {
                        el.scrollTop = Math.max(0, el.scrollHeight - el.clientHeight - dist);
                    }
                });
            });
        }
        function chatHistoryScrollToBottomIfPinned(el) {
            if (el && chatHistoryIsPinnedToBottom(el)) {
                requestAnimationFrame(function () {
                    el.scrollTop = el.scrollHeight;
                });
            }
        }
        function buildAttachmentPreviewHtml(attachments) {
            if (!attachments || !attachments.length) return '';
            var blocks = attachments.map(function(file) {
                var safeName = (file.name || 'adjunto').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                if ((file.type || '').indexOf('image/') === 0) {
                    var tmpUrl = URL.createObjectURL(file);
                    return '<a href="' + tmpUrl + '" target="_blank" rel="noopener"><img src="' + tmpUrl + '" alt="' + safeName + '" style="max-width:140px;max-height:140px;border-radius:8px;margin:4px;"></a>';
                }
                return '<div class="small text-muted">📎 ' + safeName + '</div>';
            });
            return '<div class="chat-media mt-2">' + blocks.join('') + '</div>';
        }

        function registerLocalDocumentEvents(userMsg, aiMsg, attachments) {
            var names = (attachments || []).map(function(file) { return file.name || 'adjunto'; }).filter(Boolean);
            var userText = (userMsg && userMsg.trim() !== '') ? userMsg : ('📎 Documento adjunto' + (names.length ? ': ' + names.join(', ') : ''));
            var assistantText = (aiMsg && aiMsg.trim() !== '') ? aiMsg : 'Recibi tu documento. Lo estoy procesando y podes seguir el estado en Ver documentos.';
            var nowIso = new Date().toISOString();
            localDocumentEvents.push(
                { role: 'user', content: userText, created_at: nowIso, local_document_event: true },
                { role: 'assistant', content: assistantText, created_at: nowIso, local_document_event: true }
            );
            if (localDocumentEvents.length > 20) {
                localDocumentEvents = localDocumentEvents.slice(localDocumentEvents.length - 20);
            }
        }

        function appendAssistantExchangeToChat(userMsg, aiMsg, audioBase64, audioMime, attachments) {
            var list = document.getElementById('assistant-messages-list');
            if (!list) return;
            var empty = list.querySelector('.assistant-empty-state');
            if (empty) empty.remove();
            var timeStr = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            var safeUserMsg = (userMsg || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
            var userTextHtml = safeUserMsg !== '' ? safeUserMsg : '📎 Documento adjunto';
            var attachmentHtml = buildAttachmentPreviewHtml(attachments || []);
            var userLi = document.createElement('li');
            userLi.className = 'chat-message chat-message-right';
            userLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + userTextHtml + '</p>' + attachmentHtml + '</div><div class="text-end text-muted mt-1"><small>' + timeStr + '</small></div></div>' + buildChatAvatarHtml(chatMessageAvatars.user, 'ms-3') + '</div>';
            list.appendChild(userLi);
            var audioHtml = (audioBase64 && audioMime) ? '<div class="mt-2"><audio controls class="w-100" style="max-height:40px;"><source src="data:' + audioMime + ';base64,' + audioBase64 + '" type="' + audioMime + '"></audio></div>' : '';
            var aiLi = document.createElement('li');
            aiLi.className = 'chat-message';
            aiLi.innerHTML = '<div class="d-flex overflow-hidden">' + buildChatAvatarHtml(chatMessageAvatars.assistant, 'me-3') +
                '<div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text assistant-markdown"><div class="mb-0">' + renderMarkdownForChat(aiMsg || '') + '</div>' + audioHtml + '</div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
            list.appendChild(aiLi);
            removeAssistantTypingIndicator();
            var body = document.querySelector('.chat-history-body');
            chatHistoryScrollToBottomIfPinned(body);
        }
        function syncSidebarAssistantAutoRespondFromResponse(data) {
            if (!data || typeof data.assistant_auto_respond !== 'boolean') return;
            var sidebar = document.getElementById('sidebar-ai-replies-toggle');
            if (sidebar) sidebar.checked = data.assistant_auto_respond;
            if (typeof window.syncChatAdminsWhenOffToggleUi === 'function') {
                window.syncChatAdminsWhenOffToggleUi();
            }
        }
        function showAssistantTypingIndicator() {
            var list = document.getElementById('assistant-messages-list');
            if (!list || document.getElementById('assistant-typing-indicator')) return;
            var empty = list.querySelector('.assistant-empty-state');
            if (empty) empty.remove();
            var li = document.createElement('li');
            li.id = 'assistant-typing-indicator';
            li.className = 'chat-message';
            li.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text text-muted"><span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></div></div></div>';
            list.appendChild(li);
            var body = document.querySelector('.chat-history-body');
            chatHistoryScrollToBottomIfPinned(body);
        }
        function removeAssistantTypingIndicator() {
            var el = document.getElementById('assistant-typing-indicator');
            if (el) el.remove();
        }

        (function() {
            var micBtn = document.getElementById('chat-mic-btn');
            var recordStatus = document.getElementById('chat-record-status');
            var recordedReady = document.getElementById('chat-recorded-ready');
            var recordedDuration = document.getElementById('chat-recorded-duration');
            var cancelBtn = document.getElementById('chat-record-cancel');
            var micIcon = document.getElementById('chat-mic-icon');
            var pendingRecordedAudio = null;
            var mediaRecorder = null;
            var recordChunks = [];
            var recordStream = null;
            var recordStartTime = null;

            window.getPendingRecordedAudio = function() {
                var b = pendingRecordedAudio;
                pendingRecordedAudio = null;
                if (recordedReady) recordedReady.classList.add('d-none');
                return b;
            };
            window.hasPendingRecordedAudio = function() { return pendingRecordedAudio !== null; };

            function stopRecording() {
                if (!mediaRecorder || mediaRecorder.state === 'inactive') return;
                mediaRecorder.stop();
                if (recordStream) {
                    recordStream.getTracks().forEach(function(t) { t.stop(); });
                    recordStream = null;
                }
                mediaRecorder = null;
            }

            function showRecordedReady(durationSec) {
                if (recordStatus) recordStatus.classList.add('d-none');
                if (recordedReady && recordedDuration) {
                    recordedDuration.textContent = '{{ __("Audio") }} (' + Math.round(durationSec) + 's). {{ __("Enviar con el botón o añade texto.") }}';
                    recordedReady.classList.remove('d-none');
                }
                if (micBtn) {
                    micBtn.classList.remove('btn-danger');
                    micBtn.classList.add('btn-label-secondary');
                    if (micIcon) micIcon.className = 'ti ti-microphone ti-sm';
                }
            }

            function resetRecordUI() {
                pendingRecordedAudio = null;
                if (recordStatus) recordStatus.classList.add('d-none');
                if (recordedReady) recordedReady.classList.add('d-none');
                if (micBtn) {
                    micBtn.classList.remove('btn-danger');
                    micBtn.classList.add('btn-label-secondary');
                    if (micIcon) micIcon.className = 'ti ti-microphone ti-sm';
                }
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    stopRecording();
                    resetRecordUI();
                });
            }

            if (micBtn) {
                micBtn.addEventListener('click', function() {
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        stopRecording();
                        return;
                    }
                    if (pendingRecordedAudio) {
                        resetRecordUI();
                        return;
                    }
                    navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
                        recordStream = stream;
                        recordChunks = [];
                        recordStartTime = Date.now();
                        var mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
                        mediaRecorder = new MediaRecorder(stream);
                        mediaRecorder.ondataavailable = function(e) { if (e.data.size > 0) recordChunks.push(e.data); };
                        mediaRecorder.onstop = function() {
                            var duration = (Date.now() - recordStartTime) / 1000;
                            var blob = new Blob(recordChunks, { type: mimeType });
                            pendingRecordedAudio = blob;
                            showRecordedReady(duration);
                        };
                        mediaRecorder.start();
                        if (recordStatus) {
                            recordStatus.classList.remove('d-none');
                            recordStatus.classList.add('d-flex');
                        }
                        micBtn.classList.remove('btn-label-secondary');
                        micBtn.classList.add('btn-danger');
                        if (micIcon) micIcon.className = 'ti ti-square ti-sm';
                    }).catch(function(err) {
                        console.error('Microphone access failed', err);
                        alert('{{ __("No se puede acceder al micrófono. Comprueba los permisos del navegador.") }}');
                    });
                });
            }
        })();

        // Submit: handle on document in capture phase so we always run before app-chat.js. Toggle OFF = only your message, ON = assistant
        document.addEventListener('submit', function(e) {
            if (!e.target || e.target.id !== 'chat-form') return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var form = e.target;
            var hasAudio = window.hasPendingRecordedAudio && window.hasPendingRecordedAudio();
            var selectedAttachments = getSelectedAttachments();
            var hasAttachments = selectedAttachments.length > 0;
            var msg = messageInput && messageInput.value ? messageInput.value.trim() : '';
            if (!msg && !hasAudio && !hasAttachments) return;
            hideChatSendErrorBar();
            var sendIntent = 'send';
            if (e.submitter && e.submitter.name === 'send_intent') {
                sendIntent = e.submitter.value || 'send';
            }
            setChatSendButtonsDisabled(true);
            function reenableSend() { setChatSendButtonsDisabled(false); }
            var isAssistantViewForm = form.getAttribute('data-view-assistant') === '1';
            var aiOn = isAssistantViewForm ? true : (sendIntent === 'suggest');
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            var toVal = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';
            var cidEl = document.getElementById('contact-id');
            var contactId = (cidEl && cidEl.value && parseInt(cidEl.value, 10)) ? parseInt(cidEl.value, 10) : undefined;
            var waDriverLocal = @json(($whatsappDriver ?? '') === 'local');
            var waTeamConnected = @json((bool) ($teamWhatsAppIsConnected ?? false));

            if (!isAssistantViewForm && !toVal) {
                showChatSendErrorBar(@json(__('chat.send.error.no_recipient')));
                reenableSend();
                return;
            }

            if (!isAssistantViewForm && waDriverLocal && !waTeamConnected) {
                showChatSendErrorBar(@json(__('whatsapp.send.error.not_connected')));
                reenableSend();
                return;
            }

            if (!aiOn) {
                if (hasAudio) {
                    var audioBlob = window.getPendingRecordedAudio && window.getPendingRecordedAudio();
                    if (!audioBlob) { reenableSend(); return; }
                    var fd = new FormData();
                    fd.append('_token', token);
                    fd.append('to', toVal);
                    fd.append('message', msg || '');
                    fd.append('audio', audioBlob, 'recording.webm');
                    if (contactId) fd.append('contact_id', contactId);
                    appendAttachmentsToFormData(fd);
                    fetch('{{ route("chat.send") }}', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token } })
                        .then(humChatParseSendFetchResponse)
                        .then(function(res) {
                            if (!humChatSendSucceeded(res)) {
                                showChatSendErrorBar(humChatSendErrorFromResult(res));
                                return;
                            }
                            messageInput.value = '';
                            if (attachmentInput) attachmentInput.value = '';
                            updateAttachmentCount();
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        })
                        .catch(function() {
                            showChatSendErrorBar('{{ __("Error de conexión") }}');
                        })
                        .finally(reenableSend);
                } else {
                    if (hasAttachments) {
                        var fdNoAi = new FormData();
                        fdNoAi.append('_token', token);
                        fdNoAi.append('to', toVal);
                        fdNoAi.append('message', msg || '');
                        if (contactId) fdNoAi.append('contact_id', contactId);
                        appendAttachmentsToFormData(fdNoAi);
                        fetch('{{ route("chat.send") }}', {
                            method: 'POST',
                            body: fdNoAi,
                            headers: { 'X-CSRF-TOKEN': token }
                        })
                        .then(humChatParseSendFetchResponse)
                        .then(function(res) {
                            if (!humChatSendSucceeded(res)) {
                                showChatSendErrorBar(humChatSendErrorFromResult(res));
                                return;
                            }
                            messageInput.value = '';
                            if (attachmentInput) attachmentInput.value = '';
                            updateAttachmentCount();
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        })
                        .catch(function() {
                            showChatSendErrorBar('{{ __("Error de conexión") }}');
                        })
                        .finally(reenableSend);
                    } else {
                        var body = { to: toVal, message: msg, use_ai: false };
                        if (contactId) body.contact_id = contactId;
                        fetch('{{ route("chat.send") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify(body)
                        })
                        .then(humChatParseSendFetchResponse)
                        .then(function(res) {
                            if (!humChatSendSucceeded(res)) {
                                showChatSendErrorBar(humChatSendErrorFromResult(res));
                                return;
                            }
                            messageInput.value = '';
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        })
                        .catch(function() {
                            showChatSendErrorBar('{{ __("Error de conexión") }}');
                        })
                        .finally(reenableSend);
                    }
                }
                return;
            }

            var isAssistantView = isAssistantViewForm;
            /** Vista previa modal: no envío real; el backend desactiva send_whatsapp_message y evita relatos de fallo de envío */
            var previewOnlyAi = !isAssistantView;
            currentUserMessage = msg || (hasAudio ? '{{ __("[Mensaje de voz]") }}' : '');
            currentAttachmentPreviews = selectedAttachments;
            currentAiAudioBase64 = '';
            currentAiAudioMime = '';

            // Reset modal state (no AI call yet — user must click "Sugerir")
            document.getElementById('aiPreviewLoader').classList.add('d-none');
            var taPreviewStart = document.getElementById('aiResponsePreview');
            if (taPreviewStart) {
                taPreviewStart.value = currentUserMessage;
                taPreviewStart.disabled = false;
            }
            var errBoxStart = document.getElementById('aiAssistantPreviewError');
            if (errBoxStart) {
                errBoxStart.classList.add('d-none');
                errBoxStart.innerHTML = '';
            }
            var previewAudioEl = document.getElementById('aiResponsePreviewAudio');
            if (previewAudioEl) previewAudioEl.innerHTML = '';
            currentAiResponse = '';

            if (!isAssistantView) {
                previewModal.show();
                reenableSend();
                return; // Don't call AI yet; wait for "Sugerir"
            }
            if (isAssistantView) showAssistantTypingIndicator();

            var respondWithAudio = document.getElementById('respond-with-audio') && document.getElementById('respond-with-audio').checked;

            if (hasAudio || hasAttachments) {
                var audioBlob = window.getPendingRecordedAudio && window.getPendingRecordedAudio();
                var formData = new FormData();
                formData.append('_token', token);
                formData.append('message', msg);
                if (audioBlob) {
                    formData.append('audio', audioBlob, 'recording.webm');
                }
                if (respondWithAudio) formData.append('respond_with_audio', '1');
                if (toVal) formData.append('recipient', toVal);
                if (contactId) formData.append('contact_id', contactId);
                appendAttachmentsToFormData(formData);
                var flowKeyAudio = getChatAssistantFlowRoutingKey();
                if (flowKeyAudio) formData.append('flow_routing_key', flowKeyAudio);
                if (previewOnlyAi) formData.append('preview_only', '1');
                fetch(assistantUrl, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
                    .then(function(r) { return r.text().then(function(t) { return { status: r.status, body: t }; }); })
                    .then(function(res) {
                        var data;
                        try {
                            data = JSON.parse(res.body);
                        } catch (e) {
                            if (res.status === 419) throw new Error('{{ __("Sesión caducada. Recarga la página.") }}');
                            if (res.status >= 400) throw new Error('{{ __("Error del servidor. Intenta de nuevo o recarga la página.") }}');
                            throw new Error('{{ __("Respuesta no válida del servidor.") }}');
                        }
                        document.getElementById('aiPreviewLoader').classList.add('d-none');
                        document.getElementById('aiPreviewContent').classList.remove('d-none');
                        var errAudio = document.getElementById('aiAssistantPreviewError');
                        var taAudio = document.getElementById('aiResponsePreview');
                        if (errAudio) {
                            errAudio.classList.add('d-none');
                            errAudio.innerHTML = '';
                        }
                        if (data.success) {
                            if (data.transcript) {
                                currentUserMessage = data.transcript;
                                var up = document.getElementById('userMessagePreview');
                                if (up) up.textContent = currentUserMessage;
                            }
                            currentAiResponse = data.response || '';
                            if (taAudio) {
                                taAudio.value = currentAiResponse;
                                taAudio.disabled = false;
                            }
                            if (data.audio_base64 && data.audio_mime) {
                                currentAiAudioBase64 = data.audio_base64;
                                currentAiAudioMime = data.audio_mime;
                                var container = document.getElementById('aiResponsePreviewAudio');
                                if (container) {
                                    container.innerHTML = '<audio controls class="w-100 mt-2" style="max-height:40px;"><source src="data:' + data.audio_mime + ';base64,' + data.audio_base64 + '" type="' + data.audio_mime + '"></audio>';
                                }
                            }
                            if (isAssistantView) {
                                appendAssistantExchangeToChat(currentUserMessage, currentAiResponse, currentAiAudioBase64, currentAiAudioMime, currentAttachmentPreviews);
                                if (data.redirect_url && typeof data.redirect_url === 'string') {
                                    window.location.assign(data.redirect_url);
                                    return;
                                }
                                if (data.task_status_update && typeof window.humanoKanbanMoveTask === 'function') {
                                    var tsu = data.task_status_update;
                                    window.humanoKanbanMoveTask(tsu.task_id, tsu.status_id || tsu.status_name);
                                }
                                if (data.action_performed === 'document_ingestion') {
                                    registerLocalDocumentEvents(currentUserMessage, currentAiResponse, currentAttachmentPreviews);
                                }
                                syncSidebarAssistantAutoRespondFromResponse(data);
                                messageInput.value = '';
                                if (attachmentInput) attachmentInput.value = '';
                                updateAttachmentCount();
                                currentUserMessage = '';
                                currentAiResponse = '';
                                currentAiAudioBase64 = '';
                                currentAiAudioMime = '';
                                currentAttachmentPreviews = [];
                                if (window.refreshAssistantHistory && data.action_performed !== 'document_ingestion') window.refreshAssistantHistory();
                            }
                        } else {
                            currentAiResponse = '';
                            if (taAudio) {
                                taAudio.value = '';
                                taAudio.disabled = false;
                            }
                            if (errAudio) {
                                errAudio.classList.remove('d-none');
                                errAudio.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Error').replace(/</g, '&lt;') + '</div>';
                            }
                        }
                    })
                    .catch(function(err) {
                        document.getElementById('aiPreviewLoader').classList.add('d-none');
                        document.getElementById('aiPreviewContent').classList.remove('d-none');
                        var taE = document.getElementById('aiResponsePreview');
                        if (taE) {
                            taE.value = '';
                            taE.disabled = false;
                        }
                        var errE = document.getElementById('aiAssistantPreviewError');
                        if (errE) {
                            errE.classList.remove('d-none');
                            errE.innerHTML = '<div class="alert alert-danger mb-0">' + (err.message || '{{ __("Error de conexión") }}').replace(/</g, '&lt;') + '</div>';
                        }
                        if (isAssistantView) removeAssistantTypingIndicator();
                    })
                    .finally(function() { if (isAssistantView) removeAssistantTypingIndicator(); reenableSend(); });
            } else {
                var jsonPayload = {
                    message: currentUserMessage,
                    recipient: toVal || undefined,
                    contact_id: contactId,
                    respond_with_audio: respondWithAudio,
                    preview_only: previewOnlyAi
                };
                var flowKeyJson = getChatAssistantFlowRoutingKey();
                if (flowKeyJson) jsonPayload.flow_routing_key = flowKeyJson;
                fetch(assistantUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify(jsonPayload)
                })
                .then(function(r) { return r.text().then(function(t) { return { status: r.status, body: t }; }); })
                .then(function(res) {
                    var data;
                    try {
                        data = JSON.parse(res.body);
                    } catch (e) {
                        if (res.status === 419) throw new Error('{{ __("Sesión caducada. Recarga la página.") }}');
                        if (res.status >= 400) throw new Error('{{ __("Error del servidor. Intenta de nuevo o recarga la página.") }}');
                        throw new Error('{{ __("Respuesta no válida del servidor.") }}');
                    }
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    document.getElementById('aiPreviewContent').classList.remove('d-none');
                    var errJson = document.getElementById('aiAssistantPreviewError');
                    var taJson = document.getElementById('aiResponsePreview');
                    if (errJson) {
                        errJson.classList.add('d-none');
                        errJson.innerHTML = '';
                    }
                    if (data.success) {
                        if (data.transcript) {
                            currentUserMessage = data.transcript;
                            var upEl = document.getElementById('userMessagePreview');
                            if (upEl) upEl.textContent = currentUserMessage;
                        }
                        currentAiResponse = data.response || '';
                        if (taJson) {
                            taJson.value = currentAiResponse;
                            taJson.disabled = false;
                        }
                        if (data.audio_base64 && data.audio_mime) {
                            currentAiAudioBase64 = data.audio_base64;
                            currentAiAudioMime = data.audio_mime;
                            var containerJ = document.getElementById('aiResponsePreviewAudio');
                            if (containerJ) {
                                containerJ.innerHTML = '<audio controls class="w-100 mt-2" style="max-height:40px;"><source src="data:' + data.audio_mime + ';base64,' + data.audio_base64 + '" type="' + data.audio_mime + '"></audio>';
                            }
                        }
                        if (isAssistantView) {
                            appendAssistantExchangeToChat(currentUserMessage, currentAiResponse, currentAiAudioBase64, currentAiAudioMime, currentAttachmentPreviews);
                            if (data.redirect_url && typeof data.redirect_url === 'string') {
                                window.location.assign(data.redirect_url);
                                return;
                            }
                            if (data.task_status_update && typeof window.humanoKanbanMoveTask === 'function') {
                                var tsuJ = data.task_status_update;
                                window.humanoKanbanMoveTask(tsuJ.task_id, tsuJ.status_id || tsuJ.status_name);
                            }
                            if (data.action_performed === 'document_ingestion') {
                                registerLocalDocumentEvents(currentUserMessage, currentAiResponse, currentAttachmentPreviews);
                            }
                            syncSidebarAssistantAutoRespondFromResponse(data);
                            messageInput.value = '';
                            if (attachmentInput) attachmentInput.value = '';
                            updateAttachmentCount();
                            currentUserMessage = '';
                            currentAiResponse = '';
                            currentAiAudioBase64 = '';
                            currentAiAudioMime = '';
                            currentAttachmentPreviews = [];
                            if (window.refreshAssistantHistory && data.action_performed !== 'document_ingestion') window.refreshAssistantHistory();
                        }
                    } else {
                        currentAiResponse = '';
                        if (taJson) {
                            taJson.value = '';
                            taJson.disabled = false;
                        }
                        if (errJson) {
                            errJson.classList.remove('d-none');
                            errJson.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + String(data.message || 'Failed to get response').replace(/</g, '&lt;') + '</div>';
                        }
                    }
                })
                .catch(function(error) {
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    document.getElementById('aiPreviewContent').classList.remove('d-none');
                    currentAiResponse = '';
                    var taErr = document.getElementById('aiResponsePreview');
                    if (taErr) {
                        taErr.value = '';
                        taErr.disabled = false;
                    }
                    var errBoxJ = document.getElementById('aiAssistantPreviewError');
                    if (errBoxJ) {
                        errBoxJ.classList.remove('d-none');
                        errBoxJ.innerHTML = '<div class="alert alert-danger mb-0">' + (error.message || '{{ __("Error de conexión") }}').replace(/</g, '&lt;') + '</div>';
                    }
                    if (isAssistantView) removeAssistantTypingIndicator();
                })
                .finally(function() { if (isAssistantView) removeAssistantTypingIndicator(); reenableSend(); });
            }

            return false;
        }, true);

        // Send the previewed AI response when confirmed (capture so we run first)
        sendAiResponseBtn.addEventListener('click', function() {
            var taSend = document.getElementById('aiResponsePreview');
            var replyFromPreview = taSend && taSend.value ? taSend.value.trim() : (currentAiResponse || '').trim();
            if (currentUserMessage && replyFromPreview) {
                currentAiResponse = replyFromPreview;
                previewModal.hide();

                var form = document.getElementById('chat-form');
                var isAssistantView = form && form.getAttribute('data-view-assistant') === '1';
                var list = isAssistantView ? document.getElementById('assistant-messages-list') : document.querySelector('.chat-history');
                var esc = function(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
                var timeStr = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

                if (isAssistantView && list) {
                    var empty = list.querySelector('.assistant-empty-state');
                    if (empty) empty.remove();
                    var userLi = document.createElement('li');
                    userLi.className = 'chat-message chat-message-right';
                    userLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + (currentUserMessage || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p></div><div class="text-end text-muted mt-1"><small>' + timeStr + '</small></div></div>' + buildChatAvatarHtml(chatMessageAvatars.user, 'ms-3') + '</div>';
                    list.appendChild(userLi);
                    var aiLi = document.createElement('li');
                    aiLi.className = 'chat-message';
                    var audioHtml = (currentAiAudioBase64 && currentAiAudioMime) ? '<div class="mt-2"><audio controls class="w-100" style="max-height:40px;"><source src="data:' + currentAiAudioMime + ';base64,' + currentAiAudioBase64 + '" type="' + currentAiAudioMime + '"></audio></div>' : '';
                    var aiContent = typeof renderMarkdownForChat === 'function' ? renderMarkdownForChat(currentAiResponse) : currentAiResponse.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                    aiLi.innerHTML = '<div class="d-flex overflow-hidden">' + buildChatAvatarHtml(chatMessageAvatars.assistant, 'me-3') + '<div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text assistant-markdown"><div class="mb-0">' + aiContent + '</div>' + audioHtml + '</div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
                    list.appendChild(aiLi);
                    var body = document.querySelector('.chat-history-body');
                    if (body) body.scrollTop = body.scrollHeight;
                } else if (list) {
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const cleanTo = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';

                    var aiMsg = document.createElement('li');
                    aiMsg.className = 'chat-message';
                    aiMsg.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + currentAiResponse.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p></div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div>' + buildChatAvatarHtml(chatMessageAvatars.assistant, 'ms-3') + '</div>';
                    list.appendChild(aiMsg);

                    var chatHistory = document.querySelector('.chat-history-body');
                    if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;

                    if (cleanTo) {
                        fetch('{{ route("chat.send") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ to: cleanTo, message: currentAiResponse, use_ai: false })
                        })
                        .then(humChatParseSendFetchResponse)
                        .then(function(res) {
                            if (!humChatSendSucceeded(res)) {
                                showChatSendErrorBar(humChatSendErrorFromResult(res));
                                return;
                            }
                        })
                        .catch(function(err) {
                            console.error('Error sending AI message:', err);
                            showChatSendErrorBar('{{ __("Error de conexión") }}');
                        });
                    }
                }

                messageInput.value = '';
                currentUserMessage = '';
                currentAiResponse = '';
                if (window.refreshAssistantHistory) window.refreshAssistantHistory();
            }
        });

        // --- Schedule message ---
        var scheduleAiResponseBtn = document.getElementById('scheduleAiResponseBtn');
        var scheduleAiResponseLabel = document.getElementById('scheduleAiResponseLabel');
        var confirmScheduleBtn = document.getElementById('confirmScheduleBtn');
        var cancelScheduleBtn = document.getElementById('cancelScheduleBtn');
        var scheduleFlatpickr = null;

        function scheduleResetState() {
            if (scheduleFlatpickr) scheduleFlatpickr.clear();
            if (scheduleAiResponseLabel) { scheduleAiResponseLabel.textContent = ''; scheduleAiResponseLabel.classList.add('d-none'); }
            if (confirmScheduleBtn) confirmScheduleBtn.classList.add('d-none');
            if (cancelScheduleBtn) cancelScheduleBtn.classList.add('d-none');
        }

        var chatFpLocaleKey = @json($chatFpLocaleBundle ? $chatFpLocale : '');

        function initScheduleFlatpickr() {
            if (scheduleFlatpickr) return;
            var scheduleDateInput = document.getElementById('scheduleMessageDatetime');
            if (!scheduleDateInput || typeof flatpickr === 'undefined') return;
            var scheduleModalEl = document.getElementById('claudePreviewModal');
            var fpOpts = {
                enableTime: true,
                time_24hr: true,
                dateFormat: 'd/m/Y H:i',
                minDate: 'today',
                minuteIncrement: 5,
                clickOpens: false,
                positionElement: scheduleAiResponseBtn || scheduleDateInput,
                appendTo: scheduleModalEl || document.body,
                onChange: function(selectedDates, dateStr) {
                    if (selectedDates.length) {
                        if (scheduleAiResponseLabel) { scheduleAiResponseLabel.textContent = dateStr; scheduleAiResponseLabel.classList.remove('d-none'); }
                        if (confirmScheduleBtn) confirmScheduleBtn.classList.remove('d-none');
                        if (cancelScheduleBtn) cancelScheduleBtn.classList.remove('d-none');
                    } else {
                        scheduleResetState();
                    }
                },
            };
            if (chatFpLocaleKey && flatpickr.l10ns && flatpickr.l10ns[chatFpLocaleKey]) {
                fpOpts.locale = flatpickr.l10ns[chatFpLocaleKey];
            } else {
                fpOpts.locale = { firstDayOfWeek: 1 };
            }
            scheduleFlatpickr = flatpickr(scheduleDateInput, fpOpts);
        }

        var claudePreviewModalEl = document.getElementById('claudePreviewModal');
        if (claudePreviewModalEl) {
            claudePreviewModalEl.addEventListener('shown.bs.modal', initScheduleFlatpickr);
            claudePreviewModalEl.addEventListener('hidden.bs.modal', scheduleResetState);
        }

        if (scheduleAiResponseBtn) {
            scheduleAiResponseBtn.addEventListener('click', function () {
                initScheduleFlatpickr();
                if (scheduleFlatpickr) scheduleFlatpickr.open();
            });
        }

        if (cancelScheduleBtn) {
            cancelScheduleBtn.addEventListener('click', scheduleResetState);
        }

        if (confirmScheduleBtn) {
            confirmScheduleBtn.addEventListener('click', function () {
                var taSend = document.getElementById('aiResponsePreview');
                var replyBody = taSend && taSend.value ? taSend.value.trim() : (currentAiResponse || '').trim();
                var cleanTo = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';
                var selectedDates = scheduleFlatpickr ? scheduleFlatpickr.selectedDates : [];

                if (!replyBody) { alert('{{ __("No hay respuesta para programar.") }}'); return; }
                if (!cleanTo) { alert('{{ __("No hay destinatario.") }}'); return; }
                if (!selectedDates.length) { alert('{{ __("Selecciona la fecha y hora de envío.") }}'); return; }

                var scheduledAt = selectedDates[0].toISOString();
                var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                confirmScheduleBtn.disabled = true;
                confirmScheduleBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __("Programando...") }}';

                fetch('{{ route("chat.schedule-message") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ recipient: cleanTo, body: replyBody, scheduled_at: scheduledAt, channel: 'whatsapp' })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        previewModal.hide();
                        var label = new Date(data.scheduled_at).toLocaleString('{{ app()->getLocale() }}', { dateStyle: 'medium', timeStyle: 'short' });
                        messageInput.value = '';
                        currentUserMessage = '';
                        currentAiResponse = '';
                        if (window.refreshContactChatMessages) window.refreshContactChatMessages();
                        var toastEl = document.createElement('div');
                        toastEl.className = 'alert alert-success alert-dismissible position-fixed bottom-0 end-0 m-3';
                        toastEl.style.zIndex = 9999;
                        toastEl.innerHTML = '<i class="ti ti-calendar-check me-1"></i>{{ __("Mensaje programado para") }} ' + label + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        document.body.appendChild(toastEl);
                        setTimeout(function () { toastEl.remove(); }, 5000);
                    } else {
                        alert(data.message || '{{ __("Error al programar el mensaje.") }}');
                    }
                })
                .catch(function () { alert('{{ __("Error de conexión al programar.") }}'); })
                .finally(function () {
                    confirmScheduleBtn.disabled = false;
                    confirmScheduleBtn.innerHTML = '<i class="ti ti-calendar-check me-1"></i>{{ __("Programar") }}';
                    scheduleResetState();
                });
            });
        }
        // --- End schedule message ---

        (function initChatComposeScheduleModal() {
            var confirmBtn = document.getElementById('chat-schedule-confirm-btn');
            var deleteBtn = document.getElementById('chat-schedule-delete-btn');
            var scheduleInput = document.getElementById('chat-schedule-at-input');
            var scheduleModalEl = document.getElementById('chatScheduleModal');
            var scheduleTitleEl = document.getElementById('chatScheduleModalLabel');
            if (!confirmBtn || !scheduleInput || !scheduleModalEl) {
                return;
            }

            var chatComposeScheduleFp = null;
            var createTitle = scheduleTitleEl ? scheduleTitleEl.textContent : '';
            var editTitle = @json(__('Editar mensaje programado'));
            var confirmCreateLabel = @json(__('Programar'));
            var confirmEditLabel = @json(__('Guardar'));

            function resetChatScheduleModalForCreate() {
                scheduleModalEl.removeAttribute('data-editing-id');
                if (scheduleTitleEl) {
                    scheduleTitleEl.textContent = createTitle;
                }
                confirmBtn.textContent = confirmCreateLabel;
                if (deleteBtn) {
                    deleteBtn.classList.add('d-none');
                }
                if (chatComposeScheduleFp) {
                    chatComposeScheduleFp.clear();
                } else if (scheduleInput) {
                    scheduleInput.value = '';
                }
            }

            function chatScheduleMinimumDate() {
                var now = new Date();
                var increment = 5;
                var ms = increment * 60 * 1000;
                return new Date(Math.ceil(now.getTime() / ms) * ms);
            }

            function refreshChatScheduleMinDate() {
                if (!chatComposeScheduleFp) {
                    return;
                }
                chatComposeScheduleFp.set('minDate', chatScheduleMinimumDate());
            }

            function initChatComposeScheduleFlatpickr() {
                if (chatComposeScheduleFp || typeof flatpickr === 'undefined') {
                    return;
                }
                var fpOpts = {
                    enableTime: true,
                    time_24hr: true,
                    dateFormat: 'd/m/Y H:i',
                    minDate: chatScheduleMinimumDate(),
                    minuteIncrement: 5,
                    allowInput: false,
                    clickOpens: true,
                };
                if (chatFpLocaleKey && flatpickr.l10ns && flatpickr.l10ns[chatFpLocaleKey]) {
                    fpOpts.locale = flatpickr.l10ns[chatFpLocaleKey];
                } else {
                    fpOpts.locale = { firstDayOfWeek: 1 };
                }
                chatComposeScheduleFp = flatpickr(scheduleInput, fpOpts);
            }

            window.openChatScheduleEditModal = function (trigger) {
                if (!trigger) {
                    return;
                }
                initChatComposeScheduleFlatpickr();
                refreshChatScheduleMinDate();
                var scheduledId = trigger.getAttribute('data-scheduled-id') || '';
                var scheduledAt = trigger.getAttribute('data-scheduled-at') || '';
                if (!scheduledId) {
                    return;
                }
                scheduleModalEl.setAttribute('data-editing-id', scheduledId);
                if (scheduleTitleEl) {
                    scheduleTitleEl.textContent = editTitle;
                }
                confirmBtn.textContent = confirmEditLabel;
                if (deleteBtn) {
                    deleteBtn.classList.remove('d-none');
                }
                if (chatComposeScheduleFp && scheduledAt) {
                    chatComposeScheduleFp.setDate(new Date(scheduledAt), true);
                }
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(scheduleModalEl).show();
                }
            };

            scheduleModalEl.addEventListener('shown.bs.modal', function () {
                initChatComposeScheduleFlatpickr();
                refreshChatScheduleMinDate();
            });
            scheduleModalEl.addEventListener('hidden.bs.modal', resetChatScheduleModalForCreate);

            confirmBtn.addEventListener('click', function () {
                var editingId = scheduleModalEl.getAttribute('data-editing-id');
                var msg = messageInput && messageInput.value ? messageInput.value.trim() : '';
                var cleanTo = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';
                var selectedDates = chatComposeScheduleFp ? chatComposeScheduleFp.selectedDates : [];

                if (!editingId && !msg) {
                    alert(@json(__('Escribe un mensaje para programar.')));
                    return;
                }
                if (!editingId && !cleanTo) {
                    alert(@json(__('No hay destinatario.')));
                    return;
                }
                if (!selectedDates.length) {
                    alert(@json(__('Selecciona la fecha y hora de envío.')));
                    return;
                }

                var tokenEl = document.querySelector('meta[name="csrf-token"]');
                var token = tokenEl ? tokenEl.getAttribute('content') : '';
                var scheduledAt = selectedDates[0].toISOString();
                var isEditing = !!editingId;
                var url = isEditing
                    ? '{{ url('chat/scheduled-message') }}/' + encodeURIComponent(editingId)
                    : '{{ route("chat.schedule-message") }}';
                var method = isEditing ? 'PATCH' : 'POST';
                var payload = { scheduled_at: scheduledAt, channel: 'whatsapp' };
                if (!isEditing) {
                    payload.recipient = cleanTo;
                    payload.body = msg;
                }

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (isEditing ? confirmEditLabel : @json(__('Programando...')));

                fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (!isEditing && messageInput) {
                            messageInput.value = '';
                        }
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var inst = bootstrap.Modal.getInstance(scheduleModalEl);
                            if (inst) {
                                inst.hide();
                            }
                        }
                        if (window.refreshContactChatMessages) {
                            window.refreshContactChatMessages();
                        }
                        var label = new Date(data.scheduled_at).toLocaleString('{{ app()->getLocale() }}', { dateStyle: 'medium', timeStyle: 'short' });
                        var toastEl = document.createElement('div');
                        toastEl.className = 'alert alert-success alert-dismissible position-fixed bottom-0 end-0 m-3';
                        toastEl.style.zIndex = 9999;
                        toastEl.innerHTML = '<i class="ti ti-calendar-check me-1"></i>' + (isEditing ? @json(__('Mensaje reprogramado para')) : @json(__('Mensaje programado para'))) + ' ' + label + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        document.body.appendChild(toastEl);
                        setTimeout(function () { toastEl.remove(); }, 5000);
                    } else {
                        alert(data.message || '{{ __("Error al programar el mensaje.") }}');
                    }
                })
                .catch(function () {
                    alert('{{ __("Error de conexión al programar.") }}');
                })
                .finally(function () {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = isEditing ? confirmEditLabel : confirmCreateLabel;
                });
            });

            if (deleteBtn) {
                deleteBtn.addEventListener('click', function () {
                    var editingId = scheduleModalEl.getAttribute('data-editing-id');
                    if (!editingId) {
                        return;
                    }
                    if (!window.confirm(@json(__('¿Eliminar este mensaje programado?')))) {
                        return;
                    }
                    var tokenEl = document.querySelector('meta[name="csrf-token"]');
                    var token = tokenEl ? tokenEl.getAttribute('content') : '';
                    deleteBtn.disabled = true;
                    fetch('{{ url('chat/scheduled-message') }}/' + encodeURIComponent(editingId), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var inst = bootstrap.Modal.getInstance(scheduleModalEl);
                                if (inst) {
                                    inst.hide();
                                }
                            }
                            if (window.refreshContactChatMessages) {
                                window.refreshContactChatMessages();
                            }
                        } else {
                            alert(data.message || '{{ __("Error al eliminar el mensaje programado.") }}');
                        }
                    })
                    .catch(function () {
                        alert('{{ __("Error de conexión al eliminar.") }}');
                    })
                    .finally(function () {
                        deleteBtn.disabled = false;
                    });
                });
            }
        })();

        var chatAssistantRegenerateBtn = document.getElementById('chatAssistantRegenerateBtn');
        if (chatAssistantRegenerateBtn) {
            chatAssistantRegenerateBtn.addEventListener('click', function() {
                if (!currentUserMessage) return;
                var regenToken = document.querySelector('meta[name="csrf-token"]');
                regenToken = regenToken ? regenToken.getAttribute('content') : '';
                var regenTo = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';
                var regenCidEl = document.getElementById('contact-id');
                var regenContactId = (regenCidEl && regenCidEl.value && parseInt(regenCidEl.value, 10)) ? parseInt(regenCidEl.value, 10) : undefined;
                var regenAudio = document.getElementById('respond-with-audio') && document.getElementById('respond-with-audio').checked;
                var regenForm = document.getElementById('chat-form');
                var regenIsAssistant = regenForm && regenForm.getAttribute('data-view-assistant') === '1';
                document.getElementById('aiPreviewLoader').classList.remove('d-none');
                chatAssistantRegenerateBtn.disabled = true;
                var taR = document.getElementById('aiResponsePreview');
                if (taR) { taR.value = ''; taR.disabled = true; }
                var ebR = document.getElementById('aiAssistantPreviewError');
                if (ebR) {
                    ebR.classList.add('d-none');
                    ebR.innerHTML = '';
                }
                var audioElR = document.getElementById('aiResponsePreviewAudio');
                if (audioElR) audioElR.innerHTML = '';
                currentAiAudioBase64 = '';
                currentAiAudioMime = '';
                var regenPayload = {
                    message: currentUserMessage,
                    recipient: regenTo || undefined,
                    contact_id: regenContactId,
                    respond_with_audio: regenAudio,
                    preview_only: !regenIsAssistant
                };
                var regenFk = getChatAssistantFlowRoutingKey();
                if (regenFk) regenPayload.flow_routing_key = regenFk;
                fetch(assistantUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': regenToken, 'Accept': 'application/json' },
                    body: JSON.stringify(regenPayload)
                })
                .then(function(r) { return r.text().then(function(t) { return { status: r.status, body: t }; }); })
                .then(function(res) {
                    var data;
                    try {
                        data = JSON.parse(res.body);
                    } catch (e) {
                        if (res.status === 419) throw new Error('{{ __("Sesión caducada. Recarga la página.") }}');
                        if (res.status >= 400) throw new Error('{{ __("Error del servidor. Intenta de nuevo o recarga la página.") }}');
                        throw new Error('{{ __("Respuesta no válida del servidor.") }}');
                    }
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    var errR = document.getElementById('aiAssistantPreviewError');
                    var taRx = document.getElementById('aiResponsePreview');
                    if (errR) {
                        errR.classList.add('d-none');
                        errR.innerHTML = '';
                    }
                    if (data.success) {
                        currentAiResponse = data.response || '';
                        if (taRx) {
                            taRx.value = currentAiResponse;
                            taRx.disabled = false;
                        }
                        if (data.audio_base64 && data.audio_mime) {
                            currentAiAudioBase64 = data.audio_base64;
                            currentAiAudioMime = data.audio_mime;
                            var cR = document.getElementById('aiResponsePreviewAudio');
                            if (cR) {
                                cR.innerHTML = '<audio controls class="w-100 mt-2" style="max-height:40px;"><source src="data:' + data.audio_mime + ';base64,' + data.audio_base64 + '" type="' + data.audio_mime + '"></audio>';
                            }
                        }
                    } else {
                        currentAiResponse = '';
                        if (taRx) {
                            taRx.value = '';
                            taRx.disabled = false;
                        }
                        if (errR) {
                            errR.classList.remove('d-none');
                            errR.innerHTML = '<div class="alert alert-danger mb-0">' + String(data.message || 'Error').replace(/</g, '&lt;') + '</div>';
                        }
                    }
                })
                .catch(function(err) {
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    var taE2 = document.getElementById('aiResponsePreview');
                    if (taE2) {
                        taE2.value = '';
                        taE2.disabled = false;
                    }
                    var eb2 = document.getElementById('aiAssistantPreviewError');
                    if (eb2) {
                        eb2.classList.remove('d-none');
                        eb2.innerHTML = '<div class="alert alert-danger mb-0">' + (err.message || '{{ __("Error de conexión") }}').replace(/</g, '&lt;') + '</div>';
                    }
                })
                .finally(function() {
                    chatAssistantRegenerateBtn.disabled = false;
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    var taFinal = document.getElementById('aiResponsePreview');
                    if (taFinal) taFinal.disabled = false;
                    if (regenIsAssistant) return;
                    var sb = document.querySelector('#chat-form .chat-send-primary-btn');
                    if (sb) sb.disabled = false;
                });
            });
        }

        @if($viewAssistant ?? false)
        // Poll assistant history so messages from terminal appear without full page reload
        (function() {
            var list = document.getElementById('assistant-messages-list');
            if (!list) return;
            var assistantUserId = {!! json_encode(optional($selectedAssistantUser)->id) !!};
            var historyUrl = '{{ route("chat.assistant-history") }}' + (assistantUserId ? '?user_id=' + assistantUserId : '');
            var resetContextUrl = '{{ route("chat.assistant-reset-context") }}' + (assistantUserId ? '?user_id=' + assistantUserId : '');
            var refreshBtn = document.getElementById('assistant-refresh-btn');
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var assistantHistoryInitialSyncDone = false;

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            function formatDate(iso) {
                var d = new Date(iso);
                return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            }
            function renderMessages(messages) {
                var mergedMessages = (messages || []).slice();
                if (localDocumentEvents.length > 0) {
                    mergedMessages = mergedMessages.concat(localDocumentEvents);
                }

                if (!mergedMessages || mergedMessages.length === 0) {
                    var src = document.getElementById('assistant-suggestions-source');
                    var inner = src ? src.innerHTML : '';
                    list.innerHTML = '<li class="text-center p-4 assistant-empty-state">' +
                        '<div class="text-start">' + inner + '</div></li>';
                    return;
                }
                var html = mergedMessages.map(function(m) {
                    var isAssistant = m.role === 'assistant';
                    var content = isAssistant && typeof renderMarkdownForChat === 'function'
                        ? renderMarkdownForChat(m.content || '')
                        : escapeHtml(decodeHtmlEntities(m.content || '')).replace(/\n/g, '<br>');
                    var time = formatDate(m.created_at);
                    var sideClass = isAssistant ? '' : 'chat-message-right';
                    var timeClass = isAssistant ? '' : 'text-end';
                    var contentWrap = isAssistant ? '<div class="assistant-markdown mb-0">' + content + '</div>' : '<p class="mb-0">' + content + '</p>';
                    var avatarHtml = isAssistant
                        ? buildChatAvatarHtml((chatMessageAvatars.assistant || {}), 'me-3')
                        : buildChatAvatarHtml((chatMessageAvatars.user || {}), 'ms-3');
                    if (isAssistant) {
                        return '<li class="chat-message ' + sideClass + '">' +
                            '<div class="d-flex overflow-hidden">' + avatarHtml +
                            '<div class="chat-message-wrapper flex-grow-1">' +
                            '<div class="chat-message-text">' + contentWrap + '</div>' +
                            '<div class="text-muted mt-1 ' + timeClass + '"><small>' + time + '</small></div>' +
                            '</div></div></li>';
                    }
                    return '<li class="chat-message ' + sideClass + '">' +
                        '<div class="d-flex overflow-hidden">' +
                        '<div class="chat-message-wrapper flex-grow-1">' +
                        '<div class="chat-message-text">' + contentWrap + '</div>' +
                        '<div class="text-muted mt-1 ' + timeClass + '"><small>' + time + '</small></div>' +
                        '</div>' + avatarHtml + '</div></li>';
                }).join('');
                var body = document.querySelector('.chat-history-body');
                var wasPinned = chatHistoryIsPinnedToBottom(body);
                var distBottom = chatHistoryDistanceFromBottom(body);
                var msgs = mergedMessages || [];
                var forceFirstBottom = !assistantHistoryInitialSyncDone && msgs.length > 0;
                if (forceFirstBottom) {
                    assistantHistoryInitialSyncDone = true;
                }
                list.innerHTML = html;
                if (forceFirstBottom) {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            if (body) body.scrollTop = body.scrollHeight;
                        });
                    });
                } else {
                    chatHistoryRestoreScrollAfterReplace(body, wasPinned, distBottom);
                }
            }
            function fetchHistory() {
                fetch(historyUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.avatars) {
                            chatMessageAvatars = data.avatars;
                        }
                        renderMessages(data.messages || []);
                    })
                    .catch(function() {});
            }
            function resetAssistantContext() {
                fetch(resetContextUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.success) {
                            renderMessages([]);
                        }
                    })
                    .catch(function() {});
            }
            setInterval(fetchHistory, 5000);
            if (refreshBtn) refreshBtn.addEventListener('click', resetAssistantContext);
            window.addEventListener('focus', fetchHistory);
            window.refreshAssistantHistory = fetchHistory;
        })();
        @endif

        // Poll WhatsApp conversation messages so new incoming/sent messages appear without refresh
        (function () {
            var body = document.getElementById('chat-history-body');
            if (!body) return;
            var pollPhone = body.getAttribute('data-poll-phone');
            var isAssistant = body.getAttribute('data-view-assistant') === '1';
            if (!pollPhone || isAssistant) return;

            var list = document.getElementById('assistant-messages-list');
            var messagesUrl = '{{ url("chat/messages") }}/' + encodeURIComponent(pollPhone);
            var contactChatInitialSyncDone = false;

            function escapeHtml(s) {
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }
            function formatTime(message) {
                if (!message) return '';
                var isScheduled = !!(message.is_scheduled || message.status === 'scheduled');
                var at = (isScheduled && message.scheduled_at) ? message.scheduled_at : message.created_at;
                if (!at) return '';
                var d = new Date(at);
                var now = new Date();
                var sameDay = d.getFullYear() === now.getFullYear()
                    && d.getMonth() === now.getMonth()
                    && d.getDate() === now.getDate();
                var timeStr = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                if (isScheduled && !sameDay) {
                    var dateStr = d.toLocaleDateString('{{ str_replace('_', '-', app()->getLocale()) }}', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    });
                    return dateStr + ' ' + timeStr;
                }
                return timeStr;
            }
            function statusIcon(status, isScheduled) {
                if (isScheduled || status === 'scheduled') return '<i class="ti ti-calendar-time ti-xs me-1"></i>';
                if (status === 'failed' || status === 'undelivered') return '<i class="ti ti-alert-circle ti-xs me-1 text-danger"></i>';
                if (status === 'read') return '<i class="ti ti-checks ti-xs me-1 text-primary"></i>';
                if (status === 'delivered') return '<i class="ti ti-checks ti-xs me-1 text-success"></i>';
                if (status === 'sent') return '<i class="ti ti-check ti-xs me-1 text-success"></i>';
                return '<i class="ti ti-clock ti-xs me-1"></i>';
            }
            function renderContactMessages(messages) {
                if (!messages || messages.length === 0) {
                    list.innerHTML = '<li class="text-center p-4"><p class="text-muted mb-0">Aún no hay mensajes en esta conversación.</p></li>';
                    return;
                }
                var html = messages.map(function (m) {
                    var inbound = (m.direction || '').toLowerCase() === 'inbound';
                    var sideClass = inbound ? '' : 'chat-message-right';
                    var timeClass = inbound ? '' : 'text-end';
                    var bodyEscaped = escapeHtml(decodeHtmlEntities(m.body || '')).replace(/\n/g, '<br>');
                    var time = formatTime(m);
                    var isScheduled = !!m.is_scheduled || m.status === 'scheduled';
                    var scheduledId = m.scheduled_message_id || (String(m.id || '').replace(/^scheduled-/, ''));
                    var scheduledAtIso = m.scheduled_at || '';
                    var fromSuffix = (m.from || '').toString().slice(-2);
                    var inboundAvatar = chatMessageAvatars.contact || { initials: fromSuffix, label_class: 'bg-label-success' };
                    var outboundAvatar = m.sender_avatar || chatMessageAvatars.current_user || { initials: fromSuffix, label_class: 'bg-label-primary' };
                    var media = (typeof m.media === 'string' ? (function () { try { return JSON.parse(m.media); } catch (e) { return []; } })() : m.media) || [];
                    var mediaHtml = media.length ? media.map(function (item) {
                        var url = item.url || item;
                        var ct = (item.content_type || '').toLowerCase();
                        if (ct.indexOf('image/') === 0) {
                            return '<a href="#" data-bs-toggle="modal" data-bs-target="#chatImageModal" data-img="' + escapeHtml(url) + '"><img src="' + escapeHtml(url) + '" alt="media" style="max-width:200px;max-height:200px;border-radius:8px;margin-bottom:4px;"></a>';
                        }
                        if (ct.indexOf('audio/') === 0) {
                            return '<audio controls class="mt-1" style="max-width:240px;max-height:40px;"><source src="' + escapeHtml(url) + '" type="' + escapeHtml(ct) + '"></audio>';
                        }
                        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(typeof item === 'object' ? (item.filename || 'Archivo') : 'Archivo') + '</a>';
                    }).join('') : '';
                    if (inbound) {
                        return '<li class="chat-message ' + sideClass + '"><div class="d-flex overflow-hidden">' + buildChatAvatarHtml(inboundAvatar, 'me-3') + '<div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + bodyEscaped + '</p>' + (mediaHtml ? '<div class="chat-media mt-2">' + mediaHtml + '</div>' : '') + '</div><div class="' + timeClass + ' text-muted mt-1"><small>' + escapeHtml(time) + '</small></div></div></div></li>';
                    }
                    var metaHtml = isScheduled
                        ? '<div class="' + timeClass + ' text-muted mt-1"><span class="chat-scheduled-meta-trigger" role="button" tabindex="0" data-scheduled-id="' + escapeHtml(String(scheduledId)) + '" data-scheduled-at="' + escapeHtml(String(scheduledAtIso)) + '" title="{{ e(__('Editar mensaje programado')) }}">' + statusIcon(m.status, true) + '<small>' + escapeHtml(time) + '</small></span></div>'
                        : '<div class="' + timeClass + ' text-muted mt-1">' + statusIcon(m.status, isScheduled) + '<small>' + escapeHtml(time) + '</small></div>';
                    return '<li class="chat-message ' + sideClass + '"><div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + bodyEscaped + '</p>' + (mediaHtml ? '<div class="chat-media mt-2">' + mediaHtml + '</div>' : '') + '</div>' + metaHtml + '</div>' + buildChatAvatarHtml(outboundAvatar, 'ms-3') + '</div></li>';
                }).join('');
                var scrollEl = document.querySelector('.chat-history-body');
                var wasPinnedWa = chatHistoryIsPinnedToBottom(scrollEl);
                var distBottomWa = chatHistoryDistanceFromBottom(scrollEl);
                var mlist = messages || [];
                var forceWaFirst = !contactChatInitialSyncDone && mlist.length > 0;
                if (forceWaFirst) {
                    contactChatInitialSyncDone = true;
                }
                list.innerHTML = html;
                if (forceWaFirst) {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
                        });
                    });
                } else {
                    chatHistoryRestoreScrollAfterReplace(scrollEl, wasPinnedWa, distBottomWa);
                }
            }

            function fetchContactMessages() {
                fetch(messagesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { renderContactMessages(data.messages || []); })
                    .catch(function () {});
            }
            setInterval(fetchContactMessages, 4000);
            window.addEventListener('focus', fetchContactMessages);
            window.refreshContactChatMessages = fetchContactMessages;

            list.addEventListener('click', function (e) {
                var trigger = e.target.closest('.chat-scheduled-meta-trigger');
                if (!trigger || typeof window.openChatScheduleEditModal !== 'function') {
                    return;
                }
                e.preventDefault();
                window.openChatScheduleEditModal(trigger);
            });
            list.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var trigger = e.target.closest('.chat-scheduled-meta-trigger');
                if (!trigger || typeof window.openChatScheduleEditModal !== 'function') {
                    return;
                }
                e.preventDefault();
                window.openChatScheduleEditModal(trigger);
            });
        })();

        // Poll WhatsApp conversation list so new chats appear in the sidebar without refresh
        (function () {
            var listEl = document.getElementById('chat-list-whatsapp');
            if (!listEl) return;
            var chatUrl = listEl.getAttribute('data-chat-url') || '{{ route("chat.index") }}';
            var listUrl = '{{ route("chat.list") }}';

            function chatListFetchUrl() {
                var sel = document.getElementById('chat-contact-status-filter');
                if (!sel || !sel.value) return listUrl;
                return listUrl + (listUrl.indexOf('?') >= 0 ? '&' : '?') + 'crm_status=' + encodeURIComponent(sel.value);
            }
            function buildChatIndexHrefWithPhone(fromDigits) {
                var qs = [];
                var sel = document.getElementById('chat-contact-status-filter');
                if (sel && sel.value) qs.push('crm_status=' + encodeURIComponent(sel.value));
                qs.push('phone=' + encodeURIComponent(fromDigits));
                var sep = chatUrl.indexOf('?') >= 0 ? '&' : '?';
                return chatUrl + sep + qs.join('&');
            }

            function escapeHtml(s) {
                if (s == null) return '';
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }
            function limit(str, n) {
                if (str == null) return '';
                return String(str).length > n ? String(str).slice(0, n) + '...' : String(str);
            }
            function renderChatList(contacts, selectedPhone) {
                selectedPhone = selectedPhone || '';
                if (!contacts || contacts.length === 0) {
                    var hasWa = listEl.getAttribute('data-team-has-wa-number') === '1';
                    var msg = hasWa ? '{{ __("No WhatsApp conversations") }}' : '{{ __("Link a WhatsApp number in the sidebar to see conversations here.") }}';
                    listEl.innerHTML =
                        '<li class="chat-contact-list-item chat-list-item-0">' +
                        '<a href="#" class="d-block px-4 py-2 text-muted text-decoration-none cursor-pointer" role="button" onclick="event.preventDefault();" data-bs-toggle="sidebar" data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">' +
                        '<h6 class="text-muted mb-0">' + escapeHtml(msg) + '</h6>' +
                        '</a></li>';
                    if (typeof window.applyChatSidebarSearch === 'function') {
                        window.applyChatSidebarSearch();
                    }
                    return;
                }
                var html = contacts.map(function (c) {
                    var active = (c.from === selectedPhone) ? ' active' : '';
                    var name = escapeHtml(c.user_name || c.from);
                    var lastMsg = escapeHtml(limit(c.last_message, 30));
                    var time = escapeHtml(c.last_message_time || '');
                    var fromSuffix = String(c.from).slice(-2);
                    var unread = parseInt(c.unread_count, 10) || 0;
                    var badge = unread > 0 ? '<span class="badge bg-success rounded-pill text-white" style="font-size: 0.7rem; min-width: 1.25rem;">' + (unread > 99 ? '99+' : unread) + '</span>' : '';
                    var avatar = c.user_photo
                        ? '<img src="' + escapeHtml(c.user_photo) + '" alt="' + name + '" class="rounded-circle">'
                        : '<span class="avatar-initial rounded-circle bg-label-success">' + escapeHtml(fromSuffix) + '</span>';
                    var href = buildChatIndexHrefWithPhone(c.from);
                    var rightCol = '<div class="d-flex flex-column align-items-end flex-shrink-0 gap-1"><small class="text-muted">' + time + '</small>' + (badge ? badge : '') + '</div>';
                    return '<li class="chat-contact-list-item' + active + '" data-phone="' + escapeHtml(c.from) + '"><a href="' + escapeHtml(href) + '" class="d-flex align-items-center"><div class="flex-shrink-0 avatar">' + avatar + '</div><div class="chat-contact-info flex-grow-1 ms-2 min-w-0"><h6 class="chat-contact-name text-truncate m-0">' + name + '</h6><p class="chat-contact-status text-muted text-truncate mb-0">' + lastMsg + '</p></div>' + rightCol + '</a></li>';
                }).join('');
                listEl.innerHTML = html;
                if (typeof window.applyChatSidebarSearch === 'function') {
                    window.applyChatSidebarSearch();
                }
            }
            function fetchChatList() {
                var body = document.getElementById('chat-history-body');
                var isAssistantView = body && body.getAttribute('data-view-assistant') === '1';
                fetch(chatListFetchUrl(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var selected = listEl.getAttribute('data-selected-phone') || '';
                        var contacts = data.contacts || [];
                        if (isAssistantView) {
                            selected = '';
                        }
                        renderChatList(contacts, selected);
                    })
                    .catch(function () {});
            }
            setInterval(fetchChatList, 5000);
            window.addEventListener('focus', fetchChatList);

            var crmStatusFilterSel = document.getElementById('chat-contact-status-filter');
            if (crmStatusFilterSel) {
                crmStatusFilterSel.addEventListener('change', function () {
                    var nextUrl = new URL(window.location.href);
                    if (!crmStatusFilterSel.value) {
                        nextUrl.searchParams.delete('crm_status');
                    } else {
                        nextUrl.searchParams.set('crm_status', crmStatusFilterSel.value);
                    }
                    window.history.replaceState({}, '', nextUrl.toString());
                    fetchChatList();
                });
            }
        })();

        // Generate new QR: submit via AJAX so sidebar stays open
        var refreshQrForm = document.getElementById('chat-refresh-qr-form');
        if (refreshQrForm) {
            refreshQrForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = refreshQrForm.querySelector('button[type="submit"]');
                var msgEl = document.getElementById('chat-refresh-qr-message');
                var qrImg = document.getElementById('chat-whatsapp-qr-img');
                var qrContainer = document.getElementById('chat-qr-container');
                var baseUrl = refreshQrForm.action;
                var token = refreshQrForm.querySelector('input[name="_token"]');
                if (btn) btn.disabled = true;
                if (qrContainer) {
                    qrContainer.classList.add('chat-qr-loading');
                    if (qrImg) qrImg.classList.add('d-none');
                }
                fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: '_token=' + encodeURIComponent(token ? token.value : '')
                })
                .then(function (r) {
                    if (!r.ok) throw new Error('refresh failed');
                    return r.json();
                })
                .then(function (data) {
                    if (msgEl && data.message) {
                        msgEl.textContent = data.message;
                        msgEl.classList.remove('d-none');
                    }
                    if (qrImg && qrImg.dataset.qrBase) {
                        var qrRetries = 0;
                        var maxRetries = 24;
                        function setQrSrc() {
                            fetchWhatsAppQrObjectUrl(qrImg.dataset.qrBase)
                                .then(function (objectUrl) {
                                    return assignWhatsAppQrToImage(qrImg, objectUrl).then(function () {
                                        return objectUrl;
                                    });
                                })
                                .then(function () {
                                    if (qrContainer) {
                                        qrContainer.classList.remove('chat-qr-loading');
                                    }
                                    qrImg.classList.remove('d-none');
                                    if (document.getElementById('chat-qr-fallback')) {
                                        document.getElementById('chat-qr-fallback').classList.add('d-none');
                                    }
                                })
                                .catch(function () {
                                    if (qrRetries < maxRetries) {
                                        qrRetries += 1;
                                        if (qrContainer) {
                                            qrContainer.classList.add('chat-qr-loading');
                                        }
                                        setTimeout(setQrSrc, 1100);
                                        return;
                                    }
                                    if (qrContainer) {
                                        qrContainer.classList.remove('chat-qr-loading');
                                    }
                                    qrImg.classList.add('d-none');
                                    var fbEnd = document.getElementById('chat-qr-fallback');
                                    if (fbEnd) {
                                        fbEnd.classList.add('d-none');
                                    }
                                });
                        }
                        setTimeout(setQrSrc, 650);
                    } else {
                        if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                        if (qrImg) qrImg.classList.remove('d-none');
                    }
                })
                .catch(function () {
                    if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                    if (qrImg) qrImg.classList.remove('d-none');
                    if (msgEl) {
                        msgEl.textContent = '{{ __("An error occurred. Try again.") }}';
                        msgEl.classList.remove('d-none');
                    }
                })
                .finally(function () {
                    if (btn) btn.disabled = false;
                });
            });
        }

        var waConnectionBlock = document.getElementById('chat-sidebar-whatsapp-connection-block');
        var waTeamWasConnected = {{ ($teamWhatsAppIsConnected ?? false) ? 'true' : 'false' }};
        var waQrRefreshInFlight = false;
        var chatWaQrManualBtn = document.getElementById('chat-whatsapp-qr-refresh-btn');
        var waStatusUrlForQr = '{{ route("chat.whatsapp-status") }}';
        var waWarmupUrlForQr = '{{ route("chat.whatsapp-warmup-qr") }}';
        var waServiceErrMsg = @json(__('auth.registration.qr_whatsapp_service_unreachable'));
        var waLoadErrMsg = @json(__('auth.registration.qr_whatsapp_load_failed'));
        var waQrMinPx = 100;

        function isValidWhatsAppQrImage(img) {
            return !!img && img.naturalWidth >= waQrMinPx && img.naturalHeight >= waQrMinPx;
        }

        function fetchWhatsAppQrObjectUrl(qrBase) {
            var url = qrBase + (qrBase.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();

            return fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'image/png' },
            }).then(function (response) {
                if (response.status === 204 || response.status === 404) {
                    throw new Error('qr-not-ready');
                }
                if (!response.ok) {
                    throw new Error('qr-fetch-failed');
                }

                return response.blob();
            }).then(function (blob) {
                if (!blob || blob.size < 100) {
                    throw new Error('qr-not-ready');
                }

                return URL.createObjectURL(blob);
            });
        }

        function assignWhatsAppQrToImage(img, objectUrl) {
            return new Promise(function (resolve, reject) {
                if (!img) {
                    reject(new Error('qr-img-missing'));

                    return;
                }

                img.onload = function () {
                    img.onload = null;
                    img.onerror = null;
                    if (isValidWhatsAppQrImage(img)) {
                        resolve(img);
                    } else {
                        reject(new Error('qr-not-ready'));
                    }
                };
                img.onerror = function () {
                    img.onload = null;
                    img.onerror = null;
                    reject(new Error('qr-not-ready'));
                };
                img.src = objectUrl;
            });
        }

        function hideWhatsAppQrUi() {
            collectWaQrScopes().forEach(function (s) {
                if (s.container) {
                    s.container.classList.remove('chat-qr-loading');
                }
                if (s.img) {
                    s.img.classList.add('d-none');
                    s.img.removeAttribute('src');
                }
                if (s.fallback) {
                    s.fallback.classList.add('d-none');
                }
                if (s.err) {
                    s.err.classList.add('d-none');
                    s.err.textContent = '';
                }
            });
        }

        function collectWaQrScopes() {
            var scopes = [];
            function push(containerId, imgId, fallbackId, errId) {
                var container = document.getElementById(containerId);
                var img = document.getElementById(imgId);
                if (container && img && img.dataset.qrBase) {
                    scopes.push({
                        container: container,
                        img: img,
                        fallback: document.getElementById(fallbackId),
                        err: document.getElementById(errId),
                    });
                }
            }
            push('chat-qr-container', 'chat-whatsapp-qr-img', 'chat-qr-fallback', 'chat-qr-service-error');
            push('chat-history-qr-container', 'chat-whatsapp-qr-img-history', 'chat-history-qr-fallback', 'chat-history-qr-service-error');

            return scopes;
        }

        function waitForWhatsAppQrReady(maxAttempts, intervalMs) {
            maxAttempts = maxAttempts || 45;
            intervalMs = intervalMs || 800;

            return new Promise(function (resolve)
            {
                var attempts = 0;

                function poll()
                {
                    fetch(waStatusUrlForQr, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (data)
                        {
                            if (data && (data.isTeamConnected || data.status === 'connected'))
                            {
                                window.location.reload();

                                return;
                            }
                            if (data && data.status === 'waiting_qr')
                            {
                                resolve();

                                return;
                            }
                            attempts += 1;
                            if (attempts >= maxAttempts)
                            {
                                resolve();

                                return;
                            }
                            setTimeout(poll, intervalMs);
                        })
                        .catch(function ()
                        {
                            resolve();
                        });
                }

                poll();
            });
        }

        function runWhatsappQrServerRefreshAndPoll(isManualTrigger) {
            if (isManualTrigger === undefined) {
                isManualTrigger = false;
            }
            if (waQrRefreshInFlight) {
                return;
            }
            var scopes = collectWaQrScopes();
            if (scopes.length === 0) {
                return;
            }
            waQrRefreshInFlight = true;
            if (chatWaQrManualBtn) {
                chatWaQrManualBtn.disabled = true;
            }
            var token = document.querySelector('meta[name="csrf-token"]');
            var t = token ? token.getAttribute('content') : '';

            function releaseRefresh() {
                waQrRefreshInFlight = false;
                if (chatWaQrManualBtn) {
                    chatWaQrManualBtn.disabled = false;
                }
            }

            if (!t) {
                releaseRefresh();

                return;
            }
            scopes.forEach(function (s) {
                if (s.err) {
                    s.err.classList.add('d-none');
                    s.err.textContent = '';
                }
                if (s.container) {
                    s.container.classList.add('chat-qr-loading');
                }
                if (s.img) {
                    s.img.classList.add('d-none');
                }
                if (s.fallback) {
                    s.fallback.classList.remove('d-none');
                }
            });

            fetch(waStatusUrlForQr, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (statusData) {
                    if (statusData && (statusData.isTeamConnected || statusData.status === 'connected')) {
                        if (typeof applyWaStatus === 'function') {
                            applyWaStatus(statusData);
                        }
                        releaseRefresh();
                        return null;
                    }
                    if (statusData && statusData.status === 'unreachable') {
                        throw new Error(waServiceErrMsg);
                    }
                    if (statusData && statusData.status === 'waiting_qr') {
                        return { qrReady: true };
                    }
                    var prepareUrl = isManualTrigger
                        ? '{{ route("chat.whatsapp-refresh-qr") }}'
                        : waWarmupUrlForQr;
                    return fetch(prepareUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': t,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: '_token=' + encodeURIComponent(t),
                    }).then(function (r) {
                        return r.json().then(function (data) {
                            return { r: r, data: data && typeof data === 'object' ? data : {} };
                        }).catch(function () {
                            return { r: r, data: {} };
                        });
                    }).then(function (payload) {
                        if (!payload.r.ok || payload.data.ok === false) {
                            var failMsg = payload.data.message || waServiceErrMsg;
                            throw new Error(failMsg);
                        }
                        return payload;
                    });
                })
                .then(function (prepareResult) {
                    if (prepareResult === null) {
                        return;
                    }
                    var probeImg = scopes[0].img;
                    if (!probeImg || !probeImg.dataset.qrBase) {
                        scopes.forEach(function (s) {
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.fallback) {
                                s.fallback.classList.add('d-none');
                            }
                        });
                        releaseRefresh();

                        return;
                    }
                    var qrRetries = 0;
                    var maxRetries = 36;
                    var retryMs = 1100;
                    var loadErrMsg = waLoadErrMsg;

                    function setScopesLoadingUi(active) {
                        scopes.forEach(function (s) {
                            if (!s.container) {
                                return;
                            }
                            if (active) {
                                s.container.classList.add('chat-qr-loading');
                                if (s.img) {
                                    s.img.classList.add('d-none');
                                }
                                if (s.fallback) {
                                    s.fallback.classList.remove('d-none');
                                }
                            } else {
                                s.container.classList.remove('chat-qr-loading');
                            }
                        });
                    }

                    function finishFailure() {
                        scopes.forEach(function (s) {
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.img) {
                                s.img.classList.add('d-none');
                            }
                            if (s.fallback) {
                                s.fallback.classList.add('d-none');
                            }
                            if (s.err) {
                                s.err.textContent = loadErrMsg;
                                s.err.classList.remove('d-none');
                            }
                        });
                        probeImg.onload = null;
                        probeImg.onerror = null;
                        releaseRefresh();
                    }

                    function applyQrSuccessAll(loadedSrc) {
                        if (!isValidWhatsAppQrImage(probeImg)) {
                            finishFailure();
                            return;
                        }
                        scopes.forEach(function (s) {
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.img) {
                                s.img.classList.remove('d-none');
                                s.img.src = loadedSrc;
                            }
                            if (s.fallback) {
                                s.fallback.classList.add('d-none');
                            }
                            if (s.err) {
                                s.err.classList.add('d-none');
                                s.err.textContent = '';
                            }
                        });
                        probeImg.onload = null;
                        probeImg.onerror = null;
                        releaseRefresh();
                    }

                    function bumpQrSrc() {
                        fetchWhatsAppQrObjectUrl(probeImg.dataset.qrBase)
                            .then(function (objectUrl) {
                                return assignWhatsAppQrToImage(probeImg, objectUrl).then(function () {
                                    return objectUrl;
                                });
                            })
                            .then(function () {
                                fetch(waStatusUrlForQr, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                                    .then(function (r) { return r.json(); })
                                    .then(function (statusData) {
                                        if (statusData && (statusData.isTeamConnected || statusData.status === 'connected')) {
                                            hideWhatsAppQrUi();
                                            if (typeof applyWaStatus === 'function') {
                                                applyWaStatus(statusData);
                                            }
                                            window.location.reload();
                                            return;
                                        }
                                        applyQrSuccessAll(probeImg.src);
                                    })
                                    .catch(function () {
                                        applyQrSuccessAll(probeImg.src);
                                    });
                            })
                            .catch(function () {
                                if (qrRetries < maxRetries) {
                                    qrRetries += 1;
                                    setScopesLoadingUi(true);
                                    setTimeout(bumpQrSrc, retryMs);
                                } else {
                                    finishFailure();
                                }
                            });
                    }

                    function startQrImagePoll() {
                        setTimeout(bumpQrSrc, 450);
                    }

                    if (prepareResult.qrReady) {
                        startQrImagePoll();
                    } else {
                        waitForWhatsAppQrReady().then(startQrImagePoll);
                    }
                })
                .catch(function (err) {
                    var netMsg = (err && err.message) ? err.message : waServiceErrMsg;
                    scopes.forEach(function (s) {
                        if (s.container) {
                            s.container.classList.remove('chat-qr-loading');
                        }
                        if (s.fallback) {
                            s.fallback.classList.add('d-none');
                        }
                        if (s.err) {
                            s.err.textContent = netMsg;
                            s.err.classList.remove('d-none');
                        }
                    });
                    releaseRefresh();
                });
        }

        if (waConnectionBlock && waConnectionBlock.getAttribute('data-wa-status') !== 'connected') {
            if (collectWaQrScopes().length > 0) {
                runWhatsappQrServerRefreshAndPoll(false);
            }
        }

        if (chatWaQrManualBtn) {
            chatWaQrManualBtn.addEventListener('click', function () {
                runWhatsappQrServerRefreshAndPoll(true);
            });
        }

        // Poll WhatsApp status so UI always reflects current team (fixes wrong state when switching team)
        var waStatusUrl = '{{ route("chat.whatsapp-status") }}';
        var connectedLabel = '{{ __("Connected") }}';
        var disconnectedLabel = '{{ __("Disconnected") }}';
        var scanQrLabel = '{{ __("Scan QR") }}';
        function applyWaStatus(data) {
            var titleEl = document.getElementById('chat-sidebar-wa-title');
            var badgeEl = document.getElementById('chat-sidebar-wa-badge');
            var badgeWrap = document.getElementById('chat-sidebar-wa-badge-wrap');
            var disconnectBadgeTrigger = document.getElementById('chat-whatsapp-disconnect-badge-trigger');
            var avatarEl = document.getElementById('chat-sidebar-wa-avatar');
            var contactsWaAvatar = document.getElementById('chat-contacts-wa-avatar');
            var displayNumber = data.teamNumberFormatted || data.numberFormatted || null;
            var gatewayConnected = data.status === 'connected';
            if (titleEl) titleEl.textContent = displayNumber || '{{ __("Not linked") }}';
            if (data.isTeamConnected || gatewayConnected) {
                hideWhatsAppQrUi();
                waTeamWasConnected = true;
                if (waConnectionBlock) { waConnectionBlock.classList.add('d-none'); }
                var historyWaPanel = document.getElementById('chat-history-wa-connect-panel');
                if (historyWaPanel) {
                    historyWaPanel.classList.add('d-none');
                }
                if (badgeEl) { badgeEl.textContent = connectedLabel; badgeEl.className = 'badge bg-success'; }
                if (badgeWrap) { badgeWrap.classList.add('chat-wa-disconnect-enabled'); }
                if (disconnectBadgeTrigger) { disconnectBadgeTrigger.disabled = false; }
                if (avatarEl) { avatarEl.classList.remove('avatar-offline'); avatarEl.classList.add('avatar-online'); }
                if (contactsWaAvatar) { contactsWaAvatar.classList.remove('avatar-offline'); contactsWaAvatar.classList.add('avatar-online'); }
            } else {
                var prevTeamConnected = waTeamWasConnected;
                waTeamWasConnected = false;
                if (waConnectionBlock) {
                    waConnectionBlock.classList.remove('d-none');
                    var historyWaPanelOff = document.getElementById('chat-history-wa-connect-panel');
                    if (historyWaPanelOff) {
                        historyWaPanelOff.classList.remove('d-none');
                    }
                    var qrImgReload = document.getElementById('chat-whatsapp-qr-img');
                    if (prevTeamConnected && qrImgReload && qrImgReload.dataset.qrBase) {
                        runWhatsappQrServerRefreshAndPoll();
                    }
                }
                if (badgeEl) {
                    var status = data.status || 'disconnected';
                    badgeEl.textContent = status === 'waiting_qr' ? scanQrLabel : disconnectedLabel;
                    badgeEl.className = status === 'waiting_qr' ? 'badge bg-warning' : 'badge bg-secondary';
                }
                if (badgeWrap) { badgeWrap.classList.remove('chat-wa-disconnect-enabled'); }
                if (disconnectBadgeTrigger) { disconnectBadgeTrigger.disabled = true; }
                if (avatarEl) { avatarEl.classList.remove('avatar-online'); avatarEl.classList.add('avatar-offline'); }
                if (contactsWaAvatar) { contactsWaAvatar.classList.remove('avatar-online'); contactsWaAvatar.classList.add('avatar-offline'); }
            }
        }
        if (waConnectionBlock) {
            fetch(waStatusUrl, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(applyWaStatus).catch(function () {});
            var waPoll = setInterval(function () {
                fetch(waStatusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        applyWaStatus(data);
                        if (data.isTeamConnected || data.status === 'connected') clearInterval(waPoll);
                    })
                    .catch(function () {});
            }, 3000);
        }

        var chatWaDisconnectBadgeTrigger = document.getElementById('chat-whatsapp-disconnect-badge-trigger');
        if (chatWaDisconnectBadgeTrigger && typeof Swal !== 'undefined') {
            chatWaDisconnectBadgeTrigger.addEventListener('click', function () {
                if (chatWaDisconnectBadgeTrigger.disabled) {
                    return;
                }
                Swal.fire({
                    title: '{{ __("Disconnect from WhatsApp?") }}',
                    text: '{{ __("You will be signed out of WhatsApp for this team. You will need to scan the QR code again to reconnect.") }}',
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: false,
                    showCloseButton: false,
                    confirmButtonText: '{{ __("Disconnect") }}',
                    cancelButtonText: '{{ __("Cancel") }}',
                    confirmButtonColor: '#d33',
                    didOpen: function (popup) {
                        var denyBtn = popup.querySelector('.swal2-deny');
                        if (denyBtn) {
                            denyBtn.style.setProperty('display', 'none', 'important');
                        }
                    },
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }
                    var token = document.querySelector('meta[name="csrf-token"]');
                    var t = token ? token.getAttribute('content') : '';
                    if (!t) {
                        return;
                    }
                    chatWaDisconnectBadgeTrigger.disabled = true;
                    fetch("{{ route('chat.whatsapp-disconnect') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': t,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: '_token=' + encodeURIComponent(t),
                    })
                        .then(function (r) {
                            return r.json().then(function (data) {
                                return { r: r, data: data && typeof data === 'object' ? data : {} };
                            }).catch(function () {
                                return { r: r, data: {} };
                            });
                        })
                        .then(function (payload) {
                            var r = payload.r;
                            var data = payload.data;
                            chatWaDisconnectBadgeTrigger.disabled = false;
                            if (!r.ok || data.ok === false) {
                                var msg = (data && data.message) ? data.message : "{{ __('Could not disconnect from WhatsApp.') }}";
                                Swal.fire({
                                    icon: 'error',
                                    title: "{{ __('Error') }}",
                                    text: msg,
                                    showDenyButton: false,
                                    showCloseButton: false,
                                });

                                return;
                            }
                            Swal.fire({
                                title: "{{ __('Disconnected') }}",
                                text: (data && data.message) ? data.message : "{{ __('You have been disconnected from WhatsApp for this team.') }}",
                                icon: 'success',
                                showConfirmButton: false,
                                showCancelButton: false,
                                showDenyButton: false,
                                showCloseButton: false,
                                timer: 2200,
                                timerProgressBar: true,
                            }).then(function () {
                                if (window.location) {
                                    window.location.reload();
                                }
                            });
                        })
                        .catch(function () {
                            chatWaDisconnectBadgeTrigger.disabled = false;
                            Swal.fire({
                                icon: 'error',
                                title: "{{ __('Error') }}",
                                text: "{{ __('Could not disconnect from WhatsApp.') }}",
                                showDenyButton: false,
                                showCloseButton: false,
                            });
                        });
                });
            });
        }

    });
    </script>
@endsection

@section('content')
    <div class="app-chat card overflow-hidden" data-team-id="{{ auth()->user()->currentTeam?->id ?? '' }}">
        <div class="row g-0">
            <!-- Sidebar Left -->
            <div class="col app-chat-sidebar-left app-sidebar overflow-hidden" id="app-chat-sidebar-left">
                <div
                    class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-4 pt-5">
                    @php
                        $sidebarLeftAvatarStatus = 'avatar-online';
                        if (($whatsappDriver ?? 'twilio') === 'local') {
                            $sidebarLeftAvatarStatus = ($teamWhatsAppIsConnected ?? false) ? 'avatar-online' : 'avatar-offline';
                        }
                    @endphp
                    <div id="chat-sidebar-wa-avatar" class="avatar avatar-xl {{ $sidebarLeftAvatarStatus }}">
                        <span class="avatar-initial rounded-circle bg-label-success"><i class="ti ti-brand-whatsapp" style="font-size: 2rem;"></i></span>
                    </div>
                    @if(($whatsappDriver ?? 'twilio') === 'local')
                        <h5 id="chat-sidebar-wa-title" class="mt-2 mb-0">{{ !empty($teamWhatsAppNumberFormatted ?? null) ? $teamWhatsAppNumberFormatted : __('Not linked') }}</h5>
                    @else
                        <h5 class="mt-2 mb-0">{{ auth()->user()->name ?? 'John Doe' }}</h5>
                    @endif
                    @if(($whatsappDriver ?? 'twilio') === 'local' && isset($whatsappStatus))
                        @php
                            $waConnected = $teamWhatsAppIsConnected ?? false;
                            $status = $whatsappStatus['status'] ?? 'unreachable';
                            $badgeClass = $waConnected ? 'success' : ($status === 'waiting_qr' ? 'warning' : 'secondary');
                            $statusLabel = $waConnected ? __('Connected') : ($status === 'waiting_qr' ? __('Scan QR') : __('Disconnected'));
                        @endphp
                        <div id="chat-sidebar-wa-badge-wrap" class="position-relative d-inline-block flex-shrink-0 mt-1 chat-wa-badge-disconnect-wrap {{ $waConnected ? 'chat-wa-disconnect-enabled' : '' }}">
                            <span id="chat-sidebar-wa-badge" class="badge bg-{{ $badgeClass }}">{{ $statusLabel }}</span>
                            <div class="chat-wa-disconnect-hover-positioner">
                                <button type="button" id="chat-whatsapp-disconnect-badge-trigger" class="btn btn-danger chat-wa-disconnect-hover-trigger" @disabled(!$waConnected) title="{{ __('Disconnect WhatsApp') }}" aria-label="{{ __('Disconnect WhatsApp') }}">
                                    <i class="ti ti-logout me-1"></i>{{ __('Disconnect') }}
                                </button>
                            </div>
                        </div>
                    @else
                        <span>Admin</span>
                    @endif
                    <i class="ti ti-x ti-sm cursor-pointer close-sidebar" data-bs-toggle="sidebar" data-overlay
                        data-target="#app-chat-sidebar-left"></i>
                </div>
                <div class="sidebar-body px-4 pb-4">
                    @if(($whatsappDriver ?? 'twilio') === 'local')
                    <div class="my-4">
                            <div id="chat-sidebar-whatsapp-connection-block" class="{{ ($teamWhatsAppIsConnected ?? false) ? 'd-none' : '' }}" data-wa-status="{{ $whatsappStatus['status'] ?? 'disconnected' }}">
                            <small class="text-muted text-uppercase">{{ __('WhatsApp connection') }}</small>
                            <div class="d-grid gap-2 mt-3">
                                @if(!empty($qrImageUrl))
                                    <div class="d-inline-block text-center" id="chat-qr-container">
                                        <img id="chat-whatsapp-qr-img" alt="WhatsApp QR" class="d-block mx-auto d-none" width="200" height="200" decoding="async" data-qr-base="{{ url($qrImageUrl) }}">
                                        <div id="chat-qr-fallback" class="mb-2 d-none">
                                            <div class="chat-qr-fallback-frame mx-auto">
                                                <div class="chat-qr-loading-overlay d-flex flex-column align-items-center justify-content-center gap-2" role="status" aria-live="polite">
                                                    <div class="spinner-border text-primary" style="width: 2.25rem; height: 2.25rem;" aria-hidden="true"></div>
                                                    <span class="small text-muted text-center px-2">{{ __('auth.registration.qr_whatsapp_loading') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p id="chat-qr-service-error" class="small text-danger mb-0 mt-2 text-center d-none" role="alert"></p>
                                    <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_timing_hint') }}</p>
                                    <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_refresh_hint') }}</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100" id="chat-whatsapp-qr-refresh-btn">
                                        <i class="ti ti-refresh me-1"></i>{{ __('auth.registration.qr_whatsapp_refresh') }}
                                    </button>
                                @endif
                            </div>
                            </div>
                    </div>
                    @endif
                    <div
                        class="my-4"
                        id="chat-team-settings-sidebar-toggles"
                        data-can-manage="{{ ($canManageChatTeamSidebarSettings ?? false) ? '1' : '0' }}">
                        <small class="text-muted text-uppercase">{{ __('Settings') }}</small>
                        @php
                            $sidebarReadOnly = !($canManageChatTeamSidebarSettings ?? false);
                        @endphp
                        <ul class="list-unstyled d-grid gap-2 mt-3 pe-3">
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('Humano Assistant replies') }}">
                                    <i class="ti ti-robot me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Humano Assistant replies') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-ai-replies-toggle"
                                        data-team-setting-key="assistant_auto_respond"
                                        @checked($assistantAutoRespond ?? false) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('When Humano Assistant replies is off, still auto-reply only for team admins and editors (not clients).') }}">
                                    <i class="ti ti-user-shield me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Assistant replies only for admins (when assistant off)') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif @if($assistantAutoRespond ?? false) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-assistant-admins-when-off-toggle"
                                        data-team-setting-key="assistant_auto_respond_admins_when_off"
                                        @checked($assistantAutoRespondAdminsWhenOff ?? false)
                                        @if($sidebarReadOnly || ($assistantAutoRespond ?? false)) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('How flows are chosen: AI asks vs automatic keyword routing.') }}">
                                    <i class="ti ti-sparkles me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Default assistant flow (AI discovery)') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-default-assistant-flow-toggle"
                                        data-team-setting-key="assistant_keyword_intent_routing"
                                        data-team-setting-invert="1"
                                        @checked(!($assistantKeywordIntentRouting ?? false)) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('Keyword routing') }}">
                                    <i class="ti ti-webhook me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Keyword routing') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-assistant-keyword-routing-toggle"
                                        data-team-setting-key="assistant_keyword_intent_routing"
                                        @checked($assistantKeywordIntentRouting ?? false) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="w-100 mt-2 pt-3 border-top border-light">
                                <small class="text-muted text-uppercase">{{ __('Show') }}</small>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('When ON, the assistant does not call the real AI model (dev/test).') }}">
                                    <i class="ti ti-bug me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Predefined test responses') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-assistant-stub-toggle"
                                        data-team-setting-key="assistant_chat_stub"
                                        @checked($assistantChatStub ?? false) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('Oculta solo la lista de asistente con otros clientes. Tu chat con el asistente sigue visible.') }}">
                                    <i class="ti ti-layout-list me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Asistencia en usuarios') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-show-assistant-conversations-toggle"
                                        data-team-setting-key="chat_show_assistant_conversations"
                                        @checked($showAssistantConversations ?? false) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div class="pe-1 text-truncate" title="{{ __('Sección de conversaciones de WhatsApp en la lista de chats') }}">
                                    <i class="ti ti-brand-whatsapp me-1 ti-sm"></i>
                                    <span class="align-middle small">{{ __('Conversaciones de WhatsApp') }}</span>
                                </div>
                                <label class="switch switch-primary switch-sm flex-shrink-0 @if($sidebarReadOnly) opacity-50 @endif">
                                    <input type="checkbox" class="switch-input" id="sidebar-show-whatsapp-conversations-toggle"
                                        data-team-setting-key="chat_show_whatsapp_conversations"
                                        @checked($showWhatsAppConversations ?? true) @if($sidebarReadOnly) disabled @endif />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- /Sidebar Left-->

            <!-- Chat & Contacts -->
            <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
                <div class="sidebar-header">
                    <div class="d-flex align-items-center me-3 me-lg-0">
                        @php
                            $avatarStatusClass = 'avatar-online';
                            if (($whatsappDriver ?? 'twilio') === 'local') {
                                $avatarStatusClass = ($teamWhatsAppIsConnected ?? false) ? 'avatar-online' : 'avatar-offline';
                            }
                        @endphp
                        <div id="chat-contacts-wa-avatar" class="flex-shrink-0 avatar {{ $avatarStatusClass }} me-3 cursor-pointer" role="button" tabindex="0" aria-label="{{ __('WhatsApp connection and settings') }}">
                            <span class="avatar-initial rounded-circle bg-label-success"><i class="ti ti-brand-whatsapp ti-sm"></i></span>
                        </div>
                        <div class="flex-grow-1 input-group input-group-merge rounded-pill">
                            <span class="input-group-text" id="basic-addon-search31"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control chat-search-input" placeholder="{{ __('Search') }}..."
                                aria-label="{{ __('Search') }}" aria-describedby="basic-addon-search31">
                        </div>
                    </div>
                    <i class="ti ti-x cursor-pointer d-lg-none d-block position-absolute mt-2 me-1 top-0 end-0"
                        data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
                </div>
                <hr class="container-m-nx m-0">
                <div class="sidebar-body">

                    @auth
                    <div class="chat-contact-list-item-title px-4 pt-3 pb-2">
                        <label for="chat-contact-status-filter" class="visually-hidden">{{ __('Filter by contact status') }}</label>
                        <div class="flex-grow-1 input-group input-group-merge rounded-pill">
                            <span class="input-group-text" id="chat-crm-status-addon"><i class="ti ti-filter"></i></span>
                            <select id="chat-contact-status-filter" name="crm_status"
                                class="form-select"
                                title="{{ __('Filter WhatsApp chats by CRM contact status') }}"
                                aria-describedby="chat-crm-status-addon">
                                <option value="" @selected(!request()->filled('crm_status'))>{{ __('All statuses') }}</option>
                                @foreach(($contactStatuses ?? []) as $st)
                                    <option value="{{ $st->id }}" @selected(request('crm_status') == (string) $st->id || (request('crm_status') === 'none' && isset($leadContactStatusId) && (int) $st->id === (int) $leadContactStatusId))>{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endauth
                    <!-- Chats -->
                    <div id="assistant-conversations-section">
                        <div class="chat-contact-list-item-title mt-3">
                            <h6 class="text-muted text-uppercase mb-0 px-4 pb-2">{{ __('Asistente') }}</h6>
                        </div>
                        @auth
                        <ul class="list-unstyled chat-contact-list mb-0" id="chat-list">
                            <li class="chat-contact-list-item {{ ($viewAssistant ?? false) && !($selectedAssistantUser ?? null) ? 'active' : '' }}">
                                <a href="{{ route('chat.index', array_merge(request()->only('crm_status'), ['view' => 'assistant'])) }}" class="d-flex align-items-center">
                                    <div class="flex-shrink-0 avatar">
                                        <span class="avatar-initial rounded-circle bg-label-info"><i class="ti ti-robot ti-sm"></i></span>
                                    </div>
                                    <div class="chat-contact-info flex-grow-1 ms-2">
                                        <h6 class="chat-contact-name text-truncate m-0">Asistente</h6>
                                        <p class="chat-contact-status text-muted text-truncate mb-0">Mi conversación con el bot</p>
                                    </div>
                                </a>
                            </li>
                        </ul>
                        <div id="assistant-conversations-extra-section" class="@if(!($showAssistantConversations ?? false)) d-none @endif">
                            <ul class="list-unstyled chat-contact-list mb-0" id="chat-list-assistant-clients">
                                @foreach($assistantClients as $client)
                                    @if($client->id !== auth()->id())
                                    <li class="chat-contact-list-item {{ optional($selectedAssistantUser)->id === $client->id ? 'active' : '' }}">
                                        <a href="{{ route('chat.index', array_merge(request()->only('crm_status'), ['view' => 'assistant', 'user_id' => $client->id])) }}" class="d-flex align-items-center">
                                            <div class="flex-shrink-0 avatar">
                                                <span class="avatar-initial rounded-circle bg-label-success">{{ substr($client->name ?? $client->email ?? '?', 0, 2) }}</span>
                                            </div>
                                            <div class="chat-contact-info flex-grow-1 ms-2">
                                                <h6 class="chat-contact-name text-truncate m-0">{{ $client->name ?? $client->email }}</h6>
                                                <p class="chat-contact-status text-muted text-truncate mb-0">{{ $client->phone ?? $client->email }}</p>
                                            </div>
                                        </a>
                                    </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                        @endauth
                    </div>
                    <div id="whatsapp-conversations-section" class="@if(!($showWhatsAppConversations ?? true)) d-none @endif mt-4">
                        <div class="chat-contact-list-item-title">
                            <h6 class="text-muted text-uppercase mb-0 px-4 pt-1 pb-2">{{ __('WhatsApp') }}</h6>
                        </div>
                        <ul class="list-unstyled chat-contact-list mb-0" id="chat-list-whatsapp" data-chat-url="{{ route('chat.index') }}" data-selected-phone="{{ $selectedPhone ?? '' }}" data-team-has-wa-number="{{ !empty($teamWhatsAppNumber) ? '1' : '0' }}">
                        @if ($contacts->isEmpty())
                            <li class="chat-contact-list-item chat-list-item-0">
                                <a href="#"
                                   class="d-block px-4 py-2 text-muted text-decoration-none cursor-pointer"
                                   role="button"
                                   onclick="event.preventDefault();"
                                   data-bs-toggle="sidebar"
                                   data-overlay="app-overlay-ex"
                                   data-target="#app-chat-sidebar-left">
                                    <h6 class="text-muted mb-0">{{ !empty($teamWhatsAppNumber) ? __('No WhatsApp conversations') : __('Link a WhatsApp number in the sidebar to see conversations here.') }}</h6>
                                </a>
                            </li>
                        @else
                            @foreach ($contacts as $contact)
                                <li class="chat-contact-list-item {{ $selectedPhone == $contact->from ? 'active' : '' }}"
                                    data-phone="{{ $contact->from }}">
                                    <a href="{{ route('chat.index', array_merge(request()->only('crm_status'), ['phone' => $contact->from])) }}"
                                        class="d-flex align-items-center">
                                        <div class="flex-shrink-0 avatar">
                                            @if (isset($contact->user_photo))
                                                <img src="{{ Storage::url($contact->user_photo) }}"
                                                    alt="{{ $contact->user_name ?? $contact->from }}"
                                                    class="rounded-circle">
                                            @else
                                                <span class="avatar-initial rounded-circle bg-label-success">
                                                    {{ substr($contact->from, -2) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="chat-contact-info flex-grow-1 ms-2 min-w-0">
                                            <h6 class="chat-contact-name text-truncate m-0">
                                                {{ $contact->user_name ?? $contact->from }}
                                            </h6>
                                            <p class="chat-contact-status text-muted text-truncate mb-0">
                                                {{ Str::limit($contact->last_message, 30) }}
                                            </p>
                                        </div>
                                        <div class="d-flex flex-column align-items-end flex-shrink-0 gap-1">
                                            <small class="text-muted">{{ $contact->last_message_time }}</small>
                                            @if (($contact->unread_count ?? 0) > 0)
                                                <span class="badge bg-success rounded-pill text-white" style="font-size: 0.7rem; min-width: 1.25rem;">{{ $contact->unread_count > 99 ? '99+' : $contact->unread_count }}</span>
                                            @endif
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        @endif
                        </ul>
                    </div>
                    <!-- Contacts -->
                    {{-- <ul class="list-unstyled chat-contact-list mb-0" id="contact-list">
                        <li class="chat-contact-list-item chat-contact-list-item-title">
                            <h5 class="text-primary mb-0">Contacts</h5>
                        </li>
                        <li class="chat-contact-list-item contact-list-item-0 d-none">
                            <h6 class="text-muted mb-0">No Contacts Found</h6>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-offline">
                                    <img src="{{ asset('assets/img/avatars/4.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Natalie Maxwell</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">UI/UX Designer</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-busy">
                                    <img src="{{ asset('assets/img/avatars/5.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Jess Cook</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Business Analyst</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="avatar d-block flex-shrink-0">
                                    <span class="avatar-initial rounded-circle bg-label-primary">LM</span>
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Louie Mason</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Resource Manager</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-busy">
                                    <img src="{{ asset('assets/img/avatars/7.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Krystal Norton</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Business Executive</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-offline">
                                    <img src="{{ asset('assets/img/avatars/8.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Stacy Garrison</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Marketing Ninja</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="avatar d-block flex-shrink-0">
                                    <span class="avatar-initial rounded-circle bg-label-success">CM</span>
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Calvin Moore</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">UX Engineer</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-busy">
                                    <img src="{{ asset('assets/img/avatars/10.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Mary Giles</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Account Department</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-offline">
                                    <img src="{{ asset('assets/img/avatars/13.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Waldemar Mannering</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">AWS Support</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="avatar d-block flex-shrink-0">
                                    <span class="avatar-initial rounded-circle bg-label-danger">AJ</span>
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Amy Johnson</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Frontend Developer</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-offline">
                                    <img src="{{ asset('assets/img/avatars/2.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Felecia Rower</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Cloud Engineer</p>
                                </div>
                            </a>
                        </li>
                        <li class="chat-contact-list-item">
                            <a class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-busy">
                                    <img src="{{ asset('assets/img/avatars/11.png') }}" alt="Avatar"
                                        class="rounded-circle">
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">William Stephens</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Backend Developer</p>
                                </div>
                            </a>
                        </li>
                    </ul> --}}
                </div>
            </div>
            <!-- /Chat contacts -->

            <!-- Chat History -->
            <div class="col app-chat-history bg-body">
                <div class="chat-history-wrapper">
                    <div class="chat-history-header border-bottom">
                        @if ($viewAssistant ?? false)
                        <div class="d-flex overflow-hidden align-items-center justify-content-between w-100">
                            <div class="d-flex overflow-hidden align-items-center">
                                <i class="ti ti-menu-2 ti-sm cursor-pointer d-lg-none d-block me-2"
                                    data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                                <div class="flex-shrink-0 avatar">
                                    @if($selectedAssistantUser ?? null)
                                        <span class="avatar-initial rounded-circle bg-label-success">{{ substr($selectedAssistantUser->name ?? $selectedAssistantUser->email ?? '?', 0, 2) }}</span>
                                    @else
                                        <span class="avatar-initial rounded-circle bg-label-info"><i class="ti ti-robot ti-sm"></i></span>
                                    @endif
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="m-0">{{ $selectedAssistantUser->name ?? $selectedAssistantUser->email ?? 'Asistente' }}</h6>
                                    <small class="user-status text-muted">
                                        @if($selectedAssistantUser ?? null)
                                            {{ $assistantClientPhoneDisplay ?? $selectedAssistantUser->email ?? '' }} — Responde tú o activa la IA
                                        @else
                                            {{ __('Ask the assistant anything you need below.') }}
                                        @endif
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @if($selectedAssistantUser ?? null)
                                    @include('chat.partials.header-assistant-toggle')
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon" id="assistant-refresh-btn" title="{{ __('Start a new assistant conversation (hides history, keeps it stored)') }}" aria-label="{{ __('New assistant conversation') }}">
                                    <i class="ti ti-refresh"></i>
                                </button>
                            </div>
                        </div>
                        @elseif ($selectedPhone)
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex overflow-hidden align-items-center">
                                <i class="ti ti-menu-2 ti-sm cursor-pointer d-lg-none d-block me-2"
                                    data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                                <div class="flex-shrink-0 avatar">
                                    @if (isset($selectedUser) && $selectedUser->profile_photo_path)
                                        <img src="{{ Storage::url($selectedUser->profile_photo_path) }}"
                                            alt="{{ $selectedUser->name }}" class="rounded-circle"
                                            data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-right">
                                    @else
                                        <span class="avatar-initial rounded-circle bg-label-success">{{ substr($selectedPhone ?? '?', -2) }}</span>
                                    @endif
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="m-0">{{ $selectedUser->name ?? 'Cliente' }}</h6>
                                    <small class="user-status text-muted">{{ $selectedPhone }}</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                @if(isset($selectedContact) && $selectedContact->id)
                                    <a href="{{ route('contact.show', $selectedContact->id) }}" class="me-2">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                @elseif(isset($selectedUser) && $selectedUser->id)
                                    <a href="{{ route('contact.create') }}?link_user={{ $selectedUser->id }}" class="btn btn-sm btn-outline-primary me-2" title="Crear contacto">
                                        <i class="ti ti-users ti-xs me-1"></i>Vincular con contacto
                                    </a>
                                @endif
                                @include('chat.partials.header-assistant-toggle')
                            </div>
                        </div>
                        @else
                        {{-- Empty state: show WhatsApp connection hint when no conversation selected --}}
                        @if (($whatsappDriver ?? '') === 'local')
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex overflow-hidden align-items-center">
                                    <i class="ti ti-menu-2 ti-sm cursor-pointer d-lg-none d-block me-2"
                                        data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                                    @if ($teamWhatsAppIsConnected ?? false)
                                        <small class="text-muted">
                                            <i class="ti ti-brand-whatsapp ti-xs me-1"></i>{{ __('WhatsApp connected') }}
                                        </small>
                                    @else
                                        <small class="text-muted">
                                            <a href="#" class="text-primary text-decoration-none" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-left">
                                                <i class="ti ti-qrcode ti-xs me-1"></i>{{ __('Open sidebar to scan QR and link WhatsApp') }}
                                            </a>
                                        </small>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @endif
                    </div>
                    <div class="chat-history-body bg-body" id="chat-history-body" data-poll-phone="{{ $selectedPhone ?? '' }}" data-view-assistant="{{ ($viewAssistant ?? false) ? '1' : '0' }}">
                        @if ($viewAssistant ?? false)
                            <div id="assistant-suggestions-source" class="d-none" aria-hidden="true">
                                @include('chat.partials.assistant-empty-suggestions-inner')
                            </div>
                        @endif
                        <ul class="list-unstyled chat-history" id="assistant-messages-list">
                            @if ($viewAssistant ?? false)
                                @forelse(($assistantMessages ?? []) as $msg)
                                    <li class="chat-message {{ $msg['role'] !== 'assistant' ? 'chat-message-right' : '' }}">
                                        <div class="d-flex overflow-hidden">
                                            @if ($msg['role'] === 'assistant')
                                                @include('chat.partials.message-avatar', ['avatar' => $chatMessageAvatars['assistant'], 'margin' => 'me-3'])
                                            @endif
                                            <div class="chat-message-wrapper flex-grow-1">
                                                <div class="chat-message-text assistant-markdown">
                                                    <div class="mb-0">{!! \Illuminate\Support\Str::markdown(html_entity_decode($msg['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) !!}</div>
                                                </div>
                                                <div class="text-muted mt-1 {{ $msg['role'] !== 'assistant' ? 'text-end' : '' }}">
                                                    <small>{{ $msg['created_at']->format('d/m/Y H:i') }}</small>
                                                </div>
                                            </div>
                                            @if ($msg['role'] !== 'assistant')
                                                @include('chat.partials.message-avatar', ['avatar' => $chatMessageAvatars['user'], 'margin' => 'ms-3'])
                                            @endif
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-center p-4 assistant-empty-state">
                                        <div class="text-start">
                                            @include('chat.partials.assistant-empty-suggestions-inner')
                                        </div>
                                    </li>
                                @endforelse
                            @elseif (!$selectedPhone)
                                <li class="text-center p-4">
                                    <p class="text-muted mb-0">Selecciona una conversación para ver los mensajes</p>
                                </li>
                            @else
                                @foreach ($messages as $message)
                                    @php
                                        $isInbound = $message->direction === 'inbound';
                                        $isScheduledOutbound = ! $isInbound && (($message->status ?? '') === 'scheduled' || ! empty($message->is_scheduled));
                                        $displayTime = $isScheduledOutbound && ! empty($message->scheduled_at)
                                            ? \Carbon\Carbon::parse($message->scheduled_at)
                                            : $message->created_at;
                                        $scheduledDisplayLabel = ($isScheduledOutbound ?? false) && $displayTime instanceof \Carbon\CarbonInterface
                                            ? ($displayTime->isToday()
                                                ? $displayTime->format('h:i A')
                                                : $displayTime->format('d/m/Y h:i A'))
                                            : null;
                                        $media = $message->media ?? [];
                                        if (is_string($media)) {
                                            $media = json_decode($media, true) ?? [];
                                        }
                                    @endphp
                                    <li class="chat-message {{ !$isInbound ? 'chat-message-right' : '' }}">
                                        <div class="d-flex overflow-hidden">
                                            @if ($isInbound)
                                                @include('chat.partials.message-avatar', ['avatar' => $chatMessageAvatars['contact'], 'margin' => 'me-3'])
                                            @endif
                                            <div class="chat-message-wrapper flex-grow-1">
                                                <div class="chat-message-text">
                                                    @if ($isScheduledOutbound)
                                                        <p class="mb-0">{!! nl2br(e($message->body)) !!}</p>
                                                    @else
                                                        <p class="mb-0">{!! nl2br($message->body) !!}</p>
                                                    @endif
                                                    @if (!empty($media))
                                                        <div class="chat-media mt-2">
                                                            @foreach ($media as $item)
                                                                @php
                                                                    $contentType = $item['content_type'] ?? '';
                                                                @endphp
                                                                @if(Str::startsWith($contentType, 'image/'))
                                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#chatImageModal" data-img="{{ $item['url'] }}">
                                                                        <img src="{{ $item['url'] }}" alt="media" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-bottom: 4px;">
                                                                    </a>
                                                                @elseif(Str::startsWith($contentType, 'audio/'))
                                                                    <audio controls class="mt-1" style="max-width: 240px; max-height: 40px;">
                                                                        <source src="{{ $item['url'] }}" type="{{ $contentType }}">
                                                                    </audio>
                                                                @else
                                                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                                                                        {{ basename($item['url'] ?? '') }}
                                                                    </a>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="{{ !$isInbound ? 'text-end' : '' }} text-muted mt-1">
                                                    @if ($isScheduledOutbound)
                                                        <span class="chat-scheduled-meta-trigger"
                                                            role="button"
                                                            tabindex="0"
                                                            data-scheduled-id="{{ $message->scheduled_message_id }}"
                                                            data-scheduled-at="{{ $displayTime->toIso8601String() }}"
                                                            title="{{ __('Editar mensaje programado') }}">
                                                            <i class='ti ti-calendar-time ti-xs me-1'></i>
                                                            <small>{{ $scheduledDisplayLabel }}</small>
                                                        </span>
                                                    @else
                                                        @if (!$isInbound)
                                                            @if($message instanceof \App\Models\Conversation && $message->hasFailed())
                                                                <i class='ti ti-alert-circle ti-xs me-1 text-danger'></i>
                                                            @elseif($message instanceof \App\Models\Conversation && $message->isRead())
                                                                <i class='ti ti-checks ti-xs me-1 text-primary'></i>
                                                            @elseif($message instanceof \App\Models\Conversation && $message->isDelivered())
                                                                <i class='ti ti-checks ti-xs me-1 text-success'></i>
                                                            @elseif($message instanceof \App\Models\Conversation && $message->status === 'sent')
                                                                <i class='ti ti-check ti-xs me-1 text-success'></i>
                                                            @elseif (!$isInbound)
                                                                <i class='ti ti-clock ti-xs me-1'></i>
                                                            @endif
                                                        @endif
                                                        <small>{{ $displayTime->format('h:i A') }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                            @if (!$isInbound)
                                                @php
                                                    $outboundAvatar = isset($message->user_id) && isset($users[$message->user_id])
                                                        ? \App\Support\ChatMessageAvatar::forUser($users[$message->user_id])
                                                        : $chatMessageAvatars['current_user'];
                                                @endphp
                                                @include('chat.partials.message-avatar', ['avatar' => $outboundAvatar, 'margin' => 'ms-3'])
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                        {{-- WhatsApp QR panel intentionally hidden in chat history view --}}
                    </div>
                    <!-- Chat message form -->
                    <div class="chat-history-footer d-flex flex-column">
                        <div id="chat-send-error-bar"
                            class="alert alert-danger py-2 px-3 mb-2 d-none w-100 small"
                            role="alert"
                            aria-live="assertive"></div>
                        <form id="chat-form" class="form-send-message d-flex justify-content-between align-items-center" @if($viewAssistant ?? false) data-view-assistant="1" @endif>
                            @csrf
                            <input type="hidden" id="recipient" value="{{ $selectedAssistantUser ? ($clientRecipientPhone ?? '') : ($selectedPhone ?? '') }}">
                            @php
                                $chatConversationKey = '';
                                if ($viewAssistant ?? false) {
                                    $chatConversationKey = isset($selectedAssistantUser) && $selectedAssistantUser
                                        ? 'assistant-' . $selectedAssistantUser->id
                                        : 'assistant-me';
                                } elseif (!empty($selectedPhone)) {
                                    $chatConversationKey = 'phone-' . $selectedPhone;
                                }
                            @endphp
                            <input type="hidden" id="chat-conversation-key" value="{{ $chatConversationKey }}">
                            @if(isset($selectedContact) && $selectedContact)
                                <input type="hidden" id="contact-id" value="{{ $selectedContact->id }}">
                            @elseif($selectedAssistantUser ?? null)
                                <input type="hidden" id="contact-id" value="{{ $assistantContactId ?? '' }}">
                            @else
                                <input type="hidden" id="contact-id" value="">
                            @endif

                            <div class="d-flex align-items-center w-100 flex-grow-1">
                                @if($viewAssistant ?? false)
                                <div class="d-flex align-items-center me-2">
                                    <div class="form-check form-switch mb-0 d-none">
                                        <input type="checkbox" class="form-check-input" id="respond-with-audio" title="{{ __('Respuesta por voz') }}">
                                        <label class="form-check-label text-muted" for="respond-with-audio" title="{{ __('Respuesta por voz') }}">
                                            <i class="ti ti-speakerphone ti-sm"></i>
                                        </label>
                                    </div>
                                </div>
                                @endif
                                <button type="button" class="btn btn-icon flex-shrink-0 me-2" id="chat-mic-btn" title="{{ __('Grabar mensaje de voz') }}" aria-label="{{ __('Grabar mensaje de voz') }}">
                                    <i class="ti ti-microphone ti-sm" id="chat-mic-icon"></i>
                                </button>
                                <button type="button" class="btn btn-icon flex-shrink-0 me-2" id="chat-attach-btn" title="{{ __('Adjuntar archivo') }}" aria-label="{{ __('Adjuntar archivo') }}" onclick="document.getElementById('chat-attachments').click();">
                                    <i class="ti ti-paperclip ti-sm"></i>
                                </button>
                                <input type="file" id="chat-attachments" class="d-none" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.csv,.txt,.doc,.docx,.xls,.xlsx" multiple>
                                <div id="chat-record-status" class="d-none align-items-center me-2 small text-danger flex-shrink-0">
                                    <span class="recording-dot me-1"></span>
                                    <span id="chat-record-status-text">{{ __('Grabando...') }}</span>
                                </div>
                                <div id="chat-recorded-ready" class="d-none align-items-center me-2 small text-success flex-shrink-0">
                                    <span id="chat-recorded-duration"></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger" id="chat-record-cancel">{{ __('Cancelar') }}</button>
                                </div>
                                <div id="chat-attachment-count" class="small text-muted me-2"></div>
                                <textarea class="form-control message-input border-0 me-3 shadow-none"
                                    placeholder="{{ __('Type your message here...') }}" style="resize: none;"></textarea>
                            </div>

                            <div class="message-actions d-flex align-items-center">
                                <div class="btn-group">
                                    <button type="submit" name="send_intent" value="send" class="btn btn-primary chat-send-primary-btn waves-effect waves-light">
                                        <i class="ti ti-send me-md-1"></i>
                                        <span class="align-middle d-md-inline-block">{{ __('Enviar') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split waves-effect waves-light" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">{{ __('app.message_save_options_dropdown') }}</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if (!($viewAssistant ?? false) && ($selectedPhone ?? null))
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#chatScheduleModal">
                                                    <i class="ti ti-calendar-time me-1"></i>{{ __('Programar') }}
                                                </button>
                                            </li>
                                        @endif
                                        <li>
                                            <button type="submit" name="send_intent" value="suggest" class="dropdown-item">
                                                <i class="ti ti-sparkles me-1"></i>{{ __('Sugerir') }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- /Chat History -->

            <!-- Sidebar Right -->
            <div class="col app-chat-sidebar-right app-sidebar overflow-hidden" id="app-chat-sidebar-right">
                <div
                    class="sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-4 pt-5">
                    <div class="avatar avatar-xl avatar-online">
                        <img src="{{ asset('assets/img/avatars/2.png') }}" alt="Avatar" class="rounded-circle">
                    </div>
                    <h6 class="mt-2 mb-0">Felecia Rower</h6>
                    <span>NextJS Developer</span>
                    <i class="ti ti-x ti-sm cursor-pointer close-sidebar d-block" data-bs-toggle="sidebar" data-overlay
                        data-target="#app-chat-sidebar-right"></i>
                </div>
                <div class="sidebar-body px-4 pb-4">
                    <div class="my-4">
                        <small class="text-muted text-uppercase">About</small>
                        <p class="mb-0 mt-3">A Next. js developer is a software developer who uses the Next. js framework
                            alongside ReactJS to build web applications.</p>
                    </div>
                    <div class="my-4">
                        <small class="text-muted text-uppercase">Personal Information</small>
                        <ul class="list-unstyled d-grid gap-2 mt-3">
                            <li class="d-flex align-items-center">
                                <i class='ti ti-mail ti-sm'></i>
                                <span class="align-middle ms-2">josephGreen@email.com</span>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class='ti ti-phone-call ti-sm'></i>
                                <span class="align-middle ms-2">+1(123) 456 - 7890</span>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class='ti ti-clock ti-sm'></i>
                                <span class="align-middle ms-2">Mon - Fri 10AM - 8PM</span>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <small class="text-muted text-uppercase">Options</small>
                        <ul class="list-unstyled d-grid gap-2 mt-3">
                            <li class="cursor-pointer d-flex align-items-center">
                                <i class='ti ti-badge ti-sm'></i>
                                <span class="align-middle ms-2">Add Tag</span>
                            </li>
                            <li class="cursor-pointer d-flex align-items-center">
                                <i class='ti ti-star ti-sm'></i>
                                <span class="align-middle ms-2">Important Contact</span>
                            </li>
                            <li class="cursor-pointer d-flex align-items-center">
                                <i class='ti ti-photo ti-sm'></i>
                                <span class="align-middle ms-2">Shared Media</span>
                            </li>
                            <li class="cursor-pointer d-flex align-items-center">
                                <i class='ti ti-trash ti-sm'></i>
                                <span class="align-middle ms-2">Delete Contact</span>
                            </li>
                            <li class="cursor-pointer d-flex align-items-center">
                                <i class='ti ti-ban ti-sm'></i>
                                <span class="align-middle ms-2">Block Contact</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- /Sidebar Right -->

            <div class="app-overlay"></div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="chatImageModal" tabindex="-1" aria-labelledby="chatImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body text-center p-0">
                    <img id="chatModalImg" src="" alt="media">
                </div>
            </div>
        </div>
    </div>

    <style>
    #chatImageModal .modal-dialog {
        max-width: 100vw;
        margin: 0;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #chatImageModal .modal-content {
        background: transparent;
        border: none;
        box-shadow: none;
        width: 100vw;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #chatModalImg {
        max-width: 98vw;
        max-height: 98vh;
        width: auto;
        height: auto;
        display: block;
        margin: auto;
        border-radius: 8px;
    }

    /* Make links in chat messages more visible */
    .chat-message-text a, .chat-link {
        color: inherit !important;
        font-weight: 600 !important;
        text-decoration: underline !important;
        opacity: 1 !important;
    }

    .chat-message-text a:hover, .chat-link:hover {
        opacity: 0.8 !important;
        text-decoration: underline !important;
    }
    </style>

    <!-- Humano Assistant Preview Modal -->
    <div class="modal fade" id="claudePreviewModal" tabindex="-1" aria-labelledby="claudePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claudePreviewModalLabel">{{ __('Humano Assistant - Vista previa') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-2">
                    <div class="d-flex align-items-center gap-2 mb-3 flex-nowrap">
                        <select class="form-select form-select-sm flex-grow-1" id="chatAssistantFlowRoutingKey">
                            <option value="">{{ __('Automatic (detect from message)') }}</option>
                            @foreach(($assistantFlowPrompts ?? collect()) as $flowPrompt)
                                <option value="{{ $flowPrompt['routing_key'] }}">{{ $flowPrompt['section_label'] }} ({{ $flowPrompt['routing_key'] }})</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary btn-sm flex-shrink-0" id="chatAssistantRegenerateBtn">
                            <i class="ti ti-sparkles me-1"></i>{{ __('Sugerir') }}
                        </button>
                    </div>
                    <div id="aiPreviewLoader" class="text-center py-3 d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2 text-muted small">{{ __('Humano Assistant está pensando...') }}</span>
                    </div>
                    <div id="aiPreviewContent">
                        <div id="aiAssistantPreviewError" class="d-none mb-2"></div>
                        <textarea id="aiResponsePreview" class="form-control" rows="10" spellcheck="true"
                            placeholder="{{ __('Hacé clic en «Sugerir» para que el asistente genere una respuesta, o escribí aquí directamente.') }}"></textarea>
                        <div id="aiResponsePreviewAudio" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer align-items-center gap-2 flex-nowrap">
                    {{-- Hidden input for flatpickr (not displayed, just attached to the picker) --}}
                    <input type="text" id="scheduleMessageDatetime" class="d-none" readonly="readonly">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-nowrap">
                        <button type="button" class="btn btn-outline-secondary" id="scheduleAiResponseBtn" title="{{ __('Programar envío') }}">
                            <i class="ti ti-calendar ti-sm"></i>
                        </button>
                        <span id="scheduleAiResponseLabel" class="text-muted small d-none"></span>
                        <button type="button" class="btn btn-outline-primary d-none" id="confirmScheduleBtn">
                            <i class="ti ti-calendar-check me-1"></i>{{ __('Programar') }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="cancelScheduleBtn">
                            <i class="ti ti-x ti-sm"></i>
                        </button>
                        <button type="button" class="btn btn-primary" id="sendAiResponseBtn">{{ __('Enviar respuesta') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="chatScheduleModal" tabindex="-1" aria-labelledby="chatScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chatScheduleModalLabel">{{ __('app.message_schedule_modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <label for="chat-schedule-at-input" class="form-label">{{ __('app.message_schedule_modal_datetime_label') }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="chat-schedule-at-input"
                        data-min-datetime="{{ $chatScheduleMin }}"
                        autocomplete="off"
                        readonly
                        required
                    >
                    <div class="form-text">{{ __('app.message_schedule_modal_help') }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-danger d-none" id="chat-schedule-delete-btn">{{ __('Eliminar') }}</button>
                    <button type="button" class="btn btn-label-secondary ms-auto" data-bs-dismiss="modal">{{ __('app.message_schedule_modal_cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="chat-schedule-confirm-btn">{{ __('Programar') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection
