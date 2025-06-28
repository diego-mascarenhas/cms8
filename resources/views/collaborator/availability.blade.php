@extends('layouts/layoutMaster')

@section('title', 'Disponibilidad')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('page-style')
<style>
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        text-align: center;
    }
    
    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 50%;
        margin: 1px;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    
    .calendar-day:hover:not(.day-disabled) {
        background-color: #f0f0f0;
    }
    
    .calendar-header {
        font-weight: bold;
        padding: 8px 0;
        background-color: #f9f9f9;
        border-radius: 5px;
    }
    
    .day-disabled {
        opacity: 0.3;
        cursor: default;
    }
    
    .day-unavailable {
        background-color: #e7f6df;
        color: #71dd37;
        font-weight: bold;
    }
    
    .day-today {
        border: 2px solid #696cff;
    }
    
    .weekday-toggle {
        border-radius: 0;
        flex: 1;
    }
    
    .weekday-toggle.active {
        background-color: #696cff;
        color: white;
    }
    
    .weekday-toggle:not(.active) {
        background-color: #f0f0f0;
    }
</style>
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Disponibilidad</h4>
        <p class="text-muted">Gestiona tu disponibilidad para proyectos</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Períodos de no disponibilidad</h5>
                <p class="card-subtitle text-muted mt-1">Selecciona los períodos en los que no estarás disponible para aceptar proyectos.</p>
                <p class="text-muted">Esto nos ayudará a contactarte solo cuando realmente puedas colaborar.</p>
            </div>
            <div class="card-body">
                <h6 class="mb-3">Disponibilidad por día de la semana</h6>
                <div class="d-flex flex-wrap mb-4">
                    <div class="btn-group w-100 mb-3">
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->monday ? '' : 'active' }}" data-day="monday">Lunes</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->tuesday ? '' : 'active' }}" data-day="tuesday">Martes</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->wednesday ? '' : 'active' }}" data-day="wednesday">Miércoles</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->thursday ? '' : 'active' }}" data-day="thursday">Jueves</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->friday ? '' : 'active' }}" data-day="friday">Viernes</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->saturday ? '' : 'active' }}" data-day="saturday">Sábado</button>
                        <button type="button" class="btn weekday-toggle {{ $weeklyAvailability->sunday ? '' : 'active' }}" data-day="sunday">Domingo</button>
                    </div>
                    <p class="text-muted w-100 mt-2">Los días marcados indican que <strong>NO</strong> estás disponible ese día de la semana.</p>
                </div>

                <h6 class="mb-3">Fechas específicas de no disponibilidad</h6>
                <p class="text-muted mb-4">Selecciona los días específicos en los que no estarás disponible.</p>
                
                <div class="row">
                    @foreach($months as $index => $month)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="text-center mb-4">{{ $month['name'] }}</h5>
                                    <div class="calendar-grid">
                                        <div class="calendar-header">Do</div>
                                        <div class="calendar-header">Lu</div>
                                        <div class="calendar-header">Ma</div>
                                        <div class="calendar-header">Mi</div>
                                        <div class="calendar-header">Ju</div>
                                        <div class="calendar-header">Vi</div>
                                        <div class="calendar-header">Sa</div>
                                        
                                        @for($i = 0; $i < $month['startPadding']; $i++)
                                            <div class="calendar-day day-disabled"></div>
                                        @endfor
                                        
                                        @for($day = 1; $day <= $month['daysInMonth']; $day++)
                                            @php
                                                $date = $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                                $isUnavailable = in_array($date, $absences);
                                                $isToday = date('Y-m-d') === $date;
                                            @endphp
                                            <div class="calendar-day {{ $isUnavailable ? 'day-unavailable' : '' }} {{ $isToday ? 'day-today' : '' }}" 
                                                 data-date="{{ $date }}">
                                                {{ $day }}
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="d-flex justify-content-center mt-3">
                    <a href="{{ route('availability.index', ['page' => 'next']) }}" class="btn btn-outline-primary">
                        Siguientes 6 meses <i class="ti ti-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // CSRF Token setup for AJAX
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Weekly availability toggle
    const weekdayToggles = document.querySelectorAll('.weekday-toggle');
    const weeklyAvailability = {
        monday: {{ $weeklyAvailability->monday ? 'true' : 'false' }},
        tuesday: {{ $weeklyAvailability->tuesday ? 'true' : 'false' }},
        wednesday: {{ $weeklyAvailability->wednesday ? 'true' : 'false' }},
        thursday: {{ $weeklyAvailability->thursday ? 'true' : 'false' }},
        friday: {{ $weeklyAvailability->friday ? 'true' : 'false' }},
        saturday: {{ $weeklyAvailability->saturday ? 'true' : 'false' }},
        sunday: {{ $weeklyAvailability->sunday ? 'true' : 'false' }}
    };
    
    weekdayToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const day = this.getAttribute('data-day');
            this.classList.toggle('active');
            weeklyAvailability[day] = !this.classList.contains('active');
            
            // Send update to server
            fetch('{{ route('availability.update-weekly') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(weeklyAvailability)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Actualizado',
                        text: 'Tu disponibilidad semanal ha sido actualizada',
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
    
    // Date availability toggle
    const calendarDays = document.querySelectorAll('.calendar-day:not(.day-disabled)');
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            const date = this.getAttribute('data-date');
            
            // Send update to server
            fetch('{{ route('availability.toggle-date') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ date: date })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'unavailable') {
                    this.classList.add('day-unavailable');
                } else {
                    this.classList.remove('day-unavailable');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
});
</script>
@endsection 