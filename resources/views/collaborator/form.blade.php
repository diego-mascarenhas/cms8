@extends('layouts/layoutMaster')

@section('title', isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                <span class="text-muted fw-light">{{ __('Collaborators') }}/</span> 
                {{ isset($collaborator) ? __('Edit') : __('Create') }}
            </h4>
            <p class="text-muted">{{ isset($collaborator) ? __('Update collaborator information') : __('Add a new collaborator') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @if(isset($collaborator))
                @can('collaborator.show')
                <a href="{{ route('collaborator.show', $collaborator->id) }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-eye me-1"></i>{{ __('View Collaborator') }}
                </a>
                @endcan
            @endif
        </div>
    </div>

    <form action="{{ isset($collaborator) ? route('collaborator.update', $collaborator) : route('collaborator.store') }}" method="POST">
        @csrf
        @if(isset($collaborator))
            @method('PUT')
        @endif

        <!-- Basic Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ isset($collaborator) ? __('Edit Collaborator') : __('New Collaborator') }}</h5>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">{{ __('Name') }}</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $collaborator->name ?? '') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="surname" class="form-label">{{ __('Surname') }}</label>
                        <input type="text" class="form-control @error('surname') is-invalid @enderror" id="surname" name="surname" value="{{ old('surname', $collaborator->surname ?? '') }}">
                        @error('surname')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $collaborator->email ?? '') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">{{ __('Phone') }}</label>
                        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $collaborator->phone ?? '') }}">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Services') }}</h5>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <x-fare-select 
                            id="collaborator_fare_ids" 
                            label="{{ __('Services offered') }}"
                            :selected="$collaborator->fares ?? [] ? $collaborator->fares->pluck('id')->toArray() : []"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Language Pairs Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Language Pairs') }}</h5>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <x-variant-language-select 
                            name="source_language" 
                            id="source_language" 
                            label="{{ __('Source language') }}" 
                            :required="false"
                        />
                    </div>
                    <div class="col-md-6">
                        <x-variant-language-select 
                            name="target_language" 
                            id="target_language" 
                            label="{{ __('Target language') }}" 
                            :required="false"
                        />
                    </div>
                </div>
                
                <div class="language-pairs-container mb-4">
                    <div class="row g-3 language-pairs-list">
                        <!-- Language pairs will be loaded here -->
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-primary" id="add_language_pair">
                        <i class="ti ti-plus me-1"></i>{{ __('Add language pair') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ isset($collaborator) ? __('Update') : __('Create') }}</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('collaborator-list') }}'">{{ __('Cancel') }}</button>
            </div>
        </div>
    </form>
@endsection

