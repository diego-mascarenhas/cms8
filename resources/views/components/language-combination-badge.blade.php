@php
    // Get country codes for flags
    $sourceFlag = strtolower($sourceLanguage->country_code ?? '');
    $targetFlag = strtolower($targetLanguage->country_code ?? '');
@endphp

<div class="btn-group me-2">
    <button type="button" class="btn btn-light px-4 py-2 waves-effect border" 
            data-source="{{ $sourceLanguageCode }}" 
            data-target="{{ $targetLanguageCode }}"
            disabled>
        @if(!empty($sourceFlag))
            <span class="fi fi-{{ $sourceFlag }} me-2" style="font-size: 1.1em;"></span>
        @endif
        <span class="fw-medium">{{ $sourceLanguage ? $sourceLanguage->name : $sourceLanguageCode }}</span>
        <span class="mx-2"><i class="ti ti-arrow-right text-muted" style="font-size: 1.1em;"></i></span>
        @if(!empty($targetFlag))
            <span class="fi fi-{{ $targetFlag }} me-2" style="font-size: 1.1em;"></span>
        @endif
        <span class="fw-medium">{{ $targetLanguage ? $targetLanguage->name : $targetLanguageCode }}</span>
    </button>
</div> 