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
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $collaborator->email ?? '') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Language Pairs Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Mis pares de idiomas') }}</h5>
            <div class="card-body">
                <p class="text-muted mb-4">{{ __('Define cuáles son los idiomas en los que trabajas. Puedes seleccionar más de una combinación.') }}</p>
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="source_language" class="form-label">{{ __('Lengua origen') }}</label>
                        <select class="form-select select2" id="source_language" name="source_language">
                            <option value="es-ES" data-flag="es">Español-España</option>
                            <option value="en-US" data-flag="us">English-United States</option>
                            <option value="fr-FR" data-flag="fr">Français-France</option>
                            <option value="de-DE" data-flag="de">Deutsch-Deutschland</option>
                            <option value="it-IT" data-flag="it">Italiano-Italia</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="target_language" class="form-label">{{ __('Lengua nativa') }}</label>
                        <select class="form-select select2" id="target_language" name="target_language">
                            <option value="es-ES" data-flag="es" selected>Español-España</option>
                            <option value="en-US" data-flag="us">English-United States</option>
                            <option value="fr-FR" data-flag="fr">Français-France</option>
                            <option value="de-DE" data-flag="de">Deutsch-Deutschland</option>
                            <option value="it-IT" data-flag="it">Italiano-Italia</option>
                        </select>
                    </div>
                </div>
                
                <div class="language-pairs-container mb-4">
                    <div class="row g-3 language-pairs-list">
                        <div class="col-md-6 col-lg-4">
                            <div class="border rounded d-flex align-items-center p-2" style="border-color: #ddd; background-color: #f8f8f8; height: 100%;">
                                <span><i class="fi fi-es me-2"></i>Español-España</span>
                                <span class="mx-2">&gt;</span>
                                <span><i class="fi fi-fr me-2"></i>Français-France</span>
                                <a href="javascript:void(0)" class="text-danger ms-auto remove-pair"><i class="ti ti-x"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary rounded-pill" id="add_language_pair" style="background-color: #6366f1; border-color: #6366f1;">
                    <i class="ti ti-plus me-1"></i>{{ __('Añadir par de idiomas') }}
                </button>
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
                
                if (!sourceLanguage || !targetLanguage) {
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
                        text: 'El idioma de origen y destino no pueden ser iguales',
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
                        text: 'Este par de idiomas ya existe',
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                    return;
                }
                
                // Create new pair badge
                const newPair = $(`
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded d-flex align-items-center p-2" style="border-color: #ddd; background-color: #f8f8f8; height: 100%;">
                            <span><i class="fi fi-${sourceFlag} me-2"></i>${sourceText}</span>
                            <span class="mx-2">&gt;</span>
                            <span><i class="fi fi-${targetFlag} me-2"></i>${targetText}${targetValue === 'es-ES' ? ' <i class="ti ti-circle-check text-success ms-1" style="font-size: 0.75rem;"></i>' : ''}</span>
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
                    const [existingSource, existingTarget] = value.split('|');
                    
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
            @if(isset($collaborator) && isset($collaborator->languagePairs))
                @foreach($collaborator->languagePairs as $pair)
                    // Add each pair from the database
                    const pairSource = "{{ $pair->source_language }}";
                    const pairTarget = "{{ $pair->target_language }}";
                    const pairSourceText = "{{ $pair->source_language_text }}";
                    const pairTargetText = "{{ $pair->target_language_text }}";
                    const isNative = {{ $pair->is_native ? 'true' : 'false' }};
                    const sourceFlag = pairSource.split('-')[1].toLowerCase();
                    const targetFlag = pairTarget.split('-')[1].toLowerCase();
                    
                    const savedPair = $(`
                        <div class="col-md-6 col-lg-4">
                            <div class="border rounded d-flex align-items-center p-2" style="border-color: #ddd; background-color: #f8f8f8; height: 100%;">
                                <span><i class="fi fi-${sourceFlag} me-2"></i>${pairSourceText}</span>
                                <span class="mx-2">&gt;</span>
                                <span><i class="fi fi-${targetFlag} me-2"></i>${pairTargetText}${isNative ? ' <i class="ti ti-circle-check text-success ms-1" style="font-size: 0.75rem;"></i>' : ''}</span>
                                <a href="javascript:void(0)" class="text-danger ms-auto remove-pair">
                                    <i class="ti ti-x"></i>
                                </a>
                                <input type="hidden" name="language_pairs[]" value="${pairSource}|${pairTarget}">
                                <input type="hidden" name="is_native[]" value="${isNative ? '1' : '0'}">
                            </div>
                        </div>
                    `);
                    
                    $('.language-pairs-list').append(savedPair);
                @endforeach
            @endif
        });
    </script>
@endsection 