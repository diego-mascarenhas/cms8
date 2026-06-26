@forelse($collaborators as $index => $collaborator)
    @php
        // Build language combinations string for filtering
        $languageCombinations = [];
        foreach($collaborator->languageVariants as $variant) {
            $languageCombinations[] = $variant->source_language_code . '-' . $variant->target_language_code;
        }
        $languageString = implode(',', $languageCombinations);

        // Build services string for filtering (using unique fare IDs to match the selector)
        $serviceIds = [];
        foreach($collaborator->fares->unique('id') as $fare) {
            $serviceIds[] = $fare->id;
        }
        $servicesString = implode(',', $serviceIds);

        // Get the valoration for display
        $valorationIcon = 'ti-star-filled text-warning';
        $valorationText = 'Top';
        if ($collaborator->valoration) {
            switch($collaborator->valoration->name) {
                case 'Top':
                    $valorationIcon = 'ti-star-filled text-warning';
                    $valorationText = 'Top';
                    break;
                case 'Validada':
                    $valorationIcon = 'ti-check text-success';
                    $valorationText = 'Validada';
                    break;
                case 'Interesante':
                    $valorationIcon = 'ti-clock text-info';
                    $valorationText = 'Interesante';
                    break;
                case 'Ojo':
                    $valorationIcon = 'ti-eye text-warning';
                    $valorationText = 'Ojo';
                    break;
                case 'Lista negra':
                    $valorationIcon = 'ti-x text-danger';
                    $valorationText = 'Lista negra';
                    break;
            }
        } else {
            // No valoration assigned
            $valorationIcon = 'ti-minus text-muted';
            $valorationText = 'Sin valoración';
        }

        // Get last project date for display
        $lastProjectText = 'Sin proyectos';
        if ($collaborator->lastProject) {
            $lastProject = $collaborator->lastProject;

            // Check if the last project is the current one
            if (isset($project) && $lastProject->id == $project->id) {
                $lastProjectText = 'Este mismo';
            } else {
                // Format the date
                $lastProjectText = $lastProject->created_at->format('d/m/Y');
            }
        }
    @endphp

    <div class="col-md-4 mb-3 collaborator-card"
         data-languages="{{ $languageString }}"
         data-services="{{ $servicesString }}">
        <div class="card position-relative">
            {{-- Eye icon positioned absolutely at top-right --}}
            <div class="position-absolute" style="top: 8px; right: 8px; z-index: 10;">
                <a href="{{ route('collaborator.show', $collaborator->id) }}" class="text-body" title="{{ __('View collaborator details') }}">
                    <i class="ti ti-eye ti-sm"></i>
                </a>
            </div>

            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-md me-3">
                        <span class="avatar-initial rounded-circle bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][($index % 5)] }}">{{ strtoupper(substr($collaborator->name, 0, 2)) }}</span>
                    </div>
                    <div>
                        <h6 class="mb-0">{{ $collaborator->name }}</h6>
                        <small class="text-muted">{{ $lastProjectText }}</small>
                        <div class="d-flex align-items-center mt-1">
                            <i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
                            <small class="text-muted">{{ $valorationText }}</small>
                        </div>

                            {{-- Show availability info when time filters are applied --}}
                            @if(isset($filterDays) && isset($filterDeliveryDate) && $filterDays && $filterDeliveryDate)
                                @php
                                    // Calculate available days for this collaborator
                                    $startDate = now()->format('Y-m-d');
                                    $endDate = null;

                                    // Parse delivery date
                                    switch($filterDeliveryDate) {
                                        case 'today':
                                            $endDate = now()->format('Y-m-d');
                                            break;
                                        case '1_week':
                                            $endDate = now()->addWeek()->format('Y-m-d');
                                            break;
                                        case '15_days':
                                            $endDate = now()->addDays(15)->format('Y-m-d');
                                            break;
                                        case '1_month':
                                            $endDate = now()->addMonth()->format('Y-m-d');
                                            break;
                                        case '3_months':
                                            $endDate = now()->addMonths(3)->format('Y-m-d');
                                            break;
                                        default:
                                            try {
                                                // Handle Spanish date format (d/m/Y) or ISO format (Y-m-d)
                                                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $filterDeliveryDate)) {
                                                    // Spanish format: d/m/Y
                                                    $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', $filterDeliveryDate)->format('Y-m-d');
                                                } else {
                                                    // ISO format: Y-m-d
                                                    $endDate = \Carbon\Carbon::parse($filterDeliveryDate)->format('Y-m-d');
                                                }
                                            } catch (\Exception $e) {
                                                $endDate = null;
                                            }
                                    }

                                    $availableDays = 0;
                                    if ($endDate) {
                                        $weeklyAvailability = $collaborator->weeklyAvailability;

                                        // If no weekly availability is set, assume all days are available
                                        if (!$weeklyAvailability) {
                                            $weeklyPattern = [
                                                'monday' => true,
                                                'tuesday' => true,
                                                'wednesday' => true,
                                                'thursday' => true,
                                                'friday' => true,
                                                'saturday' => true,
                                                'sunday' => true,
                                            ];
                                        } else {
                                            $weeklyPattern = [
                                                'monday' => $weeklyAvailability->monday,
                                                'tuesday' => $weeklyAvailability->tuesday,
                                                'wednesday' => $weeklyAvailability->wednesday,
                                                'thursday' => $weeklyAvailability->thursday,
                                                'friday' => $weeklyAvailability->friday,
                                                'saturday' => $weeklyAvailability->saturday,
                                                'sunday' => $weeklyAvailability->sunday,
                                            ];
                                        }

                                        // Get specific absence dates
                                        $absenceDates = $collaborator->absences()
                                            ->whereBetween('absence_date', [$startDate, $endDate])
                                            ->get()
                                            ->pluck('absence_date')
                                            ->map(function ($date) {
                                                return $date->format('Y-m-d');
                                            })
                                            ->toArray();

                                        $currentDate = \Carbon\Carbon::parse($startDate);
                                        $endDateCarbon = \Carbon\Carbon::parse($endDate);

                                        while ($currentDate->lte($endDateCarbon)) {
                                            $dayOfWeek = strtolower($currentDate->format('l'));
                                            $dateString = $currentDate->format('Y-m-d');

                                            // Check if this day is available according to weekly pattern
                                            $isWeeklyAvailable = $weeklyPattern[$dayOfWeek] ?? false;

                                            // Check if this specific date is not in absences
                                            $isNotAbsent = !in_array($dateString, $absenceDates);

                                            // Day is available if both conditions are met
                                            if ($isWeeklyAvailable && $isNotAbsent) {
                                                $availableDays++;
                                            }

                                            $currentDate->addDay();
                                        }
                                    }

                                    $requiredDays = (int) $filterDays;
                                    $isAvailable = $availableDays >= $requiredDays;
                                @endphp

                                <div class="d-flex align-items-center mt-1">
                                    <i class="ti ti-calendar ti-xs me-1 {{ $isAvailable ? 'text-success' : 'text-danger' }}"></i>
                                    <small class="{{ $isAvailable ? 'text-success' : 'text-danger' }}">
                                        {{ $availableDays }} días disponibles
                                        @if(!$isAvailable)
                                            <span class="text-muted">(necesita {{ $requiredDays }})</span>
                                        @endif
                                    </small>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- Show fare info only when service AND both languages are selected --}}
                @if(isset($selectedService) && $selectedService &&
                    isset($selectedSourceLanguage) && $selectedSourceLanguage &&
                    isset($selectedTargetLanguage) && $selectedTargetLanguage)
                    @php
                        // Find the specific fare that matches the selected service and language combination
                        $selectedFare = null;
                        foreach($collaborator->fares->where('id', $selectedService) as $fare) {
                            if (($fare->pivot->source_language_code == $selectedSourceLanguage || $fare->pivot->source_language_code == 'N/A') &&
                                ($fare->pivot->target_language_code == $selectedTargetLanguage || $fare->pivot->target_language_code == 'N/A')) {
                                $selectedFare = $fare;
                                break;
                            }
                        }
                    @endphp
                    @if($selectedFare)
                        @php
                            $price = $selectedFare->pivot->price ?? 'N/A';
                            $currency = $selectedFare->pivot->currency_code ?? 'EUR';
                            $sourceLanguage = $selectedFare->pivot->source_language_code ?? 'N/A';
                            $targetLanguage = $selectedFare->pivot->target_language_code ?? 'N/A';
                            $unit = $selectedFare->pivot->unit_id ?? '';

                            // Get unit name from database
                            $unitName = '';
                            if($unit) {
                                $unitModel = \App\Models\Unit::find($unit);
                                if($unitModel) {
                                    $unitName = '/' . $unitModel->type;
                                }
                            }

                            // Get currency symbol from database
                            $currencySymbol = '€'; // Default fallback
                            $currencyModel = \App\Models\Currency::where('code', $currency)->first();
                            if($currencyModel) {
                                $currencySymbol = $currencyModel->symbol;
                            }
                        @endphp
                        <div class="mt-2 px-3 py-2 d-flex align-items-center justify-content-between" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;">
                            <div class="form-check mb-0">
                                <input class="form-check-input collaborator-checkbox" type="checkbox"
                                       name="collaborator_ids[]" value="{{ $collaborator->id }}"
                                       data-collaborator-id="{{ $collaborator->id }}">
                            </div>
                            <div class="text-end">
                                @can('access-billing-modules')
                                @if($price !== 'N/A')
                                    <strong class="text-success">{{ number_format($price, 2) }}{{ $currencySymbol }} {{ $unitName }}</strong>
                                @else
                                    <span class="text-muted">{{ __('Por consultar') }}</span>
                                @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endcan
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Show detailed fares only when service OR languages are not selected --}}
                @if(!isset($selectedService) || !$selectedService ||
                    !isset($selectedSourceLanguage) || !$selectedSourceLanguage ||
                    !isset($selectedTargetLanguage) || !$selectedTargetLanguage)

                    {{-- Price box with checkbox when no specific fare is selected --}}
                    <div class="mt-2 px-3 py-2 d-flex align-items-center justify-content-between" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;">
                        <div class="form-check mb-0">
                            <input class="form-check-input collaborator-checkbox" type="checkbox"
                                   name="collaborator_ids[]" value="{{ $collaborator->id }}"
                                   data-collaborator-id="{{ $collaborator->id }}">
                        </div>
                        <div class="text-end">
                            <span class="text-muted">{{ __('Selecciona servicio e idiomas') }}</span>
                        </div>
                    </div>

                    <!-- Collaborator fares (initially hidden) -->
                    <div class="collapse mt-3" id="fares-{{ $collaborator->id }}">
                        <div class="card bg-light">
                            <div class="card-body p-2">
                                <h6 class="card-title mb-2 text-muted">{{ __('Tarifas del colaborador') }}</h6>

                                @if($collaborator->fares && $collaborator->fares->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless mb-0">
                                            <thead>
                                                <tr class="text-muted">
                                                    <th style="font-size: 0.75rem;">{{ __('Servicio') }}</th>
                                                    <th style="font-size: 0.75rem;">{{ __('Idiomas') }}</th>
                                                    @can('access-billing-modules')
                                                    <th style="font-size: 0.75rem;" class="text-end">{{ __('Tarifa') }}</th>
                                                    @endcan
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($collaborator->fares as $fare)
                                                    @php
                                                        // Get language names
                                                        $sourceLanguage = $fare->pivot->source_language_code ?? 'N/A';
                                                        $targetLanguage = $fare->pivot->target_language_code ?? 'N/A';

                                                        // Get price and currency
                                                        $price = $fare->pivot->price ?? 'N/A';
                                                        $currency = $fare->pivot->currency_code ?? 'EUR';
                                                        $unit = $fare->pivot->unit_id ?? '';

                                                        // Get unit name from database
                                                        $unitName = '';
                                                        if($unit) {
                                                            $unitModel = \App\Models\Unit::find($unit);
                                                            if($unitModel) {
                                                                $unitName = '/' . $unitModel->type;
                                                            }
                                                        }

                                                        // Get currency symbol from database
                                                        $currencySymbol = '€'; // Default fallback
                                                        $currencyModel = \App\Models\Currency::where('code', $currency)->first();
                                                        if($currencyModel) {
                                                            $currencySymbol = $currencyModel->symbol;
                                                        }
                                                    @endphp
                                                    <tr style="font-size: 0.8rem;" class="fare-row"
                                                        data-service-id="{{ $fare->id }}"
                                                        data-source-lang="{{ $sourceLanguage }}"
                                                        data-target-lang="{{ $targetLanguage }}">
                                                        <td>
                                                            <strong>{{ $fare->name }}</strong>
                                                            @if($fare->type)
                                                                <br><small class="text-muted">{{ $fare->type->name }}</small>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($sourceLanguage !== 'N/A' && $targetLanguage !== 'N/A')
                                                                <span class="badge bg-label-info">{{ $sourceLanguage }} → {{ $targetLanguage }}</span>
                                                            @else
                                                                <span class="text-muted">{{ __('Cualquier idioma') }}</span>
                                                            @endif
                                                        </td>
                                                        @can('access-billing-modules')
                                                        <td class="text-end">
                                                            @if($price !== 'N/A')
                                                                <strong class="text-success">{{ number_format($price, 2) }}{{ $currencySymbol }} {{ $unitName }}</strong>
                                                            @else
                                                                <span class="text-muted">{{ __('Por consultar') }}</span>
                                                            @endif
                                                        </td>
                                                        @endcan
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0" style="font-size: 0.8rem;">{{ __('No hay tarifas registradas') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-2"></i>
            @if(request()->has('source_language') || request()->has('target_language') || request()->has('servicio'))
                No hay colaboradores disponibles con los filtros seleccionados.
            @else
                Selecciona una combinación de idiomas o un servicio para ver colaboradores disponibles.
            @endif
        </div>
    </div>
@endforelse
