@extends('layouts/layoutMaster')

@section('title', 'Chat')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
    <style>
        #chat-qr-container {
            min-width: 200px;
            min-height: 200px;
        }
        #chat-qr-container.chat-qr-loading {
            background-color: #f0f0f0;
            border-radius: 0.375rem;
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
        const previewModal = new bootstrap.Modal(document.getElementById('claudePreviewModal'));
        const sendAiResponseBtn = document.getElementById('sendAiResponseBtn');
        var assistantUrl = '{{ route("chat.assistant") }}';

        function getChatAssistantFlowRoutingKey() {
            var sel = document.getElementById('chatAssistantFlowRoutingKey');
            return sel && sel.value ? String(sel.value).trim() : '';
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
            var keyEl = document.getElementById('chat-conversation-key');
            var key = keyEl ? keyEl.value : '';
            var storageKey = 'chat-ai-toggle-';
            var userDefault = {{ json_encode($userChatAiToggleDefault ?? true) }};
            if (!useAiToggle) return;
            var initializing = true;
            if (key) {
                var stored = localStorage.getItem(storageKey + key);
                if (stored !== null) {
                    useAiToggle.checked = stored === 'on';
                } else {
                    useAiToggle.checked = userDefault;
                }
                setTimeout(function() { initializing = false; }, 0);
                useAiToggle.addEventListener('change', function() {
                    if (initializing) return;
                    var aiOn = useAiToggle.checked;
                    localStorage.setItem(storageKey + key, aiOn ? 'on' : 'off');
                    var token = document.querySelector('meta[name="csrf-token"]');
                    if (token) {
                        var body = { on: aiOn };
                        var prefUserIdEl = document.getElementById('preference-user-id');
                        if (prefUserIdEl && prefUserIdEl.value) body.user_id = parseInt(prefUserIdEl.value, 10);
                        fetch('{{ route("chat.ai-toggle-preference") }}', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.getAttribute('content') },
                            body: JSON.stringify(body)
                        }).catch(function() {});
                    }
                });
            } else {
                useAiToggle.checked = userDefault;
            }
        })();

        (function persistSidebarAssistantAutoRespond() {
            var sidebar = document.getElementById('sidebar-ai-replies-toggle');
            if (!sidebar) return;
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            sidebar.addEventListener('change', function () {
                if (!token) return;
                fetch('{{ route("chat.assistant-auto-respond") }}', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ on: sidebar.checked })
                }).catch(function () {});
            });
        })();

        (function persistNotificationToggle() {
            var notifToggle = document.getElementById('sidebar-notification-toggle');
            if (!notifToggle) return;
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            notifToggle.addEventListener('change', function () {
                if (!token) return;
                fetch('{{ route("chat.notification-preference") }}', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ on: notifToggle.checked })
                }).catch(function () {});
            });
        })();

        let currentUserMessage = '';
        let currentAiResponse = '';
        let currentAiAudioBase64 = '';
        let currentAiAudioMime = '';

        function renderMarkdownForChat(text) {
            if (!text) return '';
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                return marked.parse(String(text), { gfm: true, breaks: true });
            }
            return (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
        }
        function appendAssistantExchangeToChat(userMsg, aiMsg, audioBase64, audioMime) {
            var list = document.getElementById('assistant-messages-list');
            if (!list) return;
            var empty = list.querySelector('.assistant-empty-state');
            if (empty) empty.remove();
            var timeStr = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            var userLi = document.createElement('li');
            userLi.className = 'chat-message chat-message-right';
            userLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text"><p class="mb-0">' + (userMsg || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p></div><div class="text-end text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
            list.appendChild(userLi);
            var audioHtml = (audioBase64 && audioMime) ? '<div class="mt-2"><audio controls class="w-100" style="max-height:40px;"><source src="data:' + audioMime + ';base64,' + audioBase64 + '" type="' + audioMime + '"></audio></div>' : '';
            var aiLi = document.createElement('li');
            aiLi.className = 'chat-message';
            aiLi.innerHTML = '<div class="d-flex overflow-hidden"><div class="chat-message-wrapper flex-grow-1"><div class="chat-message-text assistant-markdown"><div class="mb-0">' + renderMarkdownForChat(aiMsg || '') + '</div>' + audioHtml + '</div><div class="text-muted mt-1"><small>' + timeStr + '</small></div></div></div>';
            list.appendChild(aiLi);
            removeAssistantTypingIndicator();
            var body = document.querySelector('.chat-history-body');
            if (body) body.scrollTop = body.scrollHeight;
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
            if (body) body.scrollTop = body.scrollHeight;
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
            var msg = messageInput && messageInput.value ? messageInput.value.trim() : '';
            if (!msg && !hasAudio) return;
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
                    fetch('{{ route("chat.send") }}', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token } })
                        .then(function(r) { return r.json(); })
                        .then(function() {
                            messageInput.value = '';
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
                return;
            }

            var isAssistantView = isAssistantViewForm;
            currentUserMessage = msg || (hasAudio ? '{{ __("[Mensaje de voz]") }}' : '');
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

            if (hasAudio) {
                var audioBlob = window.getPendingRecordedAudio && window.getPendingRecordedAudio();
                if (!audioBlob) { reenableSend(); return; }
                var formData = new FormData();
                formData.append('_token', token);
                formData.append('message', msg);
                formData.append('audio', audioBlob, 'recording.webm');
                if (respondWithAudio) formData.append('respond_with_audio', '1');
                if (toVal) formData.append('recipient', toVal);
                if (contactId) formData.append('contact_id', contactId);
                var flowKeyAudio = getChatAssistantFlowRoutingKey();
                if (flowKeyAudio) formData.append('flow_routing_key', flowKeyAudio);
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
                                appendAssistantExchangeToChat(currentUserMessage, currentAiResponse, currentAiAudioBase64, currentAiAudioMime);
                                messageInput.value = '';
                                currentUserMessage = '';
                                currentAiResponse = '';
                                currentAiAudioBase64 = '';
                                currentAiAudioMime = '';
                                if (window.refreshAssistantHistory) window.refreshAssistantHistory();
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
                    respond_with_audio: respondWithAudio
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
                            appendAssistantExchangeToChat(currentUserMessage, currentAiResponse, currentAiAudioBase64, currentAiAudioMime);
                            messageInput.value = '';
                            currentUserMessage = '';
                            currentAiResponse = '';
                            currentAiAudioBase64 = '';
                            currentAiAudioMime = '';
                            if (window.refreshAssistantHistory) window.refreshAssistantHistory();
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
                    respond_with_audio: regenAudio
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
                if (!messages || messages.length === 0) {
                    var extra = assistantUserId ? '' : '<p class="text-muted small mt-2 mb-0">Mismo usuario que en la terminal ({{ auth()->user()->email ?? "" }}) para ver la misma conversación.</p>';
                    list.innerHTML = '<li class="text-center p-4 assistant-empty-state">' +
                        '<p class="text-muted mb-0">Aún no hay mensajes. Escribe abajo o usa <code>php artisan chat:simulate</code>.</p>' + extra + '</li>';
                    return;
                }
                var html = messages.map(function(m) {
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
                list.innerHTML = html;
                var body = document.querySelector('.chat-history-body');
                if (body) body.scrollTop = body.scrollHeight;
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
                list.innerHTML = html;
                var scrollEl = document.querySelector('.chat-history-body');
                if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
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
                    listEl.innerHTML = '<li class="chat-contact-list-item chat-list-item-0"><h6 class="text-muted mb-0">' + msg + '</h6></li>';
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
                    var href = chatUrl + (chatUrl.indexOf('?') >= 0 ? '&' : '?') + 'phone=' + encodeURIComponent(c.from);
                    var rightCol = '<div class="d-flex flex-column align-items-end flex-shrink-0 gap-1"><small class="text-muted">' + time + '</small>' + (badge ? badge : '') + '</div>';
                    return '<li class="chat-contact-list-item' + active + '" data-phone="' + escapeHtml(c.from) + '"><a href="' + escapeHtml(href) + '" class="d-flex align-items-center"><div class="flex-shrink-0 avatar">' + avatar + '</div><div class="chat-contact-info flex-grow-1 ms-2 min-w-0"><h6 class="chat-contact-name text-truncate m-0">' + name + '</h6><p class="chat-contact-status text-muted text-truncate mb-0">' + lastMsg + '</p></div>' + rightCol + '</a></li>';
                }).join('');
                listEl.innerHTML = html;
            }
            function fetchChatList() {
                var body = document.getElementById('chat-history-body');
                var isAssistantView = body && body.getAttribute('data-view-assistant') === '1';
                fetch(listUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
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
                .then(function (r) { return r.json(); })
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
                                    setTimeout(setQrSrc, 2500);
                                } else {
                                    if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                    qrImg.onload = null;
                                    qrImg.onerror = null;
                                }
                            };
                            qrImg.onerror = function () {
                                if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                                qrImg.classList.remove('d-none');
                                qrImg.onload = null;
                                qrImg.onerror = null;
                            };
                            qrImg.src = src;
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

        // When disconnected, try to show QR image only (do NOT call refresh — that wipes auth and forces re-scan).
        // If Node restarted, /status will trigger socket restore from auth; polling will then show connected.
        var waConnectionBlock = document.getElementById('chat-sidebar-whatsapp-connection-block');
        if (waConnectionBlock && waConnectionBlock.getAttribute('data-wa-status') !== 'connected') {
            var qrContainer = document.getElementById('chat-qr-container');
            var qrImg = document.getElementById('chat-whatsapp-qr-img');
            if (qrContainer && qrImg && qrImg.dataset.qrBase) {
                var qrRetries = 0;
                var maxRetries = 24;
                function setQrSrc() {
                    var src = qrImg.dataset.qrBase + '?t=' + Date.now();
                    var fallbackEl = document.getElementById('chat-qr-fallback');
                    qrImg.onload = function () {
                        if (qrImg.naturalWidth > 20) {
                            if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                            qrImg.classList.remove('d-none');
                            if (fallbackEl) fallbackEl.classList.add('d-none');
                            qrImg.onload = null;
                            qrImg.onerror = null;
                        } else if (qrRetries < maxRetries) {
                            qrRetries += 1;
                            if (fallbackEl) fallbackEl.classList.remove('d-none');
                            setTimeout(setQrSrc, 2500);
                        } else {
                            if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                            qrImg.classList.add('d-none');
                            if (fallbackEl) fallbackEl.classList.remove('d-none');
                            qrImg.onload = null;
                            qrImg.onerror = null;
                        }
                    };
                    qrImg.onerror = function () {
                        if (qrContainer) qrContainer.classList.remove('chat-qr-loading');
                        qrImg.classList.add('d-none');
                        if (fallbackEl) fallbackEl.classList.remove('d-none');
                        qrImg.onload = null;
                        qrImg.onerror = null;
                    };
                    qrContainer.classList.add('chat-qr-loading');
                    qrImg.classList.add('d-none');
                    qrImg.src = src;
                }
                setQrSrc();
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
                if (waConnectionBlock) { waConnectionBlock.classList.add('d-none'); }
                if (linkExistingBlock) { linkExistingBlock.classList.add('d-none'); }
                if (badgeEl) { badgeEl.textContent = connectedLabel; badgeEl.className = 'badge bg-success mt-1'; }
                if (avatarEl) { avatarEl.classList.remove('avatar-offline'); avatarEl.classList.add('avatar-online'); }
                if (contactsWaAvatar) { contactsWaAvatar.classList.remove('avatar-offline'); contactsWaAvatar.classList.add('avatar-online'); }
            } else {
                if (waConnectionBlock) {
                    waConnectionBlock.classList.remove('d-none');
                    var qrImgReload = document.getElementById('chat-whatsapp-qr-img');
                    if (qrImgReload && qrImgReload.dataset.qrBase) {
                        qrImgReload.src = qrImgReload.dataset.qrBase + '?t=' + Date.now();
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

        var btnGenerateNewQr = document.getElementById('chat-btn-generate-new-qr');
        if (btnGenerateNewQr) {
            btnGenerateNewQr.addEventListener('click', function () {
                var qrImg = document.getElementById('chat-whatsapp-qr-img');
                var qrContainer = document.getElementById('chat-qr-container');
                var fallbackEl = document.getElementById('chat-qr-fallback');
                var token = document.querySelector('meta[name="csrf-token"]');
                var t = token ? token.getAttribute('content') : '';
                if (!t) return;
                btnGenerateNewQr.disabled = true;
                fetch('{{ route("chat.whatsapp-refresh-qr") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': t,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: '_token=' + encodeURIComponent(t)
                })
                .then(function () {
                    if (fallbackEl) fallbackEl.classList.add('d-none');
                    if (qrContainer) qrContainer.classList.add('chat-qr-loading');
                    if (qrImg) qrImg.classList.remove('d-none');
                    setTimeout(function () {
                        if (qrImg && qrImg.dataset.qrBase) {
                            qrImg.src = qrImg.dataset.qrBase + '?t=' + Date.now();
                        }
                        btnGenerateNewQr.disabled = false;
                    }, 4000);
                })
                .catch(function () { btnGenerateNewQr.disabled = false; });
            });
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
                    <div class="my-4">
                        @if(($whatsappDriver ?? 'twilio') === 'local')
                            <div id="chat-sidebar-whatsapp-connection-block" class="{{ ($teamWhatsAppIsConnected ?? false) ? 'd-none' : '' }}" data-wa-status="{{ $whatsappStatus['status'] ?? 'disconnected' }}">
                            <small class="text-muted text-uppercase">{{ __('WhatsApp connection') }}</small>
                            <div class="d-grid gap-2 mt-3">
                                @if(!empty($qrImageUrl))
                                    <div class="d-inline-block text-center" id="chat-qr-container">
                                        <img id="chat-whatsapp-qr-img" src="{{ url($qrImageUrl) }}?t={{ time() }}" alt="WhatsApp QR" class="d-block mx-auto d-none" width="200" height="200" loading="eager" data-qr-base="{{ url($qrImageUrl) }}"
                                            onload="var el=this; var fb=document.getElementById('chat-qr-fallback'); if(el.naturalWidth>20){el.classList.remove('d-none'); if(fb)fb.classList.add('d-none');} else {if(fb)fb.classList.remove('d-none');}"
                                            onerror="this.classList.add('d-none'); document.getElementById('chat-qr-fallback').classList.remove('d-none');">
                                        <div id="chat-qr-fallback" class="mb-2 d-none">
                                            <p class="small text-muted mb-2">{{ __('If this team is already linked, reload the page to refresh. Otherwise wait a few seconds for the QR code to appear.') }}</p>
                                            <button type="button" id="chat-btn-generate-new-qr" class="btn btn-sm btn-outline-warning w-100">
                                                <i class="ti ti-refresh me-1"></i>{{ __('Generate new QR code') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                                <div id="chat-link-existing-number-block" class="d-none mt-2" data-number="">
                                    <p class="small text-muted mb-2">{{ __('A number is connected in the service but not linked to this team.') }}</p>
                                    <button type="button" id="chat-btn-link-existing-number" class="btn btn-sm btn-primary w-100">
                                        <i class="ti ti-link me-1"></i>{{ __('Link to this team') }}
                                    </button>
                                </div>
                            </div>
                            </div>
                        @elseif(($whatsappDriver ?? 'twilio') !== 'local')
                            <small class="text-muted text-uppercase">{{ __('Status') }}</small>
                            <div class="d-grid gap-2 mt-3">
                                <div class="form-check form-check-success">
                                    <input name="chat-user-status" class="form-check-input" type="radio" value="active"
                                        id="user-active" checked>
                                    <label class="form-check-label" for="user-active">{{ __('Active') }}</label>
                                </div>
                                <div class="form-check form-check-danger">
                                    <input name="chat-user-status" class="form-check-input" type="radio" value="busy"
                                        id="user-busy">
                                    <label class="form-check-label" for="user-busy">{{ __('Busy') }}</label>
                                </div>
                                <div class="form-check form-check-warning">
                                    <input name="chat-user-status" class="form-check-input" type="radio" value="away"
                                        id="user-away">
                                    <label class="form-check-label" for="user-away">{{ __('Away') }}</label>
                                </div>
                                <div class="form-check form-check-secondary">
                                    <input name="chat-user-status" class="form-check-input" type="radio" value="offline"
                                        id="user-offline">
                                    <label class="form-check-label" for="user-offline">{{ __('Offline') }}</label>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="my-4">
                        <small class="text-muted text-uppercase">{{ __('Settings') }}</small>
                        <ul class="list-unstyled d-grid gap-2 me-3 mt-3">
                            <li class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class='ti ti-robot me-1 ti-sm'></i>
                                    <span class="align-middle">{{ __('Humano Assistant replies') }}</span>
                                </div>
                                <label class="switch switch-primary me-4 switch-sm">
                                    <input type="checkbox" class="switch-input" id="sidebar-ai-replies-toggle" {{ ($assistantAutoRespond ?? false) ? 'checked' : '' }} />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class='ti ti-bell me-1 ti-sm'></i>
                                    <span class="align-middle">{{ __('Notification by email') }}</span>
                                </div>
                                <label class="switch switch-primary me-4 switch-sm opacity-50">
                                    <input type="checkbox" class="switch-input" id="sidebar-notification-toggle" {{ ($notifyNewContactEmail ?? false) ? 'checked' : '' }} disabled />
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

                    <div class="chat-contact-list-item-title">
                        <h5 class="text-primary mb-0 px-4 pt-3 pb-2">{{ __('Chats') }}</h5>
                    </div>
                    <!-- Chats -->
                    <ul class="list-unstyled chat-contact-list" id="chat-list">
                        @auth
                        <li class="chat-contact-list-item {{ ($viewAssistant ?? false) && !($selectedAssistantUser ?? null) ? 'active' : '' }}">
                            <a href="{{ route('chat.index', ['view' => 'assistant']) }}" class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar">
                                    <span class="avatar-initial rounded-circle bg-label-info"><i class="ti ti-robot ti-sm"></i></span>
                                </div>
                                <div class="chat-contact-info flex-grow-1 ms-2">
                                    <h6 class="chat-contact-name text-truncate m-0">Asistente</h6>
                                    <p class="chat-contact-status text-muted text-truncate mb-0">Mi conversación con el bot</p>
                                </div>
                            </a>
                        </li>
                        @foreach($assistantClients ?? [] as $client)
                            @if($client->id !== auth()->id())
                            <li class="chat-contact-list-item {{ optional($selectedAssistantUser)->id === $client->id ? 'active' : '' }}">
                                <a href="{{ route('chat.index', ['view' => 'assistant', 'user_id' => $client->id]) }}" class="d-flex align-items-center">
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
                        @endauth
                    </ul>
                    <ul class="list-unstyled chat-contact-list mb-0" id="chat-list-whatsapp" data-chat-url="{{ route('chat.index') }}" data-selected-phone="{{ $selectedPhone ?? '' }}" data-team-has-wa-number="{{ !empty($teamWhatsAppNumber) ? '1' : '0' }}">
                        @if ($contacts->isEmpty())
                            <li class="chat-contact-list-item chat-list-item-0">
                                <h6 class="text-muted mb-0">{{ !empty($teamWhatsAppNumber) ? __('No WhatsApp conversations') : __('Link a WhatsApp number in the sidebar to see conversations here.') }}</h6>
                            </li>
                        @else
                            @foreach ($contacts as $contact)
                                <li class="chat-contact-list-item {{ $selectedPhone == $contact->from ? 'active' : '' }}"
                                    data-phone="{{ $contact->from }}">
                                    <a href="{{ route('chat.index', ['phone' => $contact->from]) }}"
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
                                        <p class="text-muted mb-0">Aún no hay mensajes. Escribe abajo o usa <code>php artisan chat:simulate --phone=...</code> para simular a este cliente.</p>
                                        @if(!($selectedAssistantUser ?? null))
                                        <p class="text-muted small mt-2 mb-0">Mismo usuario que en la terminal ({{ auth()->user()->email ?? '' }}) para ver tu conversación.</p>
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
                    </div>
                    <!-- Chat message form -->
                    <div class="chat-history-footer shadow-sm">
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
                            <input type="hidden" id="preference-user-id" value="{{ $preferenceUserId ?? '' }}">
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
                                    <div class="form-check form-switch mb-0">
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
                                <div id="chat-record-status" class="d-none align-items-center me-2 small text-danger flex-shrink-0">
                                    <span class="recording-dot me-1"></span>
                                    <span id="chat-record-status-text">{{ __('Grabando...') }}</span>
                                </div>
                                <div id="chat-recorded-ready" class="d-none align-items-center me-2 small text-success flex-shrink-0">
                                    <span id="chat-recorded-duration"></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger" id="chat-record-cancel">{{ __('Cancelar') }}</button>
                                </div>
                                <textarea class="form-control message-input border-0 me-3 shadow-none"
                                    placeholder="{{ __('Type your message here...') }}" style="resize: none;"></textarea>

                                @if(!($viewAssistant ?? false))
                                <div class="d-flex align-items-center me-3">
                                    <div class="form-check form-switch mb-0">
                                        <input type="checkbox" class="form-check-input" id="use-ai-toggle">
                                        <label class="form-check-label" for="use-ai-toggle">
                                            <i class="ti ti-robot me-1"></i>
                                        </label>
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
