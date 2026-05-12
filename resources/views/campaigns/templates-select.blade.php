@extends('layouts/layoutMaster')

@section('title', __('Seleccionar plantilla'))

@section('page-script')
<script>
    $(function ()
    {
        const templateCards = $('[data-template-card]');
        const selectedTemplateInput = $('#selected-template-id');
        const templatePreviewModal = $('#templatePreviewModal');
        const modalTemplateImage = $('#modal-template-image');
        const modalTemplateName = $('#modal-template-name');
        const modalTemplateDescription = $('#modal-template-description');
        const modalTemplateIdInput = $('#modal-template-id');
        const templateCtaView = $('#template-cta-view');
        const templateFormView = $('#template-form-view');
        const modalTemplateTitleInput = $('#modal-template-title');
        const modalCreateButton = $('#modal-create-btn');

        const resetModalForm = function ()
        {
            templateCtaView.removeClass('d-none');
            templateFormView.addClass('d-none');
            modalTemplateTitleInput.val('');
            modalCreateButton.prop('disabled', true);
        };

        templateCards.on('click', function ()
        {
            const templateId = $(this).data('template-card');
            const templateName = $(this).data('template-name');
            const templateDescription = $(this).data('template-description');
            const templatePreview = $(this).data('template-preview');
            const templateFullPreview = $(this).data('template-full-preview') || templatePreview;

            selectedTemplateInput.val(templateId);
            templateCards.removeClass('border-primary border-2');
            $(this).addClass('border-primary border-2');

            modalTemplateIdInput.val(templateId);
            modalTemplateName.text(templateName);
            modalTemplateDescription.text(templateDescription);
            modalTemplateImage.attr('src', templateFullPreview).attr('alt', templateName);
            resetModalForm();
            templatePreviewModal.modal('show');
        });

        $('#template-get-started-btn').on('click', function ()
        {
            templateCtaView.addClass('d-none');
            templateFormView.removeClass('d-none');
            modalTemplateTitleInput.trigger('focus');
        });

        $('#template-keep-browsing-btn, #template-cancel-btn').on('click', function ()
        {
            templatePreviewModal.modal('hide');
        });

        modalTemplateTitleInput.on('input', function ()
        {
            modalCreateButton.prop('disabled', $(this).val().trim() === '');
        });

        $('#template-create-form').on('submit', function (event)
        {
            event.preventDefault();
            const templateId = modalTemplateIdInput.val();
            const emailTitle = modalTemplateTitleInput.val().trim();

            if (emailTitle === '' || templateId === '')
            {
                return;
            }

            @if (($selectedType ?? '') === 'messages')
            const redirectUrl = new URL(@json(route('message.create')));
            redirectUrl.searchParams.set('template_id', templateId);
            redirectUrl.searchParams.set('name', emailTitle);
            @else
            const redirectUrl = new URL(@json(route('campaigns.classic-editor')));
            redirectUrl.searchParams.set('type', @json($selectedType));
            redirectUrl.searchParams.set('title', emailTitle);
            redirectUrl.searchParams.set('template_id', templateId);
            @if (($selectedCampaignId ?? 0) > 0)
            redirectUrl.searchParams.set('campaign_id', @json((string) $selectedCampaignId));
            @endif
            @endif

            window.location.href = redirectUrl.toString();
        });
    });
</script>
@endsection

@section('content')
@if (session('error'))
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
@php
    $classicEditorLinkQuery = array_filter([
        'type' => $selectedType,
        'title' => $selectedTitle,
        'campaign_id' => ($selectedCampaignId ?? 0) > 0 ? $selectedCampaignId : null,
    ], fn ($value): bool => $value !== null && $value !== '');
    $isMessageTemplateFlow = ($selectedType ?? '') === 'messages';
    $classicEditorHref = $isMessageTemplateFlow
        ? route('message.create', ['legacy_form' => 1])
        : route('campaigns.classic-editor', $classicEditorLinkQuery);
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="mb-1">{{ __('Selecciona una plantilla') }}</h4>
        <p class="text-muted mb-0">{{ __('Tipo seleccionado:') }} <span class="fw-semibold">{{ $selectedTypeLabel }}</span></p>
        @if ($selectedTitle !== '')
            <p class="text-muted mb-0">{{ __('Título:') }} <span class="fw-semibold">{{ $selectedTitle }}</span></p>
        @endif
        @if (($selectedCampaignId ?? 0) > 0 && filled($contextCampaignName ?? null))
            <p class="text-muted mb-0">{{ __('Campaña:') }} <span class="fw-semibold">{{ $contextCampaignName }}</span></p>
        @endif
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-end">
        @if (($selectedCampaignId ?? 0) > 0)
            <a href="{{ route('campaigns.show', $selectedCampaignId) }}" class="btn btn-label-secondary">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Volver a la campaña') }}
            </a>
        @endif
        <a
            href="{{ $classicEditorHref }}"
            class="btn btn-primary"
        >
            {{ $isMessageTemplateFlow ? __('Formulario clásico') : __('Usar el editor clásico') }}
        </a>
    </div>
