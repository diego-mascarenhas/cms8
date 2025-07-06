@php
    // Get country codes for flags
    $sourceFlag = strtolower($sourceLanguage->country_code ?? '');
    $targetFlag = strtolower($targetLanguage->country_code ?? '');
@endphp

<div class="btn-group me-2">
    <button type="button" class="btn btn-outline-primary px-3 waves-effect active" 
            data-source="{{ $sourceLanguageCode }}" 
            data-target="{{ $targetLanguageCode }}"
            disabled>
        @if(!empty($sourceFlag))
            <span class="fi fi-{{ $sourceFlag }} me-1"></span>
        @endif
        {{ $sourceLanguage ? $sourceLanguage->name : $sourceLanguageCode }}
        <span class="mx-1"><i class="ti ti-arrow-right text-muted"></i></span>
        @if(!empty($targetFlag))
            <span class="fi fi-{{ $targetFlag }} me-1"></span>
        @endif
        {{ $targetLanguage ? $targetLanguage->name : $targetLanguageCode }}
    </button>
</div> 