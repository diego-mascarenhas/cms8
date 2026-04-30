@extends('layouts/layoutMaster')

@section('title', __('Seleccionar plantilla'))

@section('page-script')
<script>
    $(function ()
    {
        const templateCards = $('[data-template-card]');
        const selectedTemplateInput = $('#selected-template-id');

        templateCards.on('click', function ()
        {
            const templateId = $(this).data('template-card');
            selectedTemplateInput.val(templateId);
            templateCards.removeClass('border-primary border-2');
            $(this).addClass('border-primary border-2');
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="mb-1">{{ __('Selecciona una plantilla') }}</h4>
        <p class="text-muted mb-0">{{ __('Tipo seleccionado:') }} <span class="fw-semibold">{{ $selectedTypeLabel }}</span></p>
        @if ($selectedTitle !== '')
            <p class="text-muted mb-0">{{ __('Título:') }} <span class="fw-semibold">{{ $selectedTitle }}</span></p>
        @endif
    </div>
    <a
        href="{{ route('campaigns.classic-editor', ['type' => $selectedType, 'title' => $selectedTitle]) }}"
        class="btn btn-primary"
    >
        {{ __('Usar el editor clásico') }}
    </a>
</div>

<input id="selected-template-id" type="hidden" value="">

<h5 class="mb-3">{{ __('Plantillas personalizadas guardadas') }}</h5>
<div class="row g-3 mb-4">
    @foreach ($customTemplates as $template)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 cursor-pointer border" data-template-card="{{ $template['id'] }}">
                <img src="{{ $template['preview'] }}" alt="{{ $template['name'] }}" class="card-img-top">
                <div class="card-body">
                    <h6 class="mb-1">{{ $template['name'] }}</h6>
                    <p class="text-muted mb-0">{{ $template['description'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<h5 class="mb-3">{{ __('Plantillas Kajabi') }}</h5>
<div class="row g-3 mb-4">
    @foreach ($kajabiTemplates as $template)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 cursor-pointer border" data-template-card="{{ $template['id'] }}">
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
            href="{{ route('campaigns.classic-editor', ['type' => $selectedType, 'title' => $selectedTitle]) }}"
            class="btn btn-label-secondary"
        >
            {{ __('Usar el editor clásico') }}
        </a>
    </div>
</div>

@endsection