</div>

<input id="selected-template-id" type="hidden" value="">

@if (! empty($userTemplates))
    <h5 class="mb-3">{{ __('app.campaign_select_user_templates_heading') }}</h5>
    <p class="text-muted small mb-3">{{ __('app.campaign_select_user_templates_lead') }}</p>
    <div class="row g-3 mb-4">
        @foreach ($userTemplates as $template)
            <div class="col-12 col-md-6 col-xl-4">
                <div
                    class="card h-100 cursor-pointer border"
                    data-template-card="{{ $template['id'] }}"
                    data-template-name="{{ $template['name'] }}"
                    data-template-description="{{ $template['description'] }}"
                    data-template-preview="{{ $template['preview'] }}"
                    data-template-full-preview="{{ $template['full_preview'] ?? $template['preview'] }}"
                >
                    <img src="{{ $template['preview'] }}" alt="{{ $template['name'] }}" class="card-img-top">
                    <div class="card-body">
                        <h6 class="mb-1">{{ $template['name'] }}</h6>
                        <p class="text-muted mb-0">{{ $template['description'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<h5 class="mb-3">{{ __('Plantillas personalizadas') }}</h5>
<p class="text-muted small mb-3">{{ __('Plantillas con estilos propios, listas para campañas y secuencias.') }}</p>
<div class="row g-3 mb-4">
    @foreach ($customTemplates as $template)
        <div class="col-12 col-md-6 col-xl-4">
            <div
                class="card h-100 cursor-pointer border"
                data-template-card="{{ $template['id'] }}"
                data-template-name="{{ $template['name'] }}"
                data-template-description="{{ $template['description'] }}"
                data-template-preview="{{ $template['preview'] }}"
                data-template-full-preview="{{ $template['full_preview'] ?? $template['preview'] }}"
            >
                <img src="{{ $template['preview'] }}" alt="{{ $template['name'] }}" class="card-img-top">
                <div class="card-body">
                    <h6 class="mb-1">{{ $template['name'] }}</h6>
                    <p class="text-muted mb-0">{{ $template['description'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<h5 class="mb-3">{{ __('Plantillas destacadas') }}</h5>
<div class="row g-3 mb-4">
    @foreach ($kajabiTemplates as $template)
        <div class="col-12 col-md-6 col-xl-4">
            <div
                class="card h-100 cursor-pointer border"
                data-template-card="{{ $template['id'] }}"
                data-template-name="{{ $template['name'] }}"
                data-template-description="{{ $template['description'] }}"
                data-template-preview="{{ $template['preview'] }}"
                data-template-full-preview="{{ $template['full_preview'] ?? $template['preview'] }}"
            >
                <img src="{{ $template['preview'] }}" alt="{{ $template['name'] }}" class="card-img-top">
                <div class="card-body">
                    <h6 class="mb-1">{{ $template['name'] }}</h6>
                    <p class="text-muted mb-0">{{ $template['description'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="mb-2">{{ __('Prefieres lo clásico? El editor clásico sigue disponible.') }}</h4>
        <p class="text-muted mb-3">{{ __('Porque a veces solo necesitas algo simple, limpio y familiar.') }}</p>
        <a
            href="{{ $classicEditorHref }}"
            class="btn btn-label-secondary"
        >
            {{ $isMessageTemplateFlow ? __('Formulario clásico') : __('Usar el editor clásico') }}
        </a>
    </div>
</div>

<div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-lg-7">
                        <div class="border rounded overflow-hidden">
                            <img id="modal-template-image" class="img-fluid w-100" src="" alt="">
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <h4 id="modal-template-name" class="mb-2"></h4>
                        <p id="modal-template-description" class="text-muted mb-3"></p>

                        <div id="template-cta-view">
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                                <button id="template-get-started-btn" type="button" class="btn btn-primary">{{ __('Get Started') }}</button>
                                <a id="template-keep-browsing-btn" href="javascript:;" class="text-secondary">{{ __('Seguir explorando') }}</a>
                            </div>
                        </div>

                        <form id="template-create-form" class="mt-3">
                            <input id="modal-template-id" type="hidden" name="template_id" value="">
                            <div id="template-form-view" class="d-none">
                                <label class="form-label" for="modal-template-title">{{ __('Título de este correo') }}</label>
                                <input id="modal-template-title" type="text" class="form-control mb-3" required>
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <button id="modal-create-btn" type="submit" class="btn btn-primary" disabled>{{ __('Create') }}</button>
                                    <a id="template-cancel-btn" href="javascript:;" class="text-secondary">{{ __('Cancel') }}</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
