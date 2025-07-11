@extends('layouts/layoutMaster')

@section('title', $collaborator->name . ' - Actividad')

@section('vendor-style')
    <style>
        .legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <!-- Collaborator Sidebar -->
        @include('collaborator.partials.sidebar')
        <!--/ Collaborator Sidebar -->

        <!-- Collaborator Content -->
        <div class="col-xl-8 col-lg-7 col-md-7">
            <!-- Tabs -->
            @include('collaborator.partials.tabs')

            <!-- Activity History -->
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title m-0 me-2 pt-1 mb-2 d-flex align-items-center">
                        Historial de Actividad
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="legend-item">
                            <span class="legend-dot bg-primary me-1"></span>
                            <small>Sistema</small>
                        </div>
                        @if($collaborator->user_id)
                        <div class="legend-item">
                            <span class="legend-dot bg-success me-1"></span>
                            <small>Propias</small>
                        </div>
                        @endif
                    </div>
                </div>
                                <div class="card-body pb-0">
                    @if($formattedActivities && $formattedActivities->count() > 0)
                        <ul class="timeline ms-1 mb-0">
                            @foreach($formattedActivities as $loop => $activity)
                            <li class="timeline-item timeline-item-transparent ps-4 {{ $loop->last ? 'border-transparent' : '' }}">
                                <span class="timeline-point {{ $activity['is_own_activity'] ? 'timeline-point-success' : 'timeline-point-primary' }}"></span>
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
                                                        'User logged in' => 'se conectó al sistema',
                                                        'User logged out' => 'se desconectó del sistema',
                                                        'File uploaded' => 'subió un archivo',
                                                        'Data exported' => 'exportó datos',
                                                        'Email sent' => 'envió un email',
                                                        'Search performed' => 'realizó una búsqueda',
                                                    ];
                                                    
                                                    foreach ($translations as $en => $es) {
                                                        if (str_contains($description, $en)) {
                                                            $description = str_replace($en, $es, $description);
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                {{ $description }}
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
                                                    <a href="javascript:void(0)" class="text-info me-2 d-flex align-items-center" data-bs-toggle="collapse" data-bs-target="#changes-{{ $activity['id'] }}" aria-expanded="false" title="Ver cambios">
                                                        <i class="ti ti-eye ti-sm"></i>
                                                    </a>
                                                @endif
                                            @endif
                                            
                                            <small class="text-muted">{{ $activity['time_ago'] }}</small>
                                        </div>
                                    </div>
                                    <p class="mb-2">Por {{ $activity['user_name'] }}</p>
                                    
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
                                            <div class="collapse" id="changes-{{ $activity['id'] }}">
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
                                    
                                    @if($activity['user_photo'])
                                        <div class="d-flex flex-wrap mt-2">
                                            <div class="avatar me-2">
                                                <img src="{{ $activity['user_photo'] }}" alt="{{ $activity['user_name'] }}" class="rounded-circle">
                                            </div>
                                            <div class="ms-1">
                                                <h6 class="mb-0">{{ $activity['user_name'] }}</h6>
                                                <span>{{ $activity['is_own_activity'] ? 'Usuario' : 'Administrador' }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-secondary">
                                    <i class="ti ti-activity ti-md"></i>
                                </span>
                            </div>
                            <h5 class="mb-2">Sin actividad registrada</h5>
                            <p class="mb-0 text-muted">
                                @if($collaborator->user_id)
                                    {{ $collaborator->name }} aún no ha realizado ninguna actividad en el sistema.
                                @else
                                    No hay actividad registrada para {{ $collaborator->name }}.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Include Valoration Modal -->
    @include('collaborator.partials.valoration-modal')
@endsection
