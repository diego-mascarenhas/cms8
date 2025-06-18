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
@endsection

@section('page-script')
    <!-- <script src="{{ asset('assets/js/modal-edit-user.js') }}"></script> -->
    <script src="{{ asset('assets/js/app-user-view.js') }}"></script>
    <script src="{{ asset('assets/js/app-user-view-account.js') }}"></script>
@endsection

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
            <!-- <a href="{{ route('contact.create') }}" type="submit" class="btn btn-primary waves-effect waves-light"><i
                            class="ti ti-plus me-1"></i>Añadir informe</a> -->
            <a href="{{ route('contact.edit', $data->id) }}" class="btn btn-primary waves-effect waves-light"><i
                    class="ti ti-edit me-1"></i>Editar contacto</a>
            @can('project.create')
                <a href="{{ route('project.create', ['enterprise_id' => $data->enterprise ? $data->enterprise->id : null]) }}" class="btn btn-success waves-effect waves-light"><i
                        class="ti ti-folder-plus me-1"></i>Crear proyecto</a>
            @endcan
            @can('service.create')
                <a href="{{ route('service.create', ['enterprise_id' => $data->enterprise ? $data->enterprise->id : null]) }}" class="btn btn-info waves-effect waves-light ms-2"><i
                        class="ti ti-server me-1"></i>Crear servicio</a>
            @endcan
            @can('chat.list')
                @if ($data->getWhatsAppNumber())
                    <a href="{{ route('chat.index') }}?phone={{ $data->getWhatsAppNumber() }}"
                        class="btn btn-info waves-effect waves-light"><i class="ti ti-message-chatbot me-1"></i>Chat</a>
                @endif
            @endcan
            @if (auth()->user()->currentTeam->id == env('CMS_TEAM_ID') && isset($data->enterprise->id))
                <a href="{{ route('cms7.empresa', $data->enterprise->id) }}" class="btn btn-secondary waves-effect waves-light" target="_blank">
                    <i class="ti ti-database me-1"></i>
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- User Sidebar -->
        <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
            <!-- User Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section">
                        <div class=" d-flex align-items-center flex-column">
                            <img class="img-fluid rounded mb-3 pt-1 mt-4"
                                src="https://ui-avatars.com/api/?format=svg&name={{ $data->name }}" height="100"
                                width="100" alt="User avatar" />
                            <div class="user-info text-center">
                                <h4 class="mb-2">{{ $data->name }}</h4>
                                @if ($data->enterprise && $data->enterprise->code)
                                    <span class="badge bg-label-secondary mt-1">#{{ $data->enterprise->code }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
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
                                        style="line-height: 1.2;">{{ $totalSeconds }} segundos</span>
                                </p>
                                <small style="line-height: 1.2;">Tiempo dedicado</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 info-container">
                        <ul class="list-unstyled">
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Estado:</span>
                                <span class="badge {{ $data->status->label_class }}">{{ $data->status->name }}</span>
                            </li>
                            @if ($data->user_id)
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Usuario:</span>
                                    <span>
                                        @php $linkedUser = \App\Models\User::find($data->user_id); @endphp
                                        @if ($linkedUser)
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <img class="rounded-circle" src="https://ui-avatars.com/api/?format=svg&name={{ urlencode($linkedUser->name) }}" alt="{{ $linkedUser->name }}">
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium">{{ $linkedUser->name }}</span>
                                                        <small class="d-block text-muted">{{ $linkedUser->email }}</small>
                                                        @if($linkedUser->roles->count() > 0)
                                                            <span class="badge bg-label-info badge-sm">{{ $linkedUser->roles->first()->name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @can('contact.edit')
                                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" onclick="unlinkUser({{ $data->id }})">
                                                        <i class="ti ti-unlink ti-xs"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        @else
                                            <span class="badge bg-label-danger">Usuario no encontrado</span>
                                            @can('contact.edit')
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="showLinkUserModal({{ $data->id }})">
                                                    <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                                </button>
                                            @endcan
                                        @endif
                                    </span>
                                </li>
                            @else
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Usuario:</span>
                                    <span class="text-muted">Sin vincular</span>
                                    @can('contact.edit')
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="showLinkUserModal({{ $data->id }})">
                                            <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                        </button>
                                    @endcan
                                </li>
                            @endif
                            @if ($data->enterprise)
                                <li class="mb-2 pt-1">
                                    <span class="fw-medium me-1">Empresa:</span>
                                    <span>{{ $data->enterprise->name }}</span>
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
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="emotional-balance-tab" data-bs-toggle="tab" href="#emotional-balance"
                        role="tab" aria-controls="emotional-balance" aria-selected="false">
                        <i class="ti ti-mood-happy ti-xs me-1"></i>Emociones
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="evolution-tab" data-bs-toggle="tab" href="#evolution" role="tab"
                        aria-controls="evolution" aria-selected="false">
                        <i class="ti ti-chart-line ti-xs me-1"></i>Evolución
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="balance-tab" data-bs-toggle="tab" href="#balance" role="tab"
                        aria-controls="balance" aria-selected="false">
                        <i class="ti ti-wallet ti-xs me-1"></i>Saldo
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="billing-tab" data-bs-toggle="tab" href="#billing" role="tab"
                        aria-controls="billing" aria-selected="false">
                        <i class="ti ti-map-pin ti-xs me-1"></i>Facturación
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="scheduled-tab" data-bs-toggle="tab" href="#scheduled" role="tab"
                        aria-controls="scheduled" aria-selected="false">
                        <i class="ti ti-bell ti-xs me-1"></i>Programado
                    </a>
                </li>
            </ul>
            <!--/ User Pills -->

            <div class="tab-content">
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    @include('contact.partials.general')
                </div>
                <div class="tab-pane fade" id="emotional-balance" role="tabpanel"
                    aria-labelledby="emotional-balance-tab">
                    @include('contact.partials.emotional')
                </div>
                <div class="tab-pane fade" id="evolution" role="tabpanel" aria-labelledby="evolution-tab">
                    @include('contact.partials.evolution')
                </div>
                <div class="tab-pane fade" id="balance" role="tabpanel" aria-labelledby="balance-tab">
                    @include('contact.partials.balance')
                </div>
                <div class="tab-pane fade" id="billing" role="tabpanel" aria-labelledby="billing-tab">
                    @include('contact.partials.billing')
                </div>
                <div class="tab-pane fade" id="scheduled" role="tabpanel" aria-labelledby="scheduled-tab">
                    @include('contact.partials.scheduled')
                </div>
            </div>


        </div>
        <!--/ User Content -->
    </div>

    <!-- Modal -->
    {{-- @include('_partials/_modals/modal-edit-user') --}}
    {{-- @include('_partials/_modals/modal-upgrade-plan') --}}
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

    <!-- Modal para vincular usuario -->
    <div class="modal fade" id="linkUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vincular Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="linkUserTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="existing-user-tab" data-bs-toggle="tab" data-bs-target="#existing-user" type="button" role="tab">
                                <i class="ti ti-user ti-xs me-1"></i>Usuario Existente
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="new-user-tab" data-bs-toggle="tab" data-bs-target="#new-user" type="button" role="tab">
                                <i class="ti ti-user-plus ti-xs me-1"></i>Crear Usuario
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3" id="linkUserTabsContent">
                        <!-- Existing User Tab -->
                        <div class="tab-pane fade show active" id="existing-user" role="tabpanel">
                            <form id="linkExistingUserForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="user_search" class="form-label">Buscar Usuario</label>
                                    <select id="user_search" name="user_id" class="form-select select2" required>
                                        <option value="">-- Seleccionar usuario --</option>
                                        @foreach(\App\Models\User::whereHas('teams', function($q) { $q->where('team_id', auth()->user()->currentTeam->id); })->orderBy('name')->get() as $user)
                                            <option value="{{ $user->id }}" data-email="{{ $user->email }}" data-role="{{ $user->roles->first()->name ?? 'user' }}">
                                                {{ $user->name }} ({{ $user->email }}) - {{ $user->roles->first()->name ?? 'user' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Vincular Usuario</button>
                                </div>
                            </form>
                        </div>

                        <!-- New User Tab -->
                        <div class="tab-pane fade" id="new-user" role="tabpanel">
                            <form id="createUserForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_user_name" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="new_user_name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_user_email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="new_user_email" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_user_phone" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="new_user_phone" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_user_role" class="form-label">Rol *</label>
                                        <select class="form-select" id="new_user_role" name="role" required>
                                            <option value="">-- Seleccionar rol --</option>
                                            @foreach(\Spatie\Permission\Models\Role::all() as $role)
                                                <option value="{{ $role->name }}" {{ $role->name === 'collaborator' ? 'selected' : '' }}>
                                                    {{ ucfirst($role->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="new_user_password" class="form-label">Contraseña temporal *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_user_password" name="password" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                                <i class="ti ti-refresh ti-xs"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">El usuario deberá cambiar esta contraseña en su primer acceso</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Crear y Vincular</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>

    <script>
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

        let totalSeconds = {{ $totalSeconds }};
        setInterval(() => {
            totalSeconds++;
            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = totalSeconds % 60;

            let formattedTime;
            if (hours > 0) {
                formattedTime = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
            } else if (minutes > 0) {
                formattedTime = `${minutes} minutos`;
            } else {
                formattedTime = `${seconds} segundos`;
            }

            document.getElementById('totalTime').textContent = formattedTime;
        }, 1000);

        // User linking functionality
        function showLinkUserModal(contactId) {
            window.currentContactId = contactId;
            // Pre-fill contact data if available
            const contactName = '{{ $data->name }}';
            const contactEmail = '{{ $data->email }}';
            
            if (contactName) {
                document.getElementById('new_user_name').value = contactName;
            }
            if (contactEmail) {
                document.getElementById('new_user_email').value = contactEmail;
            }
            
            $('#linkUserModal').modal('show');
        }

        function unlinkUser(contactId) {
            if (confirm('¿Estás seguro de que quieres desvincular este usuario?')) {
                fetch(`/contact/${contactId}/unlink-user`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        toastr.success('Usuario desvinculado correctamente');
                        location.reload();
                    } else {
                        toastr.error(data.message || 'Error al desvincular usuario');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    toastr.error('Error al desvincular usuario');
                });
            }
        }

        function generatePassword() {
            const length = 10;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
            let password = "";
            for (let i = 0, n = charset.length; i < length; ++i) {
                password += charset.charAt(Math.floor(Math.random() * n));
            }
            document.getElementById('new_user_password').value = password;
        }

        // Initialize Select2 when modal is shown
        $('#linkUserModal').on('shown.bs.modal', function () {
            $('#user_search').select2({
                dropdownParent: $('#linkUserModal'),
                placeholder: 'Buscar usuario...',
                allowClear: true
            });
        });

        // Handle existing user linking
        $('#linkExistingUserForm').on('submit', function(e) {
            e.preventDefault();
            
            const userId = $('#user_search').val();
            if (!userId) {
                toastr.error('Por favor selecciona un usuario');
                return;
            }

            fetch(`/contact/${window.currentContactId}/link-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Usuario vinculado correctamente');
                    $('#linkUserModal').modal('hide');
                    location.reload();
                } else {
                    toastr.error(data.message || 'Error al vincular usuario');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Error al vincular usuario');
            });
        });

        // Handle new user creation and linking
        $('#createUserForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);

            fetch(`/contact/${window.currentContactId}/create-and-link-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Usuario creado y vinculado correctamente');
                    $('#linkUserModal').modal('hide');
                    location.reload();
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            toastr.error(data.errors[field][0]);
                        });
                    } else {
                        toastr.error(data.message || 'Error al crear usuario');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('Error al crear usuario');
            });
        });

        // Generate password on page load
        $(document).ready(function() {
            generatePassword();
        });
    </script>
@endpush
