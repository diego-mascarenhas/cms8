@props([
    'name',
    'id',
    'label',
    'value' => '',
    'required' => false,
    'placeholder' => 'Select a timezone',
    'class' => '',
    'disabled' => false,
    'searchable' => true,
    'clearable' => true
])

@php
    // Get current timezone offset for user's location
    $userTimezone = $value ?: 'UTC';
    $currentTime = now();

    // Define timezone groups with better organization
    $timezoneGroups = [
        'Popular' => [
            'UTC' => 'UTC (Coordinated Universal Time)',
            'Europe/London' => 'London (GMT)',
            'Europe/Paris' => 'Paris (CET)',
            'America/New_York' => 'New York (EST)',
            'America/Los_Angeles' => 'Los Angeles (PST)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Australia/Sydney' => 'Sydney (AEDT)',
        ],
        'Europe' => [
            'Europe/Madrid' => 'Madrid (CET)',
            'Europe/Berlin' => 'Berlin (CET)',
            'Europe/Rome' => 'Rome (CET)',
            'Europe/Amsterdam' => 'Amsterdam (CET)',
            'Europe/Brussels' => 'Brussels (CET)',
            'Europe/Vienna' => 'Vienna (CET)',
            'Europe/Zurich' => 'Zurich (CET)',
            'Europe/Stockholm' => 'Stockholm (CET)',
            'Europe/Oslo' => 'Oslo (CET)',
            'Europe/Copenhagen' => 'Copenhagen (CET)',
            'Europe/Helsinki' => 'Helsinki (EET)',
            'Europe/Warsaw' => 'Warsaw (CET)',
            'Europe/Prague' => 'Prague (CET)',
            'Europe/Budapest' => 'Budapest (CET)',
            'Europe/Athens' => 'Athens (EET)',
            'Europe/Istanbul' => 'Istanbul (TRT)',
            'Europe/Moscow' => 'Moscow (MSK)',
        ],
        'Americas' => [
            'America/Toronto' => 'Toronto (EST)',
            'America/Vancouver' => 'Vancouver (PST)',
            'America/Mexico_City' => 'Ciudad de México (CST)',
            'America/Bogota' => 'Bogotá (COT)',
            'America/Lima' => 'Lima (PET)',
            'America/Caracas' => 'Caracas (VET)',
            'America/La_Paz' => 'La Paz (BOT)',
            'America/Santiago' => 'Santiago (CLT)',
            'America/Buenos_Aires' => 'Buenos Aires (ART)',
            'America/Montevideo' => 'Montevideo (UYT)',
            'America/Asuncion' => 'Asunción (PYT)',
            'America/Sao_Paulo' => 'São Paulo (BRT)',
            'America/Rio_de_Janeiro' => 'Rio de Janeiro (BRT)',
            'America/Guayaquil' => 'Guayaquil (ECT)',
            'America/Guatemala' => 'Guatemala (CST)',
            'America/Havana' => 'La Habana (CST)',
            'America/Santo_Domingo' => 'Santo Domingo (AST)',
            'America/Tegucigalpa' => 'Tegucigalpa (CST)',
            'America/El_Salvador' => 'San Salvador (CST)',
            'America/Managua' => 'Managua (CST)',
            'America/Costa_Rica' => 'San José (CST)',
            'America/Panama' => 'Panamá (EST)',
        ],
        'Asia' => [
            'Asia/Seoul' => 'Seoul (KST)',
            'Asia/Shanghai' => 'Shanghai (CST)',
            'Asia/Beijing' => 'Beijing (CST)',
            'Asia/Hong_Kong' => 'Hong Kong (HKT)',
            'Asia/Singapore' => 'Singapore (SGT)',
            'Asia/Bangkok' => 'Bangkok (ICT)',
            'Asia/Ho_Chi_Minh' => 'Ho Chi Minh (ICT)',
            'Asia/Jakarta' => 'Jakarta (WIB)',
            'Asia/Manila' => 'Manila (PHT)',
            'Asia/Kuala_Lumpur' => 'Kuala Lumpur (MYT)',
            'Asia/Dubai' => 'Dubai (GST)',
            'Asia/Qatar' => 'Qatar (AST)',
            'Asia/Kuwait' => 'Kuwait (AST)',
            'Asia/Riyadh' => 'Riyadh (AST)',
            'Asia/Tehran' => 'Tehran (IRST)',
            'Asia/Kolkata' => 'Kolkata (IST)',
            'Asia/Dhaka' => 'Dhaka (BST)',
        ],
        'Africa' => [
            'Africa/Cairo' => 'Cairo (EET)',
            'Africa/Johannesburg' => 'Johannesburg (SAST)',
            'Africa/Lagos' => 'Lagos (WAT)',
            'Africa/Casablanca' => 'Casablanca (WET)',
            'Africa/Algiers' => 'Algiers (CET)',
            'Africa/Tunis' => 'Tunis (CET)',
            'Africa/Tripoli' => 'Tripoli (EET)',
            'Africa/Nairobi' => 'Nairobi (EAT)',
            'Africa/Addis_Ababa' => 'Addis Ababa (EAT)',
            'Africa/Dar_es_Salaam' => 'Dar es Salaam (EAT)',
            'Africa/Malabo' => 'Malabo (WAT)',
        ],
        'Oceania' => [
            'Australia/Melbourne' => 'Melbourne (AEDT)',
            'Australia/Brisbane' => 'Brisbane (AEST)',
            'Australia/Perth' => 'Perth (AWST)',
            'Australia/Adelaide' => 'Adelaide (ACDT)',
            'Pacific/Auckland' => 'Auckland (NZDT)',
            'Pacific/Fiji' => 'Fiji (FJT)',
            'Pacific/Guam' => 'Guam (ChST)',
            'Pacific/Honolulu' => 'Honolulu (HST)',
        ]
    ];
@endphp

<div class="mb-3 {{ $class }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        class="form-select @error($name) is-invalid @enderror"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        data-searchable="{{ $searchable ? 'true' : 'false' }}"
        data-clearable="{{ $clearable ? 'true' : 'false' }}"
    >
        <option value="">{{ $placeholder }}</option>

        @foreach($timezoneGroups as $groupName => $timezones)
            <optgroup label="{{ $groupName }}">
                @foreach($timezones as $timezone => $displayName)
                    @php
                        try {
                            $timezoneOffset = $currentTime->setTimezone($timezone)->format('P');
                            $currentTimeInZone = $currentTime->setTimezone($timezone)->format('H:i');
                        } catch (Exception $e) {
                            $timezoneOffset = '';
                            $currentTimeInZone = '';
                        }
                    @endphp
                    <option
                        value="{{ $timezone }}"
                        {{ $value == $timezone ? 'selected' : '' }}
                        data-offset="{{ $timezoneOffset }}"
                        data-current-time="{{ $currentTimeInZone }}"
                    >
                        {{ $displayName }}
                        @if($timezoneOffset)
                            ({{ $timezoneOffset }}, {{ $currentTimeInZone }})
                        @endif
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if($value)
        <small class="form-text text-muted">
            <i class="ti ti-clock me-1"></i>
            Current time in selected timezone:
            <span id="{{ $id }}-current-time">
                @php
                    try {
                        echo $currentTime->setTimezone($value)->format('l, F j, Y g:i A T');
                    } catch (Exception $e) {
                        echo 'Unable to display time';
                    }
                @endphp
            </span>
        </small>
    @endif
</div>

{{-- Component is designed to work with existing Select2 resources in the parent template --}}
{{-- No additional CSS or JS needed - uses Bootstrap and Select2 classes already available --}}
