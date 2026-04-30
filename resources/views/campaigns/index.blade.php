@extends('layouts/layoutMaster')

@section('title', __('Campañas'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
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
                width: '100%',
            });
        }

        if (campaignStatusFilter.length && $.fn.select2)
        {
            campaignStatusFilter.select2({
                placeholder: @json(__('Estado')),
                minimumResultsForSearch: Infinity,
                width: '100%',
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
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Campañas de correo') }}</h4>
        <p class="text-muted">{{ __('Crea, programa y monitorea tus campañas en un solo lugar') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <a href="{{ route('campaigns.templates.select') }}" class="btn btn-label-secondary">
            <i class="ti ti-template me-1"></i>{{ __('Plantillas') }}
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
            <i class="ti ti-plus me-1"></i>{{ __('Nueva campaña') }}
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body border-bottom">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <select id="campaign-type-filter" class="form-select">
                    <option value="">{{ __('Tipo') }}</option>
                    @foreach ($campaignTypes as $campaignType)
                        <option value="{{ $campaignType->value }}">{{ $campaignType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <select id="campaign-status-filter" class="form-select">
                    <option value="">{{ __('Estado') }}</option>
                    <option value="active">{{ __('Activo') }}</option>
                    <option value="scheduled">{{ __('Programado') }}</option>
                    <option value="sent">{{ __('Enviado') }}</option>
                    <option value="paused">{{ __('Pausado') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input
                        id="campaign-search-filter"
                        type="search"
                        class="form-control"
                        placeholder="{{ __('Buscar...') }}"
                        autocomplete="off"
                    />
                </div>
            </div>
        </div>
    </div>
    <div class="card-datatable table-responsive">
        {{ $dataTable->table() }}
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
                            <h4 class="modal-title mb-1">{{ __('Nueva campaña') }}</h4>
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

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
