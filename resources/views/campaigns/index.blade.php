@extends('layouts/layoutMaster')

@section('title', __('Campañas'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
    $(function ()
    {
        const campaignTypeFilter = $('#campaign-type-filter');
        const campaignStatusFilter = $('#campaign-status-filter');

        if (campaignTypeFilter.length && $.fn.select2)
        {
            campaignTypeFilter.select2({
                placeholder: @json(__('Tipo')),
                minimumResultsForSearch: Infinity,
                width: '170px',
            });
        }

        if (campaignStatusFilter.length && $.fn.select2)
        {
            campaignStatusFilter.select2({
                placeholder: @json(__('Estado')),
                minimumResultsForSearch: Infinity,
                width: '170px',
            });
        }

        const campaignCreationModal = $('#newCampaignModal');
        const optionCards = campaignCreationModal.find('[data-campaign-option]');
        const selectedTypeInput = $('#new-campaign-type');
        const sequenceTitleWrapper = $('#sequence-title-wrapper');
        const sequenceTitleInput = $('#sequence-title-input');
        const continueButton = $('#new-campaign-continue-btn');
        const creationForm = $('#new-campaign-form');
        const stepOne = $('#new-campaign-step-1');

        const resetModalState = function ()
        {
            optionCards
                .removeClass('border-dark border-3')
                .addClass('border');
            selectedTypeInput.val('');
            sequenceTitleWrapper.addClass('d-none');
            sequenceTitleInput.val('');
            continueButton.prop('disabled', true);
            stepOne.removeClass('d-none');
        };

        const refreshContinueState = function ()
        {
            const selectedType = selectedTypeInput.val();
            if (!selectedType)
            {
                continueButton.prop('disabled', true);
                return;
            }

            if (selectedType === 'sequences')
            {
                continueButton.prop('disabled', sequenceTitleInput.val().trim() === '');
                return;
            }

            continueButton.prop('disabled', false);
        };

        campaignCreationModal.on('show.bs.modal', function ()
        {
            resetModalState();
        });

        optionCards.on('click', function ()
        {
            const selectedType = $(this).data('campaign-option');
            selectedTypeInput.val(selectedType);

            optionCards
                .removeClass('border-dark border-3')
                .addClass('border');

            $(this)
                .removeClass('border')
                .addClass('border-dark border-3');

            if (selectedType === 'sequences')
            {
                sequenceTitleWrapper.removeClass('d-none');
            } else
            {
                sequenceTitleWrapper.addClass('d-none');
                sequenceTitleInput.val('');
            }

            refreshContinueState();
        });

        sequenceTitleInput.on('input', function ()
        {
            refreshContinueState();
        });

        creationForm.on('submit', function (event)
        {
            event.preventDefault();
            const templateSelectionUrl = new URL(@json(route('campaigns.templates.select')), window.location.origin);
            templateSelectionUrl.searchParams.set('type', selectedTypeInput.val());
            templateSelectionUrl.searchParams.set('title', sequenceTitleInput.val().trim());

            window.location.href = templateSelectionUrl.toString();
        });
    });
</script>
@endsection

@section('content')
@php
    $campaigns = [
        [
            'name' => 'Flujo de bienvenida para docentes',
            'type' => __('Secuencias'),
            'summary' => __('7 correos en 150 días'),
            'sends' => 0,
            'opened' => null,
            'clicked' => null,
            'unsubscribed' => null,
            'status' => __('Activo'),
            'status_class' => 'bg-label-success',
            'status_text' => 'text-success',
        ],
        [
            'name' => 'Por qué tus alumnos no progresan',
            'type' => __('Difusiones'),
            'summary' => __('Programado para 07 mayo 2026 19:00'),
            'sends' => null,
            'opened' => null,
            'clicked' => null,
            'unsubscribed' => null,
            'status' => __('Programado'),
            'status_class' => 'bg-label-warning',
            'status_text' => 'text-warning',
        ],
        [
            'name' => 'Lo que aprendí de los nuevos alumnos',
            'type' => __('Difusiones'),
            'summary' => __('Enviado el 23 abril 2026 19:03'),
            'sends' => 2381,
            'opened' => '20%',
            'clicked' => '0%',
            'unsubscribed' => '0%',
            'status' => __('Enviado'),
            'status_class' => 'bg-label-info',
            'status_text' => 'text-info',
        ],
        [
            'name' => 'Errores al activar el core',
            'type' => __('Difusiones'),
            'summary' => __('Enviado el 15 abril 2026 18:03'),
            'sends' => 2399,
            'opened' => '40%',
            'clicked' => '3%',
            'unsubscribed' => '0%',
            'status' => __('Enviado'),
            'status_class' => 'bg-label-info',
            'status_text' => 'text-info',
        ],
    ];

@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Campañas de correo') }}</h4>
        <p class="text-muted">{{ __('Crea, programa y monitorea tus campañas en un solo lugar') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <a href="{{ route('campaigns.templates.select') }}" class="btn btn-label-secondary">
            <i class="ti ti-template me-1"></i>{{ __('Gestionar plantillas') }}
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
            <i class="ti ti-plus me-1"></i>{{ __('Nueva campaña de correo') }}
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 mb-4">
            <select id="campaign-type-filter" class="form-select">
                <option value="">{{ __('Tipo') }}</option>
                @foreach ($campaignTypes as $campaignType)
                    <option value="{{ $campaignType->value }}">{{ $campaignType->label() }}</option>
                @endforeach
            </select>
            <select id="campaign-status-filter" class="form-select">
                <option value="">{{ __('Estado') }}</option>
                <option value="active">{{ __('Activo') }}</option>
                <option value="scheduled">{{ __('Programado') }}</option>
                <option value="sent">{{ __('Enviado') }}</option>
                <option value="paused">{{ __('Pausado') }}</option>
            </select>
            <div class="input-group input-group-merge ms-lg-auto" style="max-width: 360px; width: 100%;">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" class="form-control" placeholder="{{ __('Buscar...') }}" />
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table border-top">
                <thead>
                    <tr>
                        <th>{{ __('Campaña') }}</th>
                        <th>{{ __('Rendimiento') }}</th>
                        <th class="text-center">{{ __('Estado') }}</th>
                        <th class="text-center">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="d-flex align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $campaign['name'] }}</div>
                                        <small class="text-muted d-block">{{ $campaign['type'] }} - {{ $campaign['summary'] }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        <small class="text-muted d-block">{{ __('Envíos') }}</small>
                                        <span class="fw-medium">{{ $campaign['sends'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Abiertos') }}</small>
                                        <span class="fw-medium">{{ $campaign['opened'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Clics') }}</small>
                                        <span class="fw-medium">{{ $campaign['clicked'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Desuscritos') }}</small>
                                        <span class="fw-medium">{{ $campaign['unsubscribed'] ?? '-' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $campaign['status_class'] }} {{ $campaign['status_text'] }}">{{ $campaign['status'] }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-3">
                                    <a class="d-inline-flex align-items-center text-body" href="{{ route('campaigns.edit', ['campaign' => \Illuminate\Support\Str::slug($campaign['name'])]) }}" aria-label="{{ __('Editar') }}">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                    <a class="d-inline-flex align-items-center text-body" href="javascript:;" aria-label="{{ __('Reporte') }}">
                                        <i class="ti ti-chart-bar"></i>
                                    </a>
                                    <a class="d-inline-flex align-items-center text-body" href="javascript:;" aria-label="{{ __('Duplicar') }}">
                                        <i class="ti ti-copy"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            <nav aria-label="Paginación de campañas">
                <ul class="pagination mb-0">
                    <li class="page-item disabled"><span class="page-link">{{ __('Atrás') }}</span></li>
                    <li class="page-item active"><span class="page-link">1</span></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                    <li class="page-item"><a class="page-link" href="#">{{ __('Siguiente') }}</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="newCampaignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="new-campaign-form">
                <input type="hidden" id="new-campaign-type" name="campaign_type" value="">

                <div id="new-campaign-step-1">
                    <div class="modal-header border-0 pb-1">
                        <div>
                            <h4 class="modal-title mb-1">{{ __('Nueva campaña de correo') }}</h4>
                            <p class="text-muted mb-0">{{ __('Selecciona el tipo de campaña que deseas crear y agrega un título.') }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="card h-100 cursor-pointer border" role="button" tabindex="0" data-campaign-option="broadcasts">
                                    <div class="card-body text-center p-4">
                                        <div class="avatar avatar-lg bg-label-primary mb-3 rounded d-flex align-items-center justify-content-center mx-auto">
                                            <i class="ti ti-mail fs-3 text-primary"></i>
                                        </div>
                                        <h4 class="mb-2">{{ __('Difusión por correo') }}</h4>
                                        <p class="text-muted mb-3">{{ __('Envía un único correo para promocionar un producto, compartir novedades o mantener el contacto.') }}</p>
                                        <div class="d-flex align-items-center justify-content-center text-body">
                                            <i class="ti ti-test-pipe me-1"></i>
                                            <span class="fw-medium">{{ __('Prueba A/B disponible') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="card h-100 cursor-pointer border" role="button" tabindex="0" data-campaign-option="sequences">
                                    <div class="card-body text-center p-4">
                                        <div class="avatar avatar-lg bg-label-success mb-3 rounded d-flex align-items-center justify-content-center mx-auto">
                                            <i class="ti ti-mail-forward fs-3 text-success"></i>
                                        </div>
                                        <h4 class="mb-2">{{ __('Secuencia de correo') }}</h4>
                                        <p class="text-muted mb-0">{{ __('Envía una serie de correos que puede activarse con una automatización.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="sequence-title-wrapper" class="mt-4 d-none">
                            <label for="sequence-title-input" class="form-label">{{ __('Título') }}</label>
                            <input id="sequence-title-input" type="text" class="form-control" name="title" placeholder="{{ __('Título de la secuencia') }}">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                        <button id="new-campaign-continue-btn" type="submit" class="btn btn-primary" disabled>{{ __('Continuar') }}</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

@endsection