@section('page-script')
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#enterprise_id, #responsible_id').select2();
            
            // Initialize Select2 for language selectors with custom template
            $('.select2').select2({
                templateResult: formatLanguageOption,
                templateSelection: formatLanguageOption
            });
            
            // Add language pair button handler
            $('#add_language_pair').on('click', function() {
                const sourceLanguage = $('#source_language').select2('data')[0];
                const targetLanguage = $('#target_language').select2('data')[0];
                
                // Validate source language
                if (!sourceLanguage || !$('#source_language').val()) {
                    Swal.fire({
                        title: '{{ __("Validation Error") }}',
                        text: '{{ __("Source language is required") }}',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                    return;
                }
                
                // Validate target language
                if (!targetLanguage || !$('#target_language').val()) {
                    Swal.fire({
                        title: '{{ __("Validation Error") }}',
                        text: '{{ __("Target language is required") }}',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                    return;
                }
                
                // Get text and values
                const sourceText = sourceLanguage.text;
                const targetText = targetLanguage.text;
                const sourceValue = $('#source_language').val();
                const targetValue = $('#target_language').val();
                const sourceFlag = $('#source_language option:selected').data('flag');
                const targetFlag = $('#target_language option:selected').data('flag');
                
                // Check if source and target are the same
                if (sourceValue === targetValue) {
                    Swal.fire({
                        title: 'Error',
                        text: '{{ __("Source and target languages cannot be the same") }}',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                    return;
                }
                
                // Check if this pair already exists
                const pairExists = checkIfPairExists(sourceValue, targetValue);
                if (pairExists) {
                    // Show error or alert
                    Swal.fire({
                        title: 'Error',
                        text: '{{ __("This language pair already exists") }}',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                    return;
                }
                
                // Get flag codes safely
                const sourceFlagCode = sourceFlag || (sourceValue.split('-').length > 1 ? 
                    sourceValue.split('-')[1].toLowerCase() : 
                    sourceValue.split('-')[0].toLowerCase());
                
                const targetFlagCode = targetFlag || (targetValue.split('-').length > 1 ? 
                    targetValue.split('-')[1].toLowerCase() : 
                    targetValue.split('-')[0].toLowerCase());
                
                // Create new pair badge
                const newPair = $(`
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded d-flex align-items-center p-3" style="background-color: #f8f8f8; height: 100%;">
                            <div class="d-flex flex-column flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fi fi-${sourceFlagCode} me-2"></i>
                                    <span class="fw-medium">${sourceText}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-arrow-right me-2 text-muted"></i>
                                    <i class="fi fi-${targetFlagCode} me-2"></i>
                                    <span class="fw-medium">${targetText}</span>
                                    ${targetValue === 'es-ES' ? '<span class="badge bg-label-success ms-2">{{ __("Native") }}</span>' : ''}
                                </div>
                            </div>
                            <a href="javascript:void(0)" class="text-danger ms-auto remove-pair">
                                <i class="ti ti-x"></i>
                            </a>
                            <input type="hidden" name="language_pairs[]" value="${sourceValue}|${targetValue}">
                            <input type="hidden" name="is_native[]" value="${targetValue === 'es-ES' ? '1' : '0'}">
                        </div>
                    </div>
                `);
                
                // Add to container
                $('.language-pairs-list').append(newPair);
                
                // Reset selections
                $('#source_language').val(null).trigger('change');
                $('#target_language').val(null).trigger('change');
            });
            
            // Remove language pair
            $(document).on('click', '.remove-pair', function() {
                $(this).closest('.col-md-6').remove();
            });
            
            // Format language options with flags
            function formatLanguageOption(option) {
                if (!option.id) {
                    return option.text;
                }
                
                const flag = $(option.element).data('flag');
                return $(`<span><i class="fi fi-${flag} me-2"></i>${option.text}</span>`);
            }
            
            // Check if language pair already exists
            function checkIfPairExists(source, target) {
                let exists = false;
                $('input[name="language_pairs[]"]').each(function() {
                    const value = $(this).val();
                    if (!value) return;
                    
                    const parts = value.split('|');
                    if (parts.length !== 2) return;
                    
                    const [existingSource, existingTarget] = parts;
                    
                    if (existingSource === source && existingTarget === target) {
                        exists = true;
                        return false; // break the loop
                    }
                });
                
                return exists;
            }
            
            // Clear example pairs on load
            $('.language-pairs-list').empty();
            
            // If editing, load existing pairs
            // This would be populated from the backend with actual data
            @if(isset($collaborator) && isset($collaborator->languagePairs) && count($collaborator->languagePairs) > 0)
                @foreach($collaborator->languagePairs as $index => $pair)
                    // Add each pair from the database
                    try {
                        const pairSource = "{{ $pair['source_language'] }}";
                        const pairTarget = "{{ $pair['target_language'] }}";
                        const pairSourceText = "{{ $pair['source_language_text'] }}";
                        const pairTargetText = "{{ $pair['target_language_text'] }}";
                        const isNative = {{ $pair['is_native'] ? 'true' : 'false' }};
                        
                        // Extract flag codes safely
                        const sourceParts = pairSource.split('-');
                        const targetParts = pairTarget.split('-');
                        const sourceFlag = sourceParts.length > 1 ? sourceParts[1].toLowerCase() : sourceParts[0].toLowerCase();
                        const targetFlag = targetParts.length > 1 ? targetParts[1].toLowerCase() : targetParts[0].toLowerCase();
                        
                        // Create new pair badge
                        const savedPair = $(`
                            <div class="col-md-6 col-lg-4">
                                <div class="border rounded d-flex align-items-center p-3" style="background-color: #f8f8f8; height: 100%;">
                                    <div class="d-flex flex-column flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fi fi-${sourceFlag} me-2"></i>
                                            <span class="fw-medium">${pairSourceText}</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-arrow-right me-2 text-muted"></i>
                                            <i class="fi fi-${targetFlag} me-2"></i>
                                            <span class="fw-medium">${pairTargetText}</span>
                                            ${isNative ? '<span class="badge bg-label-success ms-2">{{ __("Native") }}</span>' : ''}
                                        </div>
                                    </div>
                                    <a href="javascript:void(0)" class="text-danger ms-auto remove-pair">
                                        <i class="ti ti-x"></i>
                                    </a>
                                    <input type="hidden" name="language_pairs[]" value="${pairSource}|${pairTarget}">
                                    <input type="hidden" name="is_native[]" value="${isNative ? '1' : '0'}">
                                </div>
                            </div>
                        `);
                        
                        $('.language-pairs-list').append(savedPair);
                    } catch (e) {
                        console.error('Error adding language pair:', e);
                    }
                @endforeach
            @endif

            // Form submit handler for validation
            $('form').on('submit', function(e) {
                // Validate language pairs if any exist
                const languagePairs = [];
                $('input[name="language_pairs[]"]').each(function() {
                    const value = $(this).val();
                    if (value) {
                        const parts = value.split('|');
                        if (parts.length === 2 && parts[0] && parts[1]) {
                            languagePairs.push(value);
                        }
                    }
                });
                
                // Check for invalid pairs only if there are any pairs
                if ($('input[name="language_pairs[]"]').length > 0) {
                    let hasInvalidPairs = false;
                    $('input[name="language_pairs[]"]').each(function() {
                        const value = $(this).val();
                        if (value) {
                            const parts = value.split('|');
                            if (parts.length !== 2 || !parts[0] || !parts[1]) {
                                hasInvalidPairs = true;
                                return false; // break the loop
                            }
                        }
                    });
                    
                    if (hasInvalidPairs) {
                        // Show error message
                        e.preventDefault();
                        Swal.fire({
                            title: '{{ __("Validation Error") }}',
                            text: '{{ __("Some language pairs are invalid") }}',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                        return false;
                    }
                }
                
                // If all checks pass, allow the form to submit
                return true;
            });
        });
    </script>
@endsection 