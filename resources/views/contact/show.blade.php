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
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/modal-edit-user.js') }}"></script>
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
                {{ Carbon\Carbon::parse($data->created_at)->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="{{ route('contact.create') }}" type="submit" class="btn btn-primary waves-effect waves-light"><i
                    class="ti ti-plus me-1"></i>Añadir informe</a>
            <a href="#" class="btn btn-info waves-effect waves-light"><i
                    class="ti ti-message-chatbot me-1"></i>Chat</a>
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
                            <img class="img-fluid rounded mb-3 pt-1 mt-4" src="https://ui-avatars.com/api/?format=svg&name={{ $data->name }}"
                                height="100" width="100" alt="User avatar" />
                            <div class="user-info text-center">
                                <h4 class="mb-2">{{ $data->name }}</h4>
                                <span class="badge bg-label-secondary mt-1">Customer ID #{{ $data->id }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around flex-wrap mt-3 pt-3 pb-4 border-bottom">
                        <div class="d-flex align-items-start me-4 mt-3 gap-2">
                            <span class="badge bg-label-primary p-2 rounded"><i
                                    class='ti ti-shopping-cart ti-sm'></i></span>
                            <div>
                                <p class="mb-0 fw-medium">{{ Carbon\Carbon::parse($data->created_at)->format('d/m/Y') }}
                                </p>
                                <small>Primera compra</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mt-3 gap-2" style="min-width: 200px;">
                            <span class="badge bg-label-primary p-2 rounded"><i
                                    class='ti ti-currency-dollar ti-sm'></i></span>
                            <div>
                                <p class="mb-0 fw-medium">
                                    <span id="totalTime" class="mb-0 fw-medium">{{ $totalSeconds }} segundos</span>
                                </p>
                                <small>LTV</small>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 small text-uppercase text-muted">Detalle</p>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            @if ($data->user_id)
                                <li class="mb-2">
                                    <span class="fw-medium me-1">Username:</span>
                                    <span>{{ $data->username }}</span>
                                </li>
                            @endif
                            {{-- <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Billing email:</span>
                                <span>Sin empresa vinculada</span>
                            </li> --}}
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Estado:</span>
                                <span class="badge {{ $data->status->label_class }}">{{ $data->status->name }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Contacto:</span>
                                <span>
                                    @if ($data->phone)
                                        @php
                                            $phone = $data->phone;
                                            $countryCode = substr($phone, 0, 2);
                                            $restOfNumber = substr($phone, 2);
                                            $formattedNumber = preg_replace(
                                                '/(\d{3})(\d{3})(\d{3})/',
                                                "$1 $2 $3",
                                                $restOfNumber,
                                            );
                                        @endphp
                                        +{{ $countryCode }} {{ $formattedNumber }}
                                    @else
                                        No disponible
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">País:</span>
                                <span>{{ $data->country ?? 'No asignado' }}</span>
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
                            {{-- //TODO - Cargo,profesión o título del contacto
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Cargo:</span>
                                <span>-</span>
                            </li> --}}
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
                            <li class="pt-1">
                                <span class="fw-medium me-1">Superior:</span>
                                <span>{{ $data->creator->name ?? 'No asignado' }}</span>
                            </li>
                        </ul>
                        <div class="d-flex justify-content-center">
                            <a href="javascript:;" class="btn btn-primary me-3" data-bs-target="#editUser"
                                data-bs-toggle="modal">Editar</a>
                            <a href="javascript:;" class="btn btn-label-danger suspend-user">Suspender</a>
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
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i
                            class="ti ti-user-check ti-xs me-1"></i>Account</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('app/user/view/security') }}"><i
                            class="ti ti-lock ti-xs me-1"></i>Security</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('app/user/view/billing') }}"><i
                            class="ti ti-currency-dollar ti-xs me-1"></i>Billing & Plans</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('app/user/view/notifications') }}"><i
                            class="ti ti-bell ti-xs me-1"></i>Notifications</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('app/user/view/connections') }}"><i
                            class="ti ti-link ti-xs me-1"></i>Connections</a></li>
            </ul>
            <!--/ User Pills -->

            <!-- Emotional History -->
            <div class="card mb-4">
                <h5 class="card-header d-flex justify-content-between align-items-center">
                    Emotional History
                    <button type="button" class="btn btn-primary btn-sm add-sentiment-btn">
                        + Añadir estado emocional
                    </button>
                </h5>
                <div class="card-body">
                    <ul class="timeline mb-0 ms-3">
                        @foreach ($data->sentimentHistories->sortByDesc('created_at')->take(5) as $sentimentHistory)
                            <li class="timeline-item timeline-item-transparent">
                                <span class="timeline-point timeline-point-transparent"
                                    style="background: none; font-size: 1.5em; display: flex; align-items: center; justify-content: center;">{!! $sentimentHistory->sentiment->emoji !!}</span>
                                <div class="timeline-event">
                                    <div class="timeline-header mb-1">
                                        <h6 class="mb-0">{{ $sentimentHistory->notes }}</h6>
                                        <small class="text-muted">
                                            @if ($sentimentHistory->created_at->diffInDays(now()) < 7)
                                                {{ $sentimentHistory->created_at->diffForHumans() }}
                                            @else
                                                {{ $sentimentHistory->created_at->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <!-- /Emotional History -->
        </div>
        <!--/ User Content -->
    </div>

    <!-- Modal -->
    @include('_partials/_modals/modal-edit-user')
    @include('_partials/_modals/modal-upgrade-plan')
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
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>

    <script>
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

        function endActionTracking(trackingId) {
            fetch(`/contact/end-action/${trackingId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Acción finalizada correctamente');
                    } else {
                        console.error('Error al finalizar el seguimiento de la acción');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const trackingId = {{ $trackingId ?? 'null' }};

            if (trackingId) {
                window.addEventListener('beforeunload', function() {
                    endActionTracking(trackingId);
                });

                document.body.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A' && e.target.href && !e.target.href.startsWith(window
                            .location.origin + window.location.pathname)) {
                        e.preventDefault();
                        endActionTracking(trackingId);
                        setTimeout(() => {
                            window.location.href = e.target.href;
                        }, 100);
                    }
                });
            }
        });

        let totalSeconds = {{ $totalSeconds }};
        setInterval(() => {
            totalSeconds++;
            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = totalSeconds % 60;

            let formattedTime = `${seconds} segundos`;
            if (minutes > 0) {
                formattedTime = `${minutes} minutos, ${formattedTime}`;
            }
            if (hours > 0) {
                formattedTime = `${hours} horas, ${formattedTime}`;
            }

            document.getElementById('totalTime').textContent = formattedTime;
        }, 1000);
    </script>
@endpush
