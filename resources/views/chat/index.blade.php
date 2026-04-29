@extends('layouts/layoutMaster')

@section('title', 'Chat')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
    <style>
        #chat-qr-container,
        #chat-history-qr-container {
            position: relative;
            min-width: 200px;
            min-height: 200px;
        }
        #chat-qr-container.chat-qr-loading,
        #chat-history-qr-container.chat-qr-loading {
            background-color: transparent;
            border-radius: 0;
        }
        #chat-qr-container.chat-qr-loading .chat-qr-fallback-frame,
        #chat-history-qr-container.chat-qr-loading .chat-qr-fallback-frame {
            opacity: 0.65;
        }
        .chat-qr-fallback-frame {
            width: 200px;
            height: 200px;
            background-color: var(--bs-gray-75, #eceef2);
            box-shadow: none;
        }
        .chat-qr-fallback-pattern {
            position: absolute;
            inset: -10px;
            z-index: 0;
            pointer-events: none;
            background-color: #dfe3ea;
            background-image:
                linear-gradient(90deg, rgba(67, 89, 113, 0.22) 50%, transparent 50%),
                linear-gradient(rgba(67, 89, 113, 0.22) 50%, transparent 50%);
            background-size: 7px 7px;
            filter: blur(3px);
            opacity: 0.55;
        }
        .chat-qr-fallback-vignette {
            z-index: 1;
            background: radial-gradient(
                ellipse 70% 70% at 50% 50%,
                rgba(255, 255, 255, 0.88) 0%,
                rgba(255, 255, 255, 0.35) 55%,
                rgba(255, 255, 255, 0.12) 100%
            );
            pointer-events: none;
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
    </style>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/app-chat.js') }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var chatImageModal = document.getElementById('chatImageModal');
        chatImageModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var imgUrl = trigger.getAttribute('data-img');
            var modalImg = document.getElementById('chatModalImg');
            modalImg.src = imgUrl;
        });

        // Humano Assistant preview handling
        const formSendMessage = document.getElementById('chat-form');
        const messageInput = document.querySelector('.message-input');
        const useAiToggle = document.getElementById('use-ai-toggle');
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

        (function persistAiTogglePreference() {
            var toggleDefault = {{ json_encode($contactChatAiToggleDefault ?? $userChatAiToggleDefault ?? true) }};
            if (!useAiToggle) return;
            useAiToggle.checked = toggleDefault;
            useAiToggle.addEventListener('change', function() {
                var aiOn = useAiToggle.checked;
                var token = document.querySelector('meta[name="csrf-token"]');
                var cidEl = document.getElementById('contact-id');
                var contactId = cidEl && cidEl.value ? parseInt(cidEl.value, 10) : 0;
                if (token) {
                    var body = { on: aiOn };
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
        })();

        let currentUserMessage = '';
        let currentAiResponse = '';
        let currentAiAudioBase64 = '';
        let currentAiAudioMime = '';
        let currentAttachmentPreviews = [];
        let localDocumentEvents = [];

        function renderMarkdownForChat(text) {
            if (!text) return '';
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                return marked.parse(String(text), { gfm: true, breaks: true });
            }
            return (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
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
            userLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + userTextHtml + '</p>' + attachmentHtml + '</div><div class="text-end text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
            list.appendChild(userLi);
            var audioHtml = (audioBase64 && audioMime) ? '<div class="mt-2"><audio controls class="w-100" style="max-height:40px;"><source src="data:' + audioMime + ';base64,' + audioBase64 + '" type="' + audioMime + '"></audio></div>' : '';
            var aiLi = document.createElement('li');
            aiLi.className = 'chat-message';
            aiLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text assistant-markdown"><div class="mb-0">' + renderMarkdownForChat(aiMsg || '') + '</div>' + audioHtml + '</div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
            list.appendChild(aiLi);
            removeAssistantTypingIndicator();
            var body = document.querySelector('.chat-history-body');
            chatHistoryScrollToBottomIfPinned(body);
        }
        function syncSidebarAssistantAutoRespondFromResponse(data) {
            if (!data || typeof data.assistant_auto_respond !== 'boolean') return;
            var sidebar = document.getElementById('sidebar-ai-replies-toggle');
            if (sidebar) sidebar.checked = data.assistant_auto_respond;
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
            var sendBtn = form.querySelector('.send-msg-btn');
            if (sendBtn) sendBtn.disabled = true;
            function reenableSend() { if (sendBtn) sendBtn.disabled = false; }
            var isAssistantViewForm = form.getAttribute('data-view-assistant') === '1';
            var useAiToggleEl = document.getElementById('use-ai-toggle');
            var aiOn = isAssistantViewForm ? true : (useAiToggleEl ? useAiToggleEl.checked : false);
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            var toVal = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';
            var cidEl = document.getElementById('contact-id');
            var contactId = (cidEl && cidEl.value && parseInt(cidEl.value, 10)) ? parseInt(cidEl.value, 10) : undefined;

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
                        .then(function(r) { return r.json(); })
                        .then(function() {
                            messageInput.value = '';
                            if (attachmentInput) attachmentInput.value = '';
                            updateAttachmentCount();
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        }).catch(function() {}).finally(reenableSend);
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
                        }).then(function(r) { return r.json(); }).then(function() {
                            messageInput.value = '';
                            if (attachmentInput) attachmentInput.value = '';
                            updateAttachmentCount();
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        }).catch(function() {}).finally(reenableSend);
                    } else {
                        var body = { to: toVal, message: msg, use_ai: false };
                        if (contactId) body.contact_id = contactId;
                        fetch('{{ route("chat.send") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify(body)
                        }).then(function(r) { return r.json(); }).then(function() {
                            messageInput.value = '';
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
                        }).catch(function() {}).finally(reenableSend);
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

            document.getElementById('userMessagePreview').textContent = currentUserMessage;
            document.getElementById('aiPreviewLoader').classList.remove('d-none');
            document.getElementById('aiPreviewContent').classList.remove('d-none');
            var taPreviewStart = document.getElementById('aiResponsePreview');
            if (taPreviewStart) {
                taPreviewStart.value = '';
                taPreviewStart.disabled = true;
            }
            var errBoxStart = document.getElementById('aiAssistantPreviewError');
            if (errBoxStart) {
                errBoxStart.classList.add('d-none');
                errBoxStart.innerHTML = '';
            }
            var previewAudioEl = document.getElementById('aiResponsePreviewAudio');
            if (previewAudioEl) previewAudioEl.innerHTML = '';
            if (!isAssistantView) previewModal.show();
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

        // Refresh WhatsApp QR image periodically so it appears when the Node service has it
        (function() {
            var qrImg = document.getElementById('chat-whatsapp-qr-img');
            if (qrImg && qrImg.dataset.qrBase) {
                setInterval(function() {
                    qrImg.src = qrImg.dataset.qrBase + '?t=' + Date.now();
                }, 4000);
            }
        })();

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
                    userLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + (currentUserMessage || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p></div><div class="text-end text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
                    list.appendChild(userLi);
                    var aiLi = document.createElement('li');
                    aiLi.className = 'chat-message';
                    var audioHtml = (currentAiAudioBase64 && currentAiAudioMime) ? '<div class="mt-2"><audio controls class="w-100" style="max-height:40px;"><source src="data:' + currentAiAudioMime + ';base64,' + currentAiAudioBase64 + '" type="' + currentAiAudioMime + '"></audio></div>' : '';
                    var aiContent = typeof renderMarkdownForChat === 'function' ? renderMarkdownForChat(currentAiResponse) : currentAiResponse.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                    aiLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text assistant-markdown"><div class="mb-0">' + aiContent + '</div>' + audioHtml + '</div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
                    list.appendChild(aiLi);
                    var body = document.querySelector('.chat-history-body');
                    if (body) body.scrollTop = body.scrollHeight;
                } else if (list) {
                    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const cleanTo = recipientInput ? recipientInput.value.replace('whatsapp:', '').trim() : '';

                    var aiMsg = document.createElement('li');
                    aiMsg.className = 'chat-message';
                    aiMsg.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + currentAiResponse.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p></div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div><div class="user-avatar flex-shrink-0 ms-3"><div class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-info">AI</span></div></div></div>';
                    list.appendChild(aiMsg);

                    var chatHistory = document.querySelector('.chat-history-body');
                    if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;

                    if (cleanTo) {
                        fetch('{{ route("chat.send") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ to: cleanTo, message: currentAiResponse, use_ai: false })
                        }).then(function(r) { return r.json(); }).catch(function(err) { console.error('Error sending AI message:', err); });
                    }
                }

                messageInput.value = '';
                currentUserMessage = '';
                currentAiResponse = '';
                if (window.refreshAssistantHistory) window.refreshAssistantHistory();
            }
        });

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
                var taR = document.getElementById('aiResponsePreview');
                if (taR) taR.disabled = true;
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
                    if (regenIsAssistant) return;
                    var sb = document.querySelector('#chat-form .send-msg-btn');
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
            var refreshBtn = document.getElementById('assistant-refresh-btn');
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
                    var extra = assistantUserId ? '' : '<p class="text-muted small mt-2 mb-0">Mismo usuario que en la terminal ({{ auth()->user()->email ?? "" }}) para ver la misma conversación.</p>';
                    list.innerHTML = '<li class="text-center p-4 assistant-empty-state">' +
                        '<div class="text-start">' + inner + '</div>' + extra + '</li>';
                    return;
                }
                var html = mergedMessages.map(function(m) {
                    var isAssistant = m.role === 'assistant';
                    var content = isAssistant && typeof renderMarkdownForChat === 'function'
                        ? renderMarkdownForChat(m.content || '')
                        : escapeHtml(m.content || '').replace(/\n/g, '<br>');
                    var time = formatDate(m.created_at);
                    var sideClass = isAssistant ? '' : 'chat-message-right';
                    var timeClass = isAssistant ? '' : 'text-end';
                    var contentWrap = isAssistant ? '<div class="assistant-markdown mb-0">' + content + '</div>' : '<p class="mb-0">' + content + '</p>';
                    return '<li class="chat-message ' + sideClass + '">' +
                        '<div class="d-flex overflow-hidden">' +
                        '<div class="chat-message-wrapper flex-grow-1">' +
                        '<div class="chat-message-text">' + contentWrap + '</div>' +
                        '<div class="text-muted mt-1 ' + timeClass + '"><small>' + time + '</small></div>' +
                        '</div></div></li>';
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
                    .then(function(data) { renderMessages(data.messages || []); })
                    .catch(function() {});
            }
            setInterval(fetchHistory, 5000);
            if (refreshBtn) refreshBtn.addEventListener('click', fetchHistory);
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
            function formatTime(createdAt) {
                if (!createdAt) return '';
                var d = new Date(createdAt);
                return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            }
            function statusIcon(status) {
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
                    var bodyEscaped = escapeHtml(m.body || '').replace(/\n/g, '<br>');
                    var time = formatTime(m.created_at);
                    var fromSuffix = (m.from || '').toString().slice(-2);
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
                        return '<li class="chat-message ' + sideClass + '"><div class="d-flex overflow-hidden"><div class="user-avatar flex-shrink-0 me-3"><div class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-success">' + escapeHtml(fromSuffix) + '</span></div></div><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + bodyEscaped + '</p>' + (mediaHtml ? '<div class="chat-media mt-2">' + mediaHtml + '</div>' : '') + '</div><div class="' + timeClass + ' text-muted mt-1"><small>' + time + '</small></div></div></div></li>';
                    }
                    return '<li class="chat-message ' + sideClass + '"><div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + bodyEscaped + '</p>' + (mediaHtml ? '<div class="chat-media mt-2">' + mediaHtml + '</div>' : '') + '</div><div class="' + timeClass + ' text-muted mt-1">' + statusIcon(m.status) + '<small>' + time + '</small></div></div><div class="user-avatar flex-shrink-0 ms-3"><div class="avatar avatar-sm"><span class="avatar-initial rounded-circle bg-label-primary">' + escapeHtml(fromSuffix) + '</span></div></div></div></li>';
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
                            var src = qrImg.dataset.qrBase + '?t=' + Date.now();
                            qrImg.onload = function () {
                                if (qrImg.naturalWidth > 20) {
                                    if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                    qrImg.classList.remove('d-none');
                                    if (document.getElementById('chat-qr-fallback')) document.getElementById('chat-qr-fallback').classList.add('d-none');
                                    qrImg.onload = null;
                                    qrImg.onerror = null;
                                } else if (qrRetries < maxRetries) {
                                    qrRetries += 1;
                                    var fbRetry = document.getElementById('chat-qr-fallback');
                                    if (fbRetry) fbRetry.classList.remove('d-none');
                                    if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                    setTimeout(setQrSrc, 2500);
                                } else {
                                    if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                    qrImg.classList.add('d-none');
                                    var fbEnd = document.getElementById('chat-qr-fallback');
                                    if (fbEnd) fbEnd.classList.remove('d-none');
                                    qrImg.onload = null;
                                    qrImg.onerror = null;
                                }
                            };
                            qrImg.onerror = function () {
                                if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                qrImg.classList.add('d-none');
                                var fbErr = document.getElementById('chat-qr-fallback');
                                if (fbErr) fbErr.classList.remove('d-none');
                                qrImg.onload = null;
                                qrImg.onerror = null;
                            };
                            qrImg.removeAttribute('src');
                            setTimeout(function () { qrImg.src = src; }, 0);
                        }
                        setTimeout(setQrSrc, 4000);
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

        function runWhatsappQrServerRefreshAndPoll() {
            if (waQrRefreshInFlight) {
                return;
            }
            var scopes = collectWaQrScopes();
            if (scopes.length === 0) {
                return;
            }
            waQrRefreshInFlight = true;
            var token = document.querySelector('meta[name="csrf-token"]');
            var t = token ? token.getAttribute('content') : '';

            function releaseRefresh() {
                waQrRefreshInFlight = false;
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

            fetch('{{ route("chat.whatsapp-refresh-qr") }}', {
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
                    if (!r.ok || data.ok === false) {
                        var failMsg = (data && data.message) ? data.message : '{{ __("Could not refresh the QR code.") }}';
                        scopes.forEach(function (s) {
                            if (s.err) {
                                s.err.textContent = failMsg;
                                s.err.classList.remove('d-none');
                            }
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.fallback) {
                                s.fallback.classList.remove('d-none');
                            }
                        });
                        releaseRefresh();

                        return;
                    }
                    var probeImg = scopes[0].img;
                    if (!probeImg || !probeImg.dataset.qrBase) {
                        scopes.forEach(function (s) {
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.fallback) {
                                s.fallback.classList.remove('d-none');
                            }
                        });
                        releaseRefresh();

                        return;
                    }
                    var qrRetries = 0;
                    var maxRetries = 40;
                    var loadErrMsg = '{{ __("The QR code did not load. Ensure the WhatsApp service is running and reachable from this server.") }}';

                    function finishFailure() {
                        scopes.forEach(function (s) {
                            if (s.container) {
                                s.container.classList.remove('chat-qr-loading');
                            }
                            if (s.img) {
                                s.img.classList.add('d-none');
                            }
                            if (s.fallback) {
                                s.fallback.classList.remove('d-none');
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

                    function setQrSrcAfterRefresh() {
                        var src = probeImg.dataset.qrBase + '?t=' + Date.now();
                        probeImg.onload = function () {
                            if (probeImg.naturalWidth > 20) {
                                applyQrSuccessAll(probeImg.src);
                            } else if (qrRetries < maxRetries) {
                                qrRetries += 1;
                                setTimeout(setQrSrcAfterRefresh, 2500);
                            } else {
                                finishFailure();
                            }
                        };
                        probeImg.onerror = function () {
                            if (qrRetries < maxRetries) {
                                qrRetries += 1;
                                setTimeout(setQrSrcAfterRefresh, 2500);
                            } else {
                                finishFailure();
                            }
                        };
                        probeImg.removeAttribute('src');
                        setTimeout(function () {
                            probeImg.src = src;
                        }, 0);
                    }
                    setTimeout(setQrSrcAfterRefresh, 3500);
                })
                .catch(function () {
                    var netMsg = '{{ __("Could not refresh the QR code.") }}';
                    scopes.forEach(function (s) {
                        if (s.container) {
                            s.container.classList.remove('chat-qr-loading');
                        }
                        if (s.fallback) {
                            s.fallback.classList.remove('d-none');
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
                runWhatsappQrServerRefreshAndPoll();
            }
        }

        // "Link current number to this team" – request QR URL so Node receives token and callbacks if already connected
        var linkCurrentNumberBtn = document.getElementById('chat-link-current-number-btn');
        if (linkCurrentNumberBtn && linkCurrentNumberBtn.dataset.qrUrl) {
            linkCurrentNumberBtn.addEventListener('click', function () {
                var url = linkCurrentNumberBtn.dataset.qrUrl;
                if (!url) return;
                url = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'link_current=1';
                linkCurrentNumberBtn.disabled = true;
                fetch(url, { credentials: 'same-origin' }).then(function () {
                    linkCurrentNumberBtn.disabled = false;
                    var statusUrl = '{{ route("chat.whatsapp-status") }}';
                    fetch(statusUrl, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.isTeamConnected && data.teamNumberFormatted) {
                            if (window.location && window.location.reload) window.location.reload();
                        }
                    }).catch(function () { linkCurrentNumberBtn.disabled = false; });
                }).catch(function () { linkCurrentNumberBtn.disabled = false; });
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
            var avatarEl = document.getElementById('chat-sidebar-wa-avatar');
            var contactsWaAvatar = document.getElementById('chat-contacts-wa-avatar');
            var linkExistingBlock = document.getElementById('chat-link-existing-number-block');
            var displayNumber = data.teamNumberFormatted || null;
            if (titleEl) titleEl.textContent = displayNumber || '{{ __("Not linked") }}';
            if (data.isTeamConnected) {
                waTeamWasConnected = true;
                if (waConnectionBlock) { waConnectionBlock.classList.add('d-none'); }
                var historyWaPanel = document.getElementById('chat-history-wa-connect-panel');
                if (historyWaPanel) {
                    historyWaPanel.classList.add('d-none');
                }
                if (linkExistingBlock) { linkExistingBlock.classList.add('d-none'); }
                if (badgeEl) { badgeEl.textContent = connectedLabel; badgeEl.className = 'badge bg-success mt-1'; }
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
                if (linkExistingBlock && data.status === 'connected' && data.number) {
                    linkExistingBlock.dataset.number = data.number;
                    linkExistingBlock.classList.remove('d-none');
                } else if (linkExistingBlock) {
                    linkExistingBlock.classList.add('d-none');
                }
                if (badgeEl) {
                    var status = data.status || 'disconnected';
                    badgeEl.textContent = status === 'waiting_qr' ? scanQrLabel : disconnectedLabel;
                    badgeEl.className = status === 'waiting_qr' ? 'badge bg-warning mt-1' : 'badge bg-secondary mt-1';
                }
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
                        if (data.isTeamConnected) clearInterval(waPoll);
                    })
                    .catch(function () {});
            }, 3000);
        }

        var btnLinkExisting = document.getElementById('chat-btn-link-existing-number');
        var linkExistingBlock = document.getElementById('chat-link-existing-number-block');
        if (btnLinkExisting && linkExistingBlock) {
            btnLinkExisting.addEventListener('click', function () {
                var number = linkExistingBlock.dataset.number;
                if (!number) return;
                var token = document.querySelector('meta[name="csrf-token"]');
                var t = token ? token.getAttribute('content') : '';
                btnLinkExisting.disabled = true;
                fetch('{{ route("chat.link-current-number") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': t,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ number: number })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) { if (window.location) window.location.reload(); }
                    else { btnLinkExisting.disabled = false; }
                })
                .catch(function () { btnLinkExisting.disabled = false; });
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
                        <span id="chat-sidebar-wa-badge" class="badge bg-{{ $badgeClass }} mt-1">{{ $statusLabel }}</span>
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
                                        <img id="chat-whatsapp-qr-img" src="{{ url($qrImageUrl) }}?t={{ time() }}" alt="WhatsApp QR" class="d-block mx-auto d-none" width="200" height="200" loading="eager" data-qr-base="{{ url($qrImageUrl) }}">
                                        <div id="chat-qr-fallback" class="mb-2 d-none">
                                            <div class="chat-qr-fallback-frame position-relative mx-auto rounded overflow-hidden">
                                                <div class="chat-qr-fallback-pattern" aria-hidden="true"></div>
                                                <div class="chat-qr-fallback-vignette position-absolute top-0 start-0 w-100 h-100"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p id="chat-qr-service-error" class="small text-danger mb-0 mt-2 text-center d-none" role="alert"></p>
                                @endif
                                <div id="chat-link-existing-number-block" class="d-none mt-2" data-number="">
                                    <p class="small text-muted mb-2">{{ __('A number is connected in the service but not linked to this team.') }}</p>
                                    <button type="button" id="chat-btn-link-existing-number" class="btn btn-sm btn-primary w-100">
                                        <i class="ti ti-link me-1"></i>{{ __('Link to this team') }}
                                    </button>
                                </div>
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
                        <div id="chat-contacts-wa-avatar" class="flex-shrink-0 avatar {{ $avatarStatusClass }} me-3 cursor-pointer" data-bs-toggle="sidebar"
                            data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
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
                                <option value="none" @selected(request('crm_status') === 'none')>{{ __('Sin ficha CRM') }}</option>
                                @foreach(($contactStatuses ?? []) as $st)
                                    <option value="{{ $st->id }}" @selected(request('crm_status') == (string) $st->id)>{{ $st->name }}</option>
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
                            <button type="button" class="btn btn-sm btn-outline-primary btn-icon" id="assistant-refresh-btn" title="Actualizar mensajes del terminal" aria-label="Recargar">
                                <i class="ti ti-refresh"></i>
                            </button>
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
                                @include('chat.partials.assistant-empty-suggestions-inner', ['selectedPhone' => $selectedPhone ?? null])
                            </div>
                        @endif
                        <ul class="list-unstyled chat-history" id="assistant-messages-list">
                            @if ($viewAssistant ?? false)
                                @forelse(($assistantMessages ?? []) as $msg)
                                    <li class="chat-message {{ $msg['role'] !== 'assistant' ? 'chat-message-right' : '' }}">
                                        <div class="d-flex overflow-hidden">
                                            <div class="chat-message-wrapper flex-grow-1">
                                                <div class="chat-message-text assistant-markdown">
                                                    <div class="mb-0">{!! \Illuminate\Support\Str::markdown($msg['content']) !!}</div>
                                                </div>
                                                <div class="text-muted mt-1 {{ $msg['role'] !== 'assistant' ? 'text-end' : '' }}">
                                                    <small>{{ $msg['created_at']->format('d/m/Y H:i') }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-center p-4 assistant-empty-state">
                                        <div class="text-start">
                                            @include('chat.partials.assistant-empty-suggestions-inner', ['selectedPhone' => $selectedPhone ?? null])
                                        </div>
                                        @if (! ($selectedAssistantUser ?? null))
                                            <p class="text-muted small mt-2 mb-0">Mismo usuario que en la terminal ({{ auth()->user()->email ?? '' }}) para ver la misma conversación.</p>
                                        @endif
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
                                        $media = $message->media ?? [];
                                        if (is_string($media)) {
                                            $media = json_decode($media, true) ?? [];
                                        }
                                    @endphp
                                    <li class="chat-message {{ !$isInbound ? 'chat-message-right' : '' }}">
                                        <div class="d-flex overflow-hidden">
                                            @if ($isInbound)
                                                <div class="user-avatar flex-shrink-0 me-3">
                                                    <div class="avatar avatar-sm">
                                                        @if (isset($selectedUser) && $selectedUser->profile_photo_path)
                                                            <img src="{{ Storage::url($selectedUser->profile_photo_path) }}"
                                                                alt="{{ $selectedUser->name }}" class="rounded-circle">
                                                        @else
                                                            <span class="avatar-initial rounded-circle bg-label-success">
                                                                {{ substr($message->from, -2) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="chat-message-wrapper flex-grow-1">
                                                <div class="chat-message-text">
                                                    <p class="mb-0">{!! nl2br($message->body) !!}</p>
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
                                                    @if (!$isInbound)
                                                        @if($message->hasFailed())
                                                            <i class='ti ti-alert-circle ti-xs me-1 text-danger'></i>
                                                        @elseif($message->isRead())
                                                            <i class='ti ti-checks ti-xs me-1 text-primary'></i>
                                                        @elseif($message->isDelivered())
                                                            <i class='ti ti-checks ti-xs me-1 text-success'></i>
                                                        @elseif($message->status === 'sent')
                                                            <i class='ti ti-check ti-xs me-1 text-success'></i>
                                                        @else
                                                            <i class='ti ti-clock ti-xs me-1'></i>
                                                        @endif
                                                    @endif
                                                    <small>{{ $message->created_at->format('h:i A') }}</small>
                                                </div>
                                            </div>
                                            @if (!$isInbound)
                                                <div class="user-avatar flex-shrink-0 ms-3">
                                                    <div class="avatar avatar-sm">
                                                        @if (isset($message->user_id) && $message->user_id && isset($users[$message->user_id]) && $users[$message->user_id]->profile_photo_path)
                                                            <img src="{{ Storage::url($users[$message->user_id]->profile_photo_path) }}"
                                                                alt="{{ $users[$message->user_id]->name }}" class="rounded-circle">
                                                        @elseif(isset($users[$message->user_id]->name))
                                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                                {{ \Illuminate\Support\Str::of($users[$message->user_id]->name)->explode(' ')->map(fn($w) => $w[0])->join('') }}
                                                            </span>
                                                        @else
                                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                                {{ substr($message->from, -2) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                        {{-- WhatsApp QR panel intentionally hidden in chat history view --}}
                    </div>
                    <!-- Chat message form -->
                    <div class="chat-history-footer">
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

                                @if(!($viewAssistant ?? false) || ($selectedAssistantUser ?? null))
                                <div class="d-flex align-items-center me-3">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <div class="form-check form-switch mb-0">
                                            <input type="checkbox" class="form-check-input" id="use-ai-toggle">
                                            </div>
                                            <i class="ti ti-robot ms-2"></i>
                                        </div>
                                        <small class="text-muted d-block mt-1">{{ __('Asistente') }}</small>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="message-actions d-flex align-items-center">
                                <button type="submit" class="btn btn-primary d-flex send-msg-btn waves-effect waves-light">
                                    <i class="ti ti-send me-md-1 me-0 send-msg-icon"></i>
                                    <span class="align-middle d-md-inline-block d-none send-msg-text">{{ __('Send') }}</span>
                                </button>
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
                <div class="modal-body">
                    <div id="aiPreviewLoader" class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">{{ __('Humano Assistant está pensando...') }}</p>
                    </div>
                    <div id="aiPreviewContent" class="d-none">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-2">{{ __('Your message') }}:</h6>
                                <p id="userMessagePreview" class="mb-3"></p>
                                <div class="mb-3">
                                    <label class="form-label" for="chatAssistantFlowRoutingKey">{{ __('Assistant flow prompt') }}</label>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <select class="form-select flex-grow-1" id="chatAssistantFlowRoutingKey" style="min-width: 220px;">
                                            <option value="">{{ __('Automatic (detect from message)') }}</option>
                                            @foreach(($assistantFlowPrompts ?? collect()) as $flowPrompt)
                                                <option value="{{ $flowPrompt['routing_key'] }}">{{ $flowPrompt['section_label'] }} ({{ $flowPrompt['routing_key'] }})</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="chatAssistantRegenerateBtn">{{ __('Regenerate with selected prompt') }}</button>
                                    </div>
                                </div>
                                <hr>
                                <h6 class="mb-2">{{ __('Humano Assistant reply') }}</h6>
                                <div id="aiAssistantPreviewError" class="d-none mb-2"></div>
                                <textarea id="aiResponsePreview" class="form-control" rows="8" spellcheck="true" disabled></textarea>
                                <div id="aiResponsePreviewAudio" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendAiResponseBtn">Send Response</button>
                </div>
            </div>
        </div>
    </div>
@endsection
