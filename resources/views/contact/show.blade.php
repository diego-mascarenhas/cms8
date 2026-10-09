@extends('layouts/layoutMaster')

@section('title', __('app.contacts'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />

    <style>
        .tab-content {
            padding: 0 !important;
            background: transparent !important;
        }
    </style>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <!-- <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script> -->
    <!-- <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script> -->
    <!-- <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script> -->
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-script')
    <!-- <script src="{{ asset('assets/js/modal-edit-user.js') }}"></script> -->
    <script src="{{ asset('assets/js/app-user-view.js') }}"></script>
    <script src="{{ asset('assets/js/app-user-view-account.js') }}"></script>
@endsection

@include('contact.partials.list60-add')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Contacto/</span> {{ $data->name }}
                @if ($data->currentSentiment && $data->currentSentiment->sentiment)
                    {{ $data->currentSentiment->sentiment->emoji }}
                @endif
            </h4>
            <p class="text-muted">
                Creado el {{ Carbon\Carbon::parse($data->created_at)->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @if ($data->trashed())
                @can('update', $data)
                    <form method="POST" action="{{ route('contact.restore', $data->id) }}" onsubmit="return confirm('El contacto vuelve al listado.');">
                        @csrf
                        <button type="submit" class="btn btn-primary waves-effect waves-light">
                            <i class="ti ti-arrow-back-up me-1"></i>Restaurar
                        </button>
                    </form>
                @endcan
            @else
            @can('update', $data)
            @if (auth()->user()->currentTeam?->hasModule('list60'))
                @if ($data->isInList60())
                    <span class="btn btn-success waves-effect waves-light disabled">
                        <i class="ti ti-list-check me-1"></i>{{ __('app.list60') }}
                    </span>
                @else
                    <button type="button" class="btn btn-label-secondary waves-effect" onclick="addToList({{ $data->id }}, this)">
                        <i class="ti ti-list-check me-1"></i>{{ __('app.list60') }}
                    </button>
                @endif
            @endif
            <a href="{{ route('contact.edit', $data->id) }}" class="btn btn-primary waves-effect waves-light"><i
                    class="ti ti-edit me-1"></i>Editar</a>
            @endcan
            @endif
            @if ($data->chatIndexUrl() && (auth()->user()->can('chat.list') || auth()->user()->hasAnyRole(['admin', 'collaborator', 'developer', 'technical', 'marketing'])))
                <a href="{{ $data->chatIndexUrl() }}"
                    class="btn btn-info waves-effect waves-light"><i class="ti ti-message-chatbot me-1"></i>Chat</a>
            @endif
            @can('view', $data)
                @if ($data->mailComposeListUrl())
                    <a href="{{ $data->mailComposeListUrl() }}"
                        class="btn btn-label-primary waves-effect waves-light"><i class="ti ti-mail me-1"></i>{{ __('Mail') }}</a>
                @endif
            @endcan
        </div>
    </div>

    @if ($data->trashed())
        <div class="alert alert-warning" role="alert">
            Este contacto está archivado.
            @if ($mergedInto)
                Se fusionó en <a href="{{ route('contact.show', $mergedInto->id) }}">{{ trim($mergedInto->name.' '.($mergedInto->surname ?? '')) }}</a>.
            @endif
        </div>
    @endif

    <div class="row">
        <!-- User Sidebar -->
        <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
            <!-- User Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section">
                        <div class=" d-flex align-items-center flex-column">
                            <img class="rounded-circle mb-3 mt-4"
                                src="{{ $data->avatarUrl(100) }}" height="100"
                                width="100" alt="User avatar" style="object-fit:cover" />
                            <div class="user-info text-center">
                                <h4 class="mb-2">{{ $data->name }}</h4>
                                @if (auth()->user()->seesFullContactProfile() && $data->enterprises->first() && $data->enterprises->first()->code)
                                    <span class="badge bg-label-secondary mt-1">#{{ $data->enterprises->first()->code }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if (auth()->user()->seesFullContactProfile())
                    <div class="d-flex justify-content-start flex-wrap mt-3 pt-3 pb-4 border-bottom">
                        <div class="d-flex align-items-start me-4 mt-3 gap-2">
                            <span class="badge bg-label-primary p-2 rounded">
                                <i class='ti ti-user-plus ti-sm'></i>
                            </span>
                            <div>
                                <p class="mb-0 fw-medium" style="line-height: 1.2;">
                                    {{ Carbon\Carbon::parse($data->updated_at)->format('d/m/Y') }}</p>
                                <small style="line-height: 1.2;">Última actualización</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mt-3 gap-2" style="min-width: 200px;">
                            <span class="badge bg-label-primary p-2 rounded">
                                <i class='ti ti-hourglass ti-sm'></i>
                            </span>
                            <div>
                                <p class="mb-0 fw-medium" style="line-height: 1.2;">
                                    <span id="totalTime" class="mb-0 fw-medium"
                                        style="line-height: 1.2;">{{ round($totalSeconds) }} segundos</span>
                                </p>
                                <small style="line-height: 1.2;">Tiempo dedicado</small>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="mt-4 info-container">
                        <ul class="list-unstyled">
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Estado:</span>
                                <span class="badge {{ $data->status->label_class }}">{{ $data->status->name }}</span>
                            </li>
                            @if ($data->email)
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Email:</span>
                                    <span>{{ $data->email }}</span>
                                </li>
                            @endif
                            @if ($data->phone)
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Teléfono:</span>
                                    <span>{{ $data->phone }}</span>
                                </li>
                            @endif
                            @if (auth()->user()->seesFullContactProfile())
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">{{ __('WhatsApp assistant:') }}</span>
                                @php
                                    $contactWaAssistantActive = $data->allowsInboundChatAssistant();
                                @endphp
                                <span class="badge {{ $contactWaAssistantActive ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $contactWaAssistantActive ? __('Automatic replies on') : __('Automatic replies off') }}
                                </span>
                            </li>
                            @endif
                            @if ($data->enterprises->count())
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Empresa:</span>
                                    <span>
                                        @if ($data->enterprises->count() === 1)
                                            @php $linkedEnterprise = $data->enterprises->first(); @endphp
                                            @if (auth()->user()->seesFullContactProfile())
                                                <a href="{{ route('empresas.show', $linkedEnterprise->id) }}" class="text-decoration-none" title="{{ __('View company') }}">
                                                    <span class="badge bg-label-primary">{{ $linkedEnterprise->name }}{{ $linkedEnterprise->pivot->position ? ' · '.$linkedEnterprise->pivot->position : '' }}</span>
                                                </a>
                                            @else
                                                <span class="badge bg-label-primary">{{ $linkedEnterprise->name }}</span>
                                            @endif
                                        @else
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <select id="current-enterprise-selector" class="form-select form-select-sm">
                                                    <option value="">Seleccionar empresa</option>
                                                    @foreach ($data->enterprises as $enterprise)
                                                        <option value="{{ $enterprise->id }}"
                                                            {{ $data->current_enterprise_id == $enterprise->id ? 'selected' : '' }}>
                                                            {{ $enterprise->name }}{{ $enterprise->pivot->position ? ' · '.$enterprise->pivot->position : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <a id="go-current-enterprise"
                                                    href="{{ $data->current_enterprise_id ? route('empresas.show', $data->current_enterprise_id) : '#' }}"
                                                    class="btn btn-sm btn-outline-primary {{ $data->current_enterprise_id ? '' : 'disabled' }}"
                                                    title="{{ __('View company') }}">
                                                    <i class="ti ti-building me-1"></i>Ir a la empresa
                                                </a>
                                            </span>
                                        @endif
                                    </span>
                                </li>
                            @endif
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Redes:</span>
                                <span>{!! $data->sources_icons_html !!}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Canal favorito:</span>
                                <span>
                                    @if ($data->primarySource)
                                        {{ $data->primarySource->name }}
                                    @else
                                        No hay canal favorito
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Categorías:</span>
                                <span>
                                    @if ($data->categories->count() > 0)
                                        @foreach ($data->categories as $category)
                                            <span class="badge bg-label-info me-1">{{ $category->name }}</span>
                                        @endforeach
                                    @else
                                        No hay categorías asignadas
                                    @endif
                                </span>
                            </li>
                            @php
                                $countryName = $data->country
                                    ? \App\Models\Country::find($data->country)->name ?? 'No asignado'
                                    : 'No asignado';
                                $languageName = $data->language
                                    ? \App\Models\Language::where('code', $data->language)->value('name') ??
                                        'No asignado'
                                    : 'No asignado';
                            @endphp

                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">País:</span>
                                <span>{{ $countryName }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Idioma:</span>
                                <span>{{ $languageName }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Asesor:</span>
                                <span>{{ $data->responsible->name ?? 'No asignado' }}</span>
                            </li>
                            @if (auth()->user()->seesFullContactProfile())
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Horarios:</span>
                                <span>Sin especificar</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Fecha de nacimiento:</span>
                                <span>
                                    @if (isset($data->birthday))
                                        {{ \Carbon\Carbon::parse($data->birthday)->format('d/m/Y') }}
                                        ({{ \Carbon\Carbon::parse($data->birthday)->age }} años)
                                    @else
                                        No disponible
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Superior:</span>
                                <span>{{ $data->creator->name ?? 'No asignado' }}</span>
                            </li>
                            @endif
                            @if (auth()->user()->seesFullContactProfile())
                            <li class="mb-2 pt-3">
                                @if ($data->user_id && $data->user)
                                    @php $linkedUser = $data->user; @endphp
                                    @if ($linkedUser)
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="badge bg-label-primary me-2">{{ $linkedUser->roles->first()->name ?? 'Sin rol' }}</span>
                                                <span>{{ $linkedUser->name }} ({{ $linkedUser->email }})</span>
                                            </div>
                                            @can('update', $data)
                                                <a href="{{ route('user-unlink.show', ['contact', $data->id]) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                                                    <i class="ti ti-unlink ti-xs"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    @else
                                        <span class="badge bg-label-danger">Usuario no encontrado</span>
                                        @can('update', $data)
                                            <a href="{{ route('user-link.show', ['contact', $data->id]) }}" class="btn btn-sm btn-outline-primary ms-2">
                                                <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                            </a>
                                        @endcan
                                    @endif
                                @else
                                    @can('update', $data)
                                        <a href="{{ route('user-link.show', ['contact', $data->id]) }}" class="btn btn-sm btn-outline-primary ms-2">
                                            <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                        </a>
                                    @endcan
                                @endif
                                @can('update', $data)
                                    @if (! $data->trashed())
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-label-secondary" data-bs-toggle="modal" data-bs-target="#modalMergeContact">
                                                <i class="ti ti-git-merge me-1"></i>Fusionar
                                            </button>
                                        </div>
                                    @endif
                                @endcan
                            </li>
                            @endif
                        </ul>
                        <div class="d-flex justify-content-center">
                            {{-- <a href="javascript:;" class="btn btn-primary me-3" data-bs-target="#editUser"
                                data-bs-toggle="modal">Editar</a> --}}
                            {{-- <a href="javascript:;" class="btn btn-label-danger suspend-user">Suspender</a> --}}
                        </div>
                    </div>
                </div>
            </div>
            <!-- /User Card -->
            <!-- Plan Card -->
            {{-- <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="badge bg-label-primary">Standard</span>
                        <div class="d-flex justify-content-center">
                            <sup class="h6 pricing-currency mt-3 mb-0 me-1 text-primary fw-normal">$</sup>
                            <h1 class="mb-0 text-primary">99</h1>
                            <sub class="h6 pricing-duration mt-auto mb-2 text-muted fw-normal">/month</sub>
                        </div>
                    </div>
                    <ul class="ps-3 g-2 my-3">
                        <li class="mb-2">10 Users</li>
                        <li class="mb-2">Up to 10 GB storage</li>
                        <li>Basic Support</li>
                    </ul>
                    <div class="d-flex justify-content-between align-items-center mb-1 fw-medium text-heading">
                        <span>Days</span>
                        <span>65% Completed</span>
                    </div>
                    <div class="progress mb-1" style="height: 8px;">
                        <div class="progress-bar" role="progressbar" style="width: 65%;" aria-valuenow="65"
                            aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span>4 days remaining</span>
                    <div class="d-grid w-100 mt-4">
                        <button class="btn btn-primary" data-bs-target="#upgradePlanModal" data-bs-toggle="modal">Upgrade
                            Plan</button>
                    </div>
                </div>
            </div> --}}
            <!-- /Plan Card -->
        </div>
        <!--/ User Sidebar -->


        <!-- User Content -->
        <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
            <!-- User Pills -->
            <ul class="nav nav-pills flex-column flex-md-row mb-4" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab"
                        aria-controls="general" aria-selected="true">
                        <i class="ti ti-user ti-xs me-1"></i>General
                    </a>
                </li>
                @if (auth()->user()->seesFullContactProfile())
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity" role="tab"
                        aria-controls="activity" aria-selected="false">
                        <i class="ti ti-history ti-xs me-1"></i>{{ __('Activity') }}
                    </a>
                </li>
                @endif
                @role('admin')
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="balance-tab" data-bs-toggle="tab" href="#balance" role="tab"
                        aria-controls="balance" aria-selected="false">
                        <i class="ti ti-wallet ti-xs me-1"></i>Saldo
                    </a>
                </li>
                @endrole
                @role('admin|collaborator')
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="billing-tab" data-bs-toggle="tab" href="#billing" role="tab"
                        aria-controls="billing" aria-selected="false">
                        <i class="ti ti-map-pin ti-xs me-1"></i>Facturación
                    </a>
                </li>
                @endrole
                @can('viewAny', \App\Models\Ticket::class)
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tickets-tab" data-bs-toggle="tab" href="#tickets" role="tab"
                        aria-controls="tickets" aria-selected="false">
                        <i class="ti ti-ticket ti-xs me-1"></i>{{ __('tickets.Tickets') }}
                    </a>
                </li>
                @endcan
            </ul>
            <!--/ User Pills -->

            <div class="tab-content">
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    @include('contact.partials.general')
                </div>

                @if (auth()->user()->seesFullContactProfile())
                <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                    @include('contact.partials.activity')
                </div>
                @endif
                @role('admin')
                <div class="tab-pane fade" id="balance" role="tabpanel" aria-labelledby="balance-tab">
                    @include('contact.partials.balance')
                </div>
                @endrole
                @role('admin|collaborator')
                <div class="tab-pane fade" id="billing" role="tabpanel" aria-labelledby="billing-tab">
                    @include('contact.partials.billing')
                </div>
                @endrole
                @can('viewAny', \App\Models\Ticket::class)
                <div class="tab-pane fade" id="tickets" role="tabpanel" aria-labelledby="tickets-tab">
                    @include('contact.partials.tickets')
                </div>
                @endcan
            </div>


        </div>
        <!--/ User Content -->
    </div>

    <!-- Modal -->
    {{-- @include('_partials/_modals/modal-edit-user') --}}
    {{-- @include('_partials/_modals/modal-upgrade-plan') --}}

    @if($data->birthday)
        @include('contact.partials.astral-data-modal')
    @endif
    <!-- /Modal -->
@endsection

@push('modals')
    <!-- Modal para añadir estado emocional -->
    <div class="modal fade" id="updateSentimentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir estado emocional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateSentimentForm" method="POST"
                    action="{{ route('contact.update-sentiment', $data->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="sentiment_id" class="form-label">Estado emocional</label>
                                <select id="sentiment_id" name="sentiment_id" class="form-select" required>
                                    <option value="" selected disabled>Selecciona un estado emocional</option>
                                    @foreach ($sentiments as $sentiment)
                                        <option value="{{ $sentiment->id }}">{{ $sentiment->name }}
                                            {!! $sentiment->emoji !!}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" id="sentiment_id_error"></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="notes" class="form-label">Notas</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" required></textarea>
                                <div class="invalid-feedback" id="notes_error"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endpush

@can('update', $data)
    @if (! $data->trashed())
        @push('modals')
            <div class="modal fade" id="modalMergeContact" tabindex="-1" aria-labelledby="modalMergeContactLabel" aria-hidden="true"
                data-candidates-url="{{ route('contact.merge-candidates', $data->id) }}"
                data-preview-url="{{ route('contact.merge-preview', $data->id) }}"
                data-merge-url="{{ route('contact.merge', $data->id) }}">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalMergeContactLabel">Fusionar contacto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Se conserva este contacto. El otro se archiva. Si está en otras empresas, esos vínculos pasan con el rol de cada una.</p>
                            <label for="mergeContactSearchInput" class="form-label">Buscar el contacto duplicado</label>
                            <input type="search" class="form-control" id="mergeContactSearchInput" placeholder="Nombre, email o teléfono…" autocomplete="off">
                            <div id="mergeContactFeedback" class="alert d-none mt-3 mb-0" role="alert"></div>
                            <div id="mergeContactList" class="list-group list-group-flush mt-3 border rounded d-none"></div>
                            <div id="mergeContactPreview" class="d-none mt-3">
                                <p id="mergeContactPreviewMessage" class="mb-2"></p>
                                <ul id="mergeContactPreviewLines" class="mb-0"></ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-primary" id="mergeContactSubmitBtn" disabled>Fusionar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endpush
    @endif
@endcan

@push('scripts')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>

    <script>
        (function () {
            ['#activity', '#tickets'].forEach(function (hash) {
                if (window.location.hash !== hash) {
                    return;
                }
                var trigger = document.querySelector('a[href="' + hash + '"][data-bs-toggle="tab"]');
                if (trigger && typeof bootstrap !== 'undefined') {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                }
            });
        })();

        function toggleNotesEdit() {
            const notes = document.getElementById('contact-notes').value;
            const contactId = window.location.pathname.split('/')[2];

            fetch(`/contact/${contactId}/notes`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        toastr.success('Notas guardadas correctamente');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toastr.error('Error al guardar las notas');
                });
        }

        $(document).ready(function() {
            $('.add-sentiment-btn').on('click', function() {
                $('#updateSentimentModal').modal('show');
            });

            $('#updateSentimentForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');

                // Reset previous errors
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');

                $.ajax({
                    type: "POST",
                    url: url,
                    data: form.serialize(),
                    success: function(response) {
                        $('#updateSentimentModal').modal('hide');
                        toastr.success(response.message);
                        updateEmotionalHistory(response);
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            // Mostrar errores de validación
                            $.each(errors, function(key, value) {
                                $('#' + key).addClass('is-invalid');
                                $('#' + key + '_error').text(value[0]);
                            });
                        } else {
                            toastr.error('An error occurred. Please try again.');
                        }
                    }
                });
            });
        });

        function updateEmotionalHistory(response) {
            var newItem = `
                <li class="timeline-item timeline-item-transparent">
                    <span class="timeline-point timeline-point-transparent" style="background: none; font-size: 1.5em; display: flex; align-items: center; justify-content: center;">${response.newEmoji}</span>
                    <div class="timeline-event">
                        <div class="timeline-header mb-1">
                            <h6 class="mb-0">${$('#notes').val()}</h6>
                            <small class="text-muted">Ahora mismo</small>
                        </div>
                    </div>
                </li>
            `;

            $('.timeline').prepend(newItem);

            if ($('.timeline-item').length > 5) {
                $('.timeline-item:last').remove();
            }
        }

        window.addEventListener('beforeunload', function() {
            fetch(`{{ route('contact.end-action', session('tracking_id')) }}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
        });

        let totalSeconds = Math.round({{ $totalSeconds }});
        setInterval(() => {
            totalSeconds++;
            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = Math.round(totalSeconds % 60);

            let formattedTime;
            if (hours > 0) {
                formattedTime = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
            } else if (minutes > 0) {
                formattedTime = `${minutes} minutos`;
            } else {
                formattedTime = `${Math.round(seconds)} segundos`;
            }

            document.getElementById('totalTime').textContent = formattedTime;
        }, 1000);

        var $currentEnterprise = $('#current-enterprise-selector');
        if ($currentEnterprise.length && $.fn.select2) {
            $currentEnterprise.wrap('<div class="position-relative d-inline-block"></div>');
            $currentEnterprise.select2({
                width: '18rem',
                minimumResultsForSearch: Infinity,
                dropdownAutoWidth: true,
            });
            $currentEnterprise.on('select2:open', function () {
                var dropdown = document.querySelector('.select2-container--open .select2-dropdown');
                if (!dropdown) {
                    return;
                }
                dropdown.style.width = 'max-content';
                dropdown.style.minWidth = '18rem';
                dropdown.style.maxWidth = '28rem';
            });
        }

        $currentEnterprise.on('change', function() {
            const enterpriseId = $(this).val();
            const contactId = {{ $data->id }};
            const goLink = document.getElementById('go-current-enterprise');

            if (!enterpriseId) {
                if (goLink) {
                    goLink.setAttribute('href', '#');
                    goLink.classList.add('disabled');
                }
                return;
            }

            if (goLink) {
                goLink.setAttribute('href', `{{ url('/empresas') }}/${enterpriseId}`);
                goLink.classList.remove('disabled');
            }

            $.ajax({
                url: `/contact/${contactId}/set-current-enterprise`,
                method: 'POST',
                data: {
                    enterprise_id: enterpriseId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        // Reload page to update Stripe data and other enterprise-related info
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error al actualizar la empresa');
                }
            });
        });

    </script>
    <script>
        var modalMergeContact = document.getElementById('modalMergeContact');
        if (modalMergeContact) {
            var mergeContactCandidatesUrl = modalMergeContact.getAttribute('data-candidates-url');
            var mergeContactPreviewUrl = modalMergeContact.getAttribute('data-preview-url');
            var mergeContactUrl = modalMergeContact.getAttribute('data-merge-url');
            var mergeContactSearch = document.getElementById('mergeContactSearchInput');
            var mergeContactList = document.getElementById('mergeContactList');
            var mergeContactPreview = document.getElementById('mergeContactPreview');
            var mergeContactPreviewMessage = document.getElementById('mergeContactPreviewMessage');
            var mergeContactPreviewLines = document.getElementById('mergeContactPreviewLines');
            var mergeContactFeedback = document.getElementById('mergeContactFeedback');
            var mergeContactSubmit = document.getElementById('mergeContactSubmitBtn');
            var mergeContactSelectedId = null;
            var mergeContactLines = [];
            var mergeContactTimer = null;

            function mergeContactEsc(value) {
                var holder = document.createElement('div');
                holder.textContent = value === null || value === undefined ? '' : String(value);
                return holder.innerHTML;
            }

            function mergeContactFeedbackHide() {
                mergeContactFeedback.classList.add('d-none');
                mergeContactFeedback.textContent = '';
            }

            function mergeContactFeedbackShow(message, tone) {
                mergeContactFeedback.className = 'alert alert-' + tone + ' mt-3 mb-0';
                mergeContactFeedback.textContent = message;
            }

            function mergeContactResetPreview() {
                mergeContactSelectedId = null;
                mergeContactSubmit.disabled = true;
                mergeContactPreview.classList.add('d-none');
                mergeContactPreviewMessage.textContent = '';
                mergeContactPreviewLines.innerHTML = '';
                mergeContactLines = [];
            }

            function mergeContactDialogHtml(message, lines) {
                var html = '<p class="mb-2">' + mergeContactEsc(message) + '</p>';
                if (!lines.length) {
                    return html;
                }
                html += '<ul class="text-start mb-0">';
                lines.forEach(function (line) {
                    html += '<li>' + mergeContactEsc(line) + '</li>';
                });
                html += '</ul>';
                return html;
            }

            function mergeContactDialog(options) {
                var modal = window.bootstrap && bootstrap.Modal.getInstance(modalMergeContact);
                if (modal && modal._focustrap) {
                    modal._focustrap.deactivate();
                }
                return Swal.fire({
                    icon: options.icon,
                    title: options.title,
                    html: mergeContactDialogHtml(options.message, options.lines || []),
                    showCancelButton: !!options.showCancel,
                    confirmButtonText: options.confirmText,
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    focusCancel: true,
                    customClass: {
                        confirmButton: 'btn btn-primary me-2',
                        cancelButton: 'btn btn-label-secondary',
                    },
                    didOpen: function () {
                        var container = Swal.getContainer();
                        if (container) {
                            container.style.zIndex = '20000';
                        }
                    },
                }).then(function (result) {
                    if (modal && modal._focustrap) {
                        modal._focustrap.activate();
                    }
                    return result;
                });
            }

            function loadContactMergePreview(contactId) {
                mergeContactResetPreview();
                mergeContactFeedbackHide();
                fetch(mergeContactPreviewUrl + '?contact_id=' + encodeURIComponent(contactId), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (response) { return response.json(); })
                    .then(function (body) {
                        mergeContactPreview.classList.remove('d-none');
                        mergeContactPreviewMessage.textContent = body.message || '';
                        mergeContactLines = body.lines || [];
                        mergeContactLines.forEach(function (line) {
                            var item = document.createElement('li');
                            item.textContent = line;
                            mergeContactPreviewLines.appendChild(item);
                        });
                        if (body.blocked) {
                            mergeContactFeedbackShow(body.message || 'No se puede fusionar.', 'warning');
                            return;
                        }
                        mergeContactSelectedId = contactId;
                        mergeContactSubmit.disabled = false;
                    })
                    .catch(function () {
                        mergeContactFeedbackShow('No se pudo preparar la fusión.', 'danger');
                    });
            }

            mergeContactSearch.addEventListener('input', function () {
                var term = mergeContactSearch.value.trim();
                mergeContactResetPreview();
                mergeContactFeedbackHide();
                clearTimeout(mergeContactTimer);
                if (term.length < 2) {
                    mergeContactList.classList.add('d-none');
                    mergeContactList.innerHTML = '';
                    return;
                }
                mergeContactTimer = setTimeout(function () {
                    fetch(mergeContactCandidatesUrl + '?q=' + encodeURIComponent(term), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (body) {
                            mergeContactList.innerHTML = '';
                            var rows = body.contacts || [];
                            if (!rows.length) {
                                mergeContactList.classList.add('d-none');
                                return;
                            }
                            mergeContactList.classList.remove('d-none');
                            rows.forEach(function (row) {
                                var button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'list-group-item list-group-item-action';
                                button.innerHTML = '<span class="fw-medium">' + mergeContactEsc(row.name) + '</span><br><small class="text-muted">' + mergeContactEsc(row.subtitle) + '</small>';
                                button.addEventListener('click', function () {
                                    mergeContactList.querySelectorAll('.active').forEach(function (item) {
                                        item.classList.remove('active');
                                    });
                                    button.classList.add('active');
                                    loadContactMergePreview(row.id);
                                });
                                mergeContactList.appendChild(button);
                            });
                        })
                        .catch(function () { mergeContactFeedbackShow('No se pudo buscar.', 'danger'); });
                }, 300);
            });

            function mergeContactPost() {
                mergeContactSubmit.disabled = true;
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                fetch(mergeContactUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ contact_id: mergeContactSelectedId }),
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.body.success) {
                            mergeContactSubmit.disabled = false;
                            mergeContactFeedbackShow((result.body && result.body.message) ? result.body.message : 'No se pudo fusionar.', 'danger');
                            return;
                        }
                        var doneLines = (result.body && result.body.lines) ? result.body.lines : mergeContactLines;
                        mergeContactDialog({
                            icon: 'success',
                            title: 'Valores fusionados',
                            message: (result.body && result.body.message) ? result.body.message : 'Contactos fusionados.',
                            lines: doneLines,
                            confirmText: 'Ver contacto',
                        }).then(function () {
                            window.location.href = result.body.redirect;
                        });
                    })
                    .catch(function () {
                        mergeContactSubmit.disabled = false;
                        mergeContactFeedbackShow('Error de red al fusionar.', 'danger');
                    });
            }

            mergeContactSubmit.addEventListener('click', function () {
                if (!mergeContactSelectedId) {
                    return;
                }
                mergeContactDialog({
                    icon: 'warning',
                    title: 'Confirmá la fusión',
                    message: mergeContactPreviewMessage.textContent || 'Se archiva el contacto duplicado.',
                    lines: mergeContactLines,
                    showCancel: true,
                    confirmText: 'Fusionar',
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }
                    mergeContactPost();
                });
            });
        }
    </script>
@endpush
