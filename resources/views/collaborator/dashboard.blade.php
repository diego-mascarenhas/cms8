@extends('layouts/layoutMaster')

@section('title', 'Dashboard Colaboradores')



@section('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dashboard loaded successfully
            console.log('Dashboard collaboradores cargado');
        });
    </script>
@endsection

@section('page-style')
<style>
.legend-item {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.timeline-point {
    position: absolute;
    left: -6px;
    border: 2px solid #fff;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #696cff;
}

.timeline-point-primary {
    background-color: #696cff;
}

.timeline-point-success {
    background-color: #71dd37;
}

.timeline-item-transparent {
    border-left: 1px solid #d9dee3;
}

.timeline-item-transparent.border-transparent {
    border-left-color: transparent;
}

.timeline-event {
    padding: 0 0 1.5rem 1rem;
}

.timeline-header {
    margin-bottom: 0.5rem;
}
</style>
@endsection

@section('content')
    <div class="row">
        <!-- Cards section -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">{{ number_format($totalCollaborators) }}</h4>
                            <small class="text-muted">Total colaboradoras</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-users ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">{{ number_format($newCollaboratorsThisMonth) }}</h4>
                            <small class="text-muted">Nuevas este mes</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ti ti-user-plus ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">{{ number_format($activeProjects) }}</h4>
                            <small class="text-muted">Proyectos activos</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ti ti-briefcase ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1">{{ number_format($activeLanguages) }}</h4>
                            <small class="text-muted">Idiomas activos</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-language ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @php
            // Determine order based on number of incomplete collaborators
            $hasMany = $incompleteCollaborators->count() > 10;
        @endphp

        <!-- Language combinations section -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Combinaciones con pocas colaboradoras</h5>
                </div>
                <div class="card-body">
                    @if ($languageCombinations->count() > 0)
                        <ul class="list-unstyled mb-0">
                            @foreach ($languageCombinations as $combination)
                                <li class="mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <i class="fi fi-{{ $combination['source_flag'] }} me-2"
                                                style="font-size: 1.1em;"></i>
                                            <i class="ti ti-arrow-right mx-2 text-muted" style="font-size: 0.9em;"></i>
                                            <i class="fi fi-{{ $combination['target_flag'] }} me-2"
                                                style="font-size: 1.1em;"></i>
                                            <span class="ms-2 text-truncate"
                                                title="{{ $combination['source_name'] }} a {{ $combination['target_name'] }}">
                                                {{ $combination['source_name'] }} a {{ $combination['target_name'] }}
                                            </span>
                                        </div>
                                        <span class="badge bg-label-warning rounded-pill ms-2 flex-shrink-0">
                                            {{ $combination['count'] }}
                                            colaborador{{ $combination['count'] !== 1 ? 'as' : 'a' }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-4">
                            <div class="avatar avatar-xl bg-light-success rounded-circle mx-auto mb-3">
                                <i class="ti ti-check ti-lg text-success"></i>
                            </div>
                            <h6 class="mb-1">¡Excelente cobertura!</h6>
                            <p class="text-muted mb-0">Todas las combinaciones de idiomas tienen 10 o más colaboradoras.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Top languages section (below combinations) -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top idiomas</h5>
                </div>
                <div class="card-body px-0 pt-0 pb-3">
                    @if ($topLanguages->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach ($topLanguages as $index => $language)
                                @php
                                    // Define badge colors rotating through different styles
                                    $badgeColors = ['primary', 'info', 'success', 'warning', 'danger'];
                                    $badgeColor = $badgeColors[$index % count($badgeColors)];
                                @endphp

                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="fi fi-{{ $language['flag'] }} me-2" style="font-size: 1.1em;"></i>
                                        <span>{{ $language['name'] }}</span>
                                    </div>
                                    <div>
                                        <span class="badge bg-label-{{ $badgeColor }} rounded-pill">
                                            {{ $language['count'] }}
                                            colaborador{{ $language['count'] !== 1 ? 'as' : 'a' }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-4">
                            <div class="avatar avatar-xl bg-light-secondary rounded-circle mx-auto mb-3">
                                <i class="ti ti-language ti-lg text-secondary"></i>
                            </div>
                            <h6 class="mb-1">Sin datos de idiomas</h6>
                            <p class="text-muted mb-0">No hay colaboradores registrados con idiomas asignados.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Activity section (below top languages) -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title m-0 me-2 pt-1 mb-2 d-flex align-items-center">
                        <i class="ti ti-activity me-2"></i>Actividad del equipo
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="legend-item">
                            <span class="legend-dot bg-primary me-1"></span>
                            <small>Sistema</small>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot bg-success me-1"></span>
                            <small>Usuarios</small>
                        </div>
                        @can('activity-log.index')
                            <a href="{{ route('activity-log.index') }}" class="btn btn-sm btn-outline-primary">
                                Ver todo
                            </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body pb-0">
                    @if ($formattedActivities && $formattedActivities->count() > 0)
                        <ul class="timeline ms-1 mb-0">
                            @foreach ($formattedActivities as $activity)
                                <li class="timeline-item timeline-item-transparent ps-4 {{ $loop->last ? 'border-transparent' : '' }}">
                                    <span class="timeline-point {{ $activity['is_system_activity'] ? 'timeline-point-primary' : 'timeline-point-success' }}"></span>
                                    <div class="timeline-event {{ $loop->last ? 'pb-0' : '' }}">
                                        <div class="timeline-header">
                                            <div class="d-flex align-items-center w-100">
                                                <h6 class="mb-0 flex-grow-1">
                                                    @php
                                                        $description = $activity['description'];
                                                        // Translate common activity descriptions
                                                        $translations = [
                                                            'created' => 'Creación',
                                                            'updated' => 'Actualización', 
                                                            'deleted' => 'Eliminación',
                                                            'User logged in' => 'Se conectó al sistema',
                                                            'User logged out' => 'Se desconectó del sistema',
                                                            'File uploaded' => 'Subió un archivo',
                                                            'Data exported' => 'Exportó datos',
                                                            'Email sent' => 'Envió un email',
                                                            'Search performed' => 'Realizó una búsqueda',
                                                        ];
                                                        
                                                        foreach ($translations as $en => $es) {
                                                            if (str_contains($description, $en)) {
                                                                $description = str_replace($en, $es, $description);
                                                                break;
                                                            }
                                                        }
                                                    @endphp
                                                    {{ $description }}
                                                    @if ($activity['subject_name'])
                                                        - {{ $activity['subject_name'] }}
                                                    @endif
                                                </h6>
                                                
                                                @if($activity['properties'] && $activity['properties']->count() > 0)
                                                    @php 
                                                        $properties = $activity['properties']; 
                                                        $hasChanges = false;
                                                        
                                                        // Check for changes in different formats
                                                        if ((isset($properties['old']) && isset($properties['attributes'])) || 
                                                            (isset($properties->old) && isset($properties->attributes)) ||
                                                            (property_exists($properties, 'old') && property_exists($properties, 'attributes'))) {
                                                            $hasChanges = true;
                                                        }
                                                    @endphp
                                                    
                                                    @if($hasChanges)
                                                        <a href="javascript:void(0)" class="text-info me-2 d-flex align-items-center" data-bs-toggle="collapse" data-bs-target="#changes-dashboard-{{ $activity['id'] }}" aria-expanded="false" title="Ver cambios">
                                                            <i class="ti ti-eye ti-sm"></i>
                                                        </a>
                                                    @endif
                                                @endif
                                                
                                                <small class="text-muted">{{ $activity['time_ago'] }}</small>
                                            </div>
                                        </div>
                                        @if ($activity['user_name'])
                                            <p class="mb-2">Por {{ $activity['user_name'] }}</p>
                                        @endif
                                        
                                        @if($activity['properties'] && $activity['properties']->count() > 0)
                                            @php $properties = $activity['properties']; @endphp
                                            
                                            @if(isset($properties['file_name']) || isset($properties['email_to']) || isset($properties['search_term']) || isset($properties['export_type']))
                                                <div class="d-flex flex-wrap gap-2 pt-1">
                                                    @if(isset($properties['file_name']))
                                                        <span class="fw-medium text-heading">{{ $properties['file_name'] }}</span>
                                                    @endif
                                                    @if(isset($properties['email_to']))
                                                        <span class="fw-medium text-heading">{{ $properties['email_to'] }}</span>
                                                    @endif
                                                    @if(isset($properties['search_term']))
                                                        <span class="fw-medium text-heading">Búsqueda: {{ $properties['search_term'] }}</span>
                                                    @endif
                                                    @if(isset($properties['export_type']))
                                                        <span class="fw-medium text-heading">{{ $properties['export_type'] }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                            
                                            @if(isset($properties['ip_address']))
                                                <div class="mt-1">
                                                    <small class="text-muted">IP: {{ $properties['ip_address'] }}</small>
                                                </div>
                                            @endif
                                        @endif
                                        
                                        @if($activity['properties'] && $activity['properties']->count() > 0)
                                            @php 
                                                $properties = $activity['properties']; 
                                                $hasChanges = false;
                                                
                                                // Check for changes in different formats
                                                if ((isset($properties['old']) && isset($properties['attributes'])) || 
                                                    (isset($properties->old) && isset($properties->attributes)) ||
                                                    (property_exists($properties, 'old') && property_exists($properties, 'attributes'))) {
                                                    $hasChanges = true;
                                                }
                                            @endphp
                                            
                                            @if($hasChanges)
                                                <div class="collapse" id="changes-dashboard-{{ $activity['id'] }}">
                                                    <div class="mt-2 p-2 bg-light rounded" style="font-size: 0.8rem;">
                                                        @php
                                                            // Try different ways to access the data
                                                            $oldData = $properties['old'] ?? $properties->old ?? [];
                                                            $newData = $properties['attributes'] ?? $properties->attributes ?? [];
                                                            
                                                            // Convert to arrays if they're objects
                                                            if (is_object($oldData)) {
                                                                $oldData = (array) $oldData;
                                                            }
                                                            if (is_object($newData)) {
                                                                $newData = (array) $newData;
                                                            }
                                                            
                                                            // Handle Collection case
                                                            if (method_exists($properties, 'toArray')) {
                                                                $propsArray = $properties->toArray();
                                                                $oldData = $propsArray['old'] ?? [];
                                                                $newData = $propsArray['attributes'] ?? [];
                                                            }
                                                        @endphp
                                                        
                                                        @if(is_array($newData) && count($newData) > 0)
                                                            @foreach($newData as $key => $newValue)
                                                                <div class="mb-1">
                                                                    <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong><br>
                                                                    @if(array_key_exists($key, $oldData) && $oldData[$key] !== null)
                                                                        <span class="text-danger">- {{ $oldData[$key] }}</span><br>
                                                                    @endif
                                                                    <span class="text-success">+ {{ $newValue ?? 'null' }}</span>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                        
                                        @if ($activity['user_photo'])
                                            <div class="d-flex flex-wrap mt-2">
                                                <div class="avatar me-2">
                                                    <img src="{{ $activity['user_photo'] }}" alt="{{ $activity['user_name'] }}" class="rounded-circle">
                                                </div>
                                                <div class="ms-1">
                                                    <h6 class="mb-0">{{ $activity['user_name'] }}</h6>
                                                    <span>{{ $activity['is_system_activity'] ? 'Sistema' : 'Usuario del equipo' }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-secondary">
                                    <i class="ti ti-activity ti-md"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">Sin actividad reciente</h5>
                            <p class="mb-0 text-muted">No hay actividades recientes del equipo para mostrar.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Collaborators with incomplete data (right side) -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title mb-0">Colaboradoras con datos incompletos</h5>
                </div>
                <div class="card-body p-0">
                    @if ($incompleteCollaborators->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach ($incompleteCollaborators as $collaborator)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <img src="{{ $collaborator['avatar'] }}" alt="Avatar"
                                                class="rounded-circle">
                                        </div>
                                        <div>
                                            <h6 class="mb-0">{{ $collaborator['name'] }}</h6>
                                            @if ($collaborator['missing_count'] > 1)
                                                <small class="text-muted">Faltan:
                                                    {{ implode(', ', array_slice($collaborator['missing_fields'], 0, 3)) }}{{ count($collaborator['missing_fields']) > 3 ? '...' : '' }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted">{{ $collaborator['missing_text'] }}</span>
                                        @can('collaborator.edit')
                                            <br><a href="{{ route('collaborator.edit', $collaborator['id']) }}"
                                                class="btn btn-xs btn-outline-primary mt-1">
                                                <i class="ti ti-edit ti-xs"></i> Editar
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="card-body text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-success">
                                    <i class="ti ti-check ti-md"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">¡Excelente!</h5>
                            <p class="mb-0 text-muted">Todas las colaboradoras tienen sus datos completos.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>


@endsection
