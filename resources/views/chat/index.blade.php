@extends('layouts/layoutMaster')

@section('title', 'Chat')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-chat.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.js') }}"></script>
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

        // Claude AI preview handling
        const formSendMessage = document.getElementById('chat-form');
        const messageInput = document.querySelector('.message-input');
        const useAiToggle = document.getElementById('use-ai-toggle');
        const recipientInput = document.getElementById('recipient');
        const previewModal = new bootstrap.Modal(document.getElementById('claudePreviewModal'));
        const sendAiResponseBtn = document.getElementById('sendAiResponseBtn');

        @if($viewAssistant ?? false)
        if (useAiToggle) { useAiToggle.checked = true; }
        @endif

        let currentUserMessage = '';
        let currentAiResponse = '';

        // Override the default form submission when AI is toggled on
        formSendMessage.addEventListener('submit', function(e) {
            if (useAiToggle && useAiToggle.checked && messageInput.value.trim()) {
                e.preventDefault();
                e.stopPropagation();
                currentUserMessage = messageInput.value.trim();

                // Show the user's message in the preview modal
                document.getElementById('userMessagePreview').textContent = currentUserMessage;

                // Reset and show the preview modal
                document.getElementById('aiPreviewLoader').classList.remove('d-none');
                document.getElementById('aiPreviewContent').classList.add('d-none');
                document.getElementById('aiResponsePreview').textContent = '';
                previewModal.show();

                // Get assistant response with context (agent_conversations)
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const cleanTo = recipientInput.value.replace('whatsapp:', '');
                const contactIdInput = document.getElementById('contact-id');
                const contactId = contactIdInput ? contactIdInput.value : '';

                fetch('{{ route("chat.assistant") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        message: currentUserMessage,
                        recipient: cleanTo || undefined,
                        contact_id: (contactId && parseInt(contactId, 10)) ? parseInt(contactId, 10) : undefined
                    })
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    document.getElementById('aiPreviewContent').classList.remove('d-none');

                    if (data.success) {
                        currentAiResponse = data.response || '';
                        document.getElementById('aiResponsePreview').textContent = currentAiResponse;
                    } else {
                        document.getElementById('aiResponsePreview').innerHTML =
                            '<div class="alert alert-danger">Error: ' + (data.message || 'Failed to get response') + '</div>';
                        currentAiResponse = '';
                    }
                })
                .catch(error => {
                    document.getElementById('aiPreviewLoader').classList.add('d-none');
                    document.getElementById('aiPreviewContent').classList.remove('d-none');
                    document.getElementById('aiResponsePreview').innerHTML =
                        '<div class="alert alert-danger">Error connecting to server: ' + error.message + '</div>';
                    currentAiResponse = '';
                });

                return false;
            }
        });

        // Send the previewed AI response when confirmed
        sendAiResponseBtn.addEventListener('click', function() {
            if (currentUserMessage && currentAiResponse) {
                previewModal.hide();

                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const cleanTo = recipientInput.value.replace('whatsapp:', '').trim();

                // Add the original message to the UI
                let renderMsg = document.createElement('li');
                renderMsg.className = 'chat-message chat-message-right';
                renderMsg.innerHTML = `
                    <div class="d-flex overflow-hidden">
                        <div class="chat-message-wrapper flex-grow-1">
                            <div class="chat-message-text">
                                <p class="mb-0">${currentUserMessage}</p>
                            </div>
                            <div class="text-end text-muted mt-1">
                                <i class='ti ti-check ti-xs me-1 text-success'></i>
                                <small>${new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</small>
                            </div>
                        </div>
                        <div class="user-avatar flex-shrink-0 ms-3">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ substr(auth()->user()->name ?? 'U', 0, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                document.querySelector('.chat-history').appendChild(renderMsg);

                // Send the user message to WhatsApp only when there is a recipient (not in assistant-only view)
                if (cleanTo) {
                    fetch('/chat/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            to: cleanTo,
                            message: currentUserMessage,
                            use_ai: false
                        })
                    }).then(response => response.json())
                      .catch(error => console.error('Error sending user message:', error));
                }

                // Create a new message for AI response (left side, like in assistant view)
                let aiMsg = document.createElement('li');
                aiMsg.className = 'chat-message';
                aiMsg.innerHTML = `
                    <div class="d-flex overflow-hidden">
                        <div class="user-avatar flex-shrink-0 me-3">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-label-info">AI</span>
                            </div>
                        </div>
                        <div class="chat-message-wrapper flex-grow-1">
                            <div class="chat-message-text">
                                <p class="mb-0">${currentAiResponse.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}</p>
                            </div>
                            <div class="text-muted mt-1">
                                <small>${new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</small>
                            </div>
                        </div>
                    </div>
                `;
                document.querySelector('.chat-history').appendChild(aiMsg);

                // Scroll to bottom
                const chatHistory = document.querySelector('.chat-history-body');
                if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;

                // Send the AI message to WhatsApp only when there is a recipient
                if (cleanTo) {
                    fetch('/chat/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                        to: cleanTo,
                        message: currentAiResponse,
                        use_ai: false
                    })
                }).then(response => response.json())
                  .catch(error => console.error('Error sending AI message:', error));
                }

                // Clear the input
                messageInput.value = '';
                currentUserMessage = '';
                currentAiResponse = '';
                if (window.refreshAssistantHistory) window.refreshAssistantHistory();
            }
        });

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
                    var isUser = m.role === 'user';
                    var content = escapeHtml(m.content).replace(/\n/g, '<br>');
                    var time = formatDate(m.created_at);
                    var sideClass = isUser ? 'chat-message-right' : '';
                    var timeClass = isUser ? 'text-end' : '';
                    return '<li class="chat-message ' + sideClass + '">' +
                        '<div class="d-flex overflow-hidden">' +
                        '<div class="chat-message-wrapper flex-grow-1">' +
                        '<div class="chat-message-text"><p class="mb-0">' + content + '</p></div>' +
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
    });
    </script>
@endsection

@section('content')
    <div class="app-chat card overflow-hidden">
        <div class="row g-0">
            <!-- Sidebar Left -->
            <div class="col app-chat-sidebar-left app-sidebar overflow-hidden" id="app-chat-sidebar-left">
                <div
                    class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-4 pt-5">
                    <div class="avatar avatar-xl avatar-online">
                        <img src="{{ asset('assets/img/branding/icon.png') }}" alt="Avatar" class="rounded-circle">
                    </div>
                    <h5 class="mt-2 mb-0">John Doe</h5>
                    <span>Admin</span>
                    <i class="ti ti-x ti-sm cursor-pointer close-sidebar" data-bs-toggle="sidebar" data-overlay
                        data-target="#app-chat-sidebar-left"></i>
                </div>
                <div class="sidebar-body px-4 pb-4">
                    <div class="my-4">
                        <small class="text-muted text-uppercase">About</small>
                        <textarea id="chat-sidebar-left-user-about" class="form-control chat-sidebar-left-user-about mt-3" rows="4"
                            maxlength="120">Dessert chocolate cake lemon drops jujubes. Biscuit cupcake ice cream bear claw brownie brownie marshmallow.</textarea>
                    </div>
                    <div class="my-4">
                        <small class="text-muted text-uppercase">Status</small>
                        <div class="d-grid gap-2 mt-3">
                            <div class="form-check form-check-success">
                                <input name="chat-user-status" class="form-check-input" type="radio" value="active"
                                    id="user-active" checked>
                                <label class="form-check-label" for="user-active">Active</label>
                            </div>
                            <div class="form-check form-check-danger">
                                <input name="chat-user-status" class="form-check-input" type="radio" value="busy"
                                    id="user-busy">
                                <label class="form-check-label" for="user-busy">Busy</label>
                            </div>
                            <div class="form-check form-check-warning">
                                <input name="chat-user-status" class="form-check-input" type="radio" value="away"
                                    id="user-away">
                                <label class="form-check-label" for="user-away">Away</label>
                            </div>
                            <div class="form-check form-check-secondary">
                                <input name="chat-user-status" class="form-check-input" type="radio" value="offline"
                                    id="user-offline">
                                <label class="form-check-label" for="user-offline">Offline</label>
                            </div>
                        </div>
                    </div>
                    <div class="my-4">
                        <small class="text-muted text-uppercase">Settings</small>
                        <ul class="list-unstyled d-grid gap-2 me-3 mt-3">
                            <li class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class='ti ti-message me-1 ti-sm'></i>
                                    <span class="align-middle">Two-step Verification</span>
                                </div>
                                <label class="switch switch-primary me-4 switch-sm">
                                    <input type="checkbox" class="switch-input" checked="" />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class='ti ti-bell me-1 ti-sm'></i>
                                    <span class="align-middle">Notification</span>
                                </div>
                                <label class="switch switch-primary me-4 switch-sm">
                                    <input type="checkbox" class="switch-input" />
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                </label>
                            </li>
                            <li>
                                <i class="ti ti-user-plus me-1 ti-sm"></i>
                                <span class="align-middle">Invite Friends</span>
                            </li>
                            <li>
                                <i class="ti ti-trash me-1 ti-sm"></i>
                                <span class="align-middle">Delete Account</span>
                            </li>
                        </ul>
                    </div>
                    <div class="d-flex mt-4">
                        <button class="btn btn-primary" data-bs-toggle="sidebar" data-overlay
                            data-target="#app-chat-sidebar-left">Logout</button>
                    </div>
                </div>
            </div>
            <!-- /Sidebar Left-->

            <!-- Chat & Contacts -->
            <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
                <div class="sidebar-header">
                    <div class="d-flex align-items-center me-3 me-lg-0">
                        <div class="flex-shrink-0 avatar avatar-online me-3" data-bs-toggle="sidebar"
                            data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
                            <img class="user-avatar rounded-circle cursor-pointer"
                                src="{{ asset('assets/img/branding/icon.png') }}" alt="Avatar">
                        </div>
                        <div class="flex-grow-1 input-group input-group-merge rounded-pill">
                            <span class="input-group-text" id="basic-addon-search31"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control chat-search-input" placeholder="Search..."
                                aria-label="Search..." aria-describedby="basic-addon-search31">
                        </div>
                    </div>
                    <i class="ti ti-x cursor-pointer d-lg-none d-block position-absolute mt-2 me-1 top-0 end-0"
                        data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
                </div>
                <hr class="container-m-nx m-0">
                <div class="sidebar-body">

                    <div class="chat-contact-list-item-title">
                        <h5 class="text-primary mb-0 px-4 pt-3 pb-2">Chats</h5>
                    </div>
                    <!-- Chats -->
                    <ul class="list-unstyled chat-contact-list" id="chat-list">
                        @auth
                        <li class="chat-contact-list-item {{ ($viewAssistant ?? false) && !($selectedAssistantUser ?? null) ? 'active' : '' }}">
                            <a href="{{ route('chat.index', ['view' => 'assistant']) }}" class="d-flex align-items-center">
                                <div class="flex-shrink-0 avatar avatar-online">
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
                                    <div class="flex-shrink-0 avatar avatar-online">
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
                        @if ($contacts->isEmpty())
                            <li class="chat-contact-list-item chat-list-item-0">
                                <h6 class="text-muted mb-0">No hay conversaciones de WhatsApp</h6>
                            </li>
                        @else
                            @foreach ($contacts as $contact)
                                <li class="chat-contact-list-item {{ $selectedPhone == $contact->from ? 'active' : '' }}"
                                    data-phone="{{ $contact->from }}">
                                    <a href="{{ route('chat.index', ['phone' => $contact->from]) }}"
                                        class="d-flex align-items-center">
                                        <div class="flex-shrink-0 avatar avatar-online">
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
                                        <div class="chat-contact-info flex-grow-1 ms-2">
                                            <h6 class="chat-contact-name text-truncate m-0">
                                                {{ $contact->user_name ?? $contact->from }}
                                            </h6>
                                            <p class="chat-contact-status text-muted text-truncate mb-0">
                                                {{ Str::limit($contact->last_message, 30) }}
                                            </p>
                                        </div>
                                        <small class="text-muted mb-auto">{{ $contact->last_message_time }}</small>
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
                                            {{ $selectedAssistantUser->phone ?? $selectedAssistantUser->email }} — Responde tú o activa la IA
                                        @else
                                            Misma conversación que en <code>php artisan chat:simulate</code>
                                        @endif
                                    </small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="assistant-refresh-btn" title="Actualizar mensajes del terminal">
                                <i class="ti ti-refresh me-1"></i>Recargar
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
                        @endif
                    </div>
                    <div class="chat-history-body bg-body">
                        <ul class="list-unstyled chat-history" id="assistant-messages-list">
                            @if ($viewAssistant ?? false)
                                @forelse(($assistantMessages ?? []) as $msg)
                                    <li class="chat-message {{ $msg['role'] === 'user' ? 'chat-message-right' : '' }}">
                                        <div class="d-flex overflow-hidden">
                                            <div class="chat-message-wrapper flex-grow-1">
                                                <div class="chat-message-text">
                                                    <p class="mb-0">{!! nl2br(e($msg['content'])) !!}</p>
                                                </div>
                                                <div class="text-muted mt-1 {{ $msg['role'] === 'user' ? 'text-end' : '' }}">
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
                                                                @if(Str::startsWith($item['content_type'], 'image/'))
                                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#chatImageModal" data-img="{{ $item['url'] }}">
                                                                        <img src="{{ $item['url'] }}" alt="media" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-bottom: 4px;">
                                                                    </a>
                                                                @else
                                                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                                                                        {{ basename($item['url']) }}
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
                        <form id="chat-form" class="form-send-message d-flex justify-content-between align-items-center">
                            @csrf
                            <input type="hidden" id="recipient" value="{{ $selectedAssistantUser ? ($clientRecipientPhone ?? '') : ($selectedPhone ?? '') }}">
                            @if(isset($selectedContact) && $selectedContact)
                                <input type="hidden" id="contact-id" value="{{ $selectedContact->id }}">
                            @elseif($selectedAssistantUser ?? null)
                                <input type="hidden" id="contact-id" value="{{ $assistantContactId ?? '' }}">
                            @else
                                <input type="hidden" id="contact-id" value="">
                            @endif

                            <div class="d-flex align-items-center w-100">
                                <textarea class="form-control message-input border-0 me-3 shadow-none"
                                    placeholder="Type your message here..." style="resize: none;"></textarea>

                                <div class="d-flex align-items-center me-3">
                                    <div class="form-check form-switch mb-0">
                                        <input type="checkbox" class="form-check-input" id="use-ai-toggle">
                                        <label class="form-check-label" for="use-ai-toggle">
                                            <i class="ti ti-robot me-1"></i>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="message-actions d-flex align-items-center">
                                {{-- <i class="speech-to-text ti ti-microphone ti-sm cursor-pointer"></i>
                                <label for="attach-doc" class="form-label mb-0">
                                    <i class="ti ti-photo ti-sm cursor-pointer mx-3"></i>
                                    <input type="file" id="attach-doc" hidden>
                                </label> --}}
                                <button type="submit" class="btn btn-primary d-flex send-msg-btn">
                                    <i class="ti ti-send me-md-1 me-0"></i>
                                    <span class="align-middle d-md-inline-block d-none">Send</span>
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

    <!-- Claude AI Preview Modal -->
    <div class="modal fade" id="claudePreviewModal" tabindex="-1" aria-labelledby="claudePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claudePreviewModalLabel">Claude's Response Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiPreviewLoader" class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Claude is thinking...</p>
                    </div>
                    <div id="aiPreviewContent" class="d-none">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-2">Your message:</h6>
                                <p id="userMessagePreview" class="mb-3"></p>
                                <hr>
                                <h6 class="mb-2">Claude's response:</h6>
                                <p id="aiResponsePreview"></p>
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
