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
                        @if ($selectedPhone)
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
                                {{-- <i class="ti ti-phone-call cursor-pointer d-sm-block d-none me-3"></i>
                                <i class="ti ti-video cursor-pointer d-sm-block d-none me-3"></i>
                                <i class="ti ti-search cursor-pointer d-sm-block d-none me-3"></i> --}}
                                <a href="{{ isset($selectedUser) && $selectedUser->id ? route('contact.show', $selectedUser->id) : '#' }}">
                                    <i class="ti ti-eye"></i>
                                </a>
                                {{-- <div class="dropdown d-flex align-self-center">
                                    <button class="btn p-0" type="button" id="chat-header-actions"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="chat-header-actions">
                                        <a class="dropdown-item" href="javascript:void(0);">View Contact</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Mute Notifications</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Block Contact</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Clear Chat</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Report</a>
                                    </div>
                                </div> --}}
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="chat-history-body bg-body">
                        <ul class="list-unstyled chat-history">
                            @if (!$selectedPhone)
                                <li class="text-center p-4">
                                    <p class="text-muted mb-0">Selecciona una conversación para ver los mensajes</p>
                                </li>
                            @else
                                @foreach ($messages as $message)
                                    @php
                                        $isInbound = $message->direction === 'inbound';
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
                                                    <p class="mb-0">{{ $message->body }}</p>
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
                                                        <img src="{{ asset('assets/img/branding/icon.png') }}"
                                                            alt="Avatar" class="rounded-circle">
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
                            <input type="hidden" id="recipient" value="{{ $selectedPhone }}">
                            <input class="form-control message-input border-0 me-3 shadow-none"
                                placeholder="Escribe tu mensaje aquí..." {{ !$selectedPhone ? 'disabled' : '' }}>
                            <div class="message-actions d-flex align-items-center">
                                <button type="submit" class="btn btn-primary d-flex send-msg-btn"
                                    {{ !$selectedPhone ? 'disabled' : '' }}>
                                    <i class="ti ti-send me-md-1 me-0"></i>
                                    <span class="align-middle d-md-inline-block d-none">Enviar</span>
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
@endsection
