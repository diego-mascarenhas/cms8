@php
    // Get country codes for flags
    $sourceFlag = strtolower($sourceLanguage->country_code ?? '');
    $targetFlag = strtolower($targetLanguage->country_code ?? '');
@endphp

<div class="d-flex align-items-center gap-2">
    <span class="badge bg-primary">
        @if(!empty($sourceFlag))
            <span class="fi fi-{{ $sourceFlag }} me-1"></span>
        @endif
        {{ $sourceLanguage ? $sourceLanguage->name : $sourceLanguageCode }}
    </span>
    <i class="ti ti-arrow-right text-muted ti-xs"></i>
    <span class="badge bg-success">
        @if(!empty($targetFlag))
            <span class="fi fi-{{ $targetFlag }} me-1"></span>
        @endif
        {{ $targetLanguage ? $targetLanguage->name : $targetLanguageCode }}
    </span>
</div> 