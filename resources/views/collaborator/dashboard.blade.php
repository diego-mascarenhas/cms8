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
        @if($languageCombinations->count() > 0)
          <ul class="list-unstyled mb-0">
            @foreach($languageCombinations as $combination)
            <li class="mb-2 pb-2 border-bottom">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center flex-grow-1">
                  <i class="fi fi-{{ $combination['source_flag'] }} me-2" style="font-size: 1.1em;"></i>
                  <i class="ti ti-arrow-right mx-2 text-muted" style="font-size: 0.9em;"></i>
                  <i class="fi fi-{{ $combination['target_flag'] }} me-2" style="font-size: 1.1em;"></i>
                  <span class="ms-2 text-truncate" title="{{ $combination['source_name'] }} a {{ $combination['target_name'] }}">
                    {{ $combination['source_name'] }} a {{ $combination['target_name'] }}
                  </span>
                </div>
                <span class="badge bg-label-warning rounded-pill ms-2 flex-shrink-0">
                  {{ $combination['count'] }} colaborador{{ $combination['count'] !== 1 ? 'as' : 'a' }}
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
        @if($topLanguages->count() > 0)
          <ul class="list-group list-group-flush">
            @foreach($topLanguages as $index => $language)
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
                    {{ $language['count'] }} colaborador{{ $language['count'] !== 1 ? 'as' : 'a' }}
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
      <div class="card-header">
        <div class="d-flex align-items-center">
          <i class="ti ti-activity me-2"></i>
          <h5 class="card-title mb-0">Actividad</h5>
        </div>
      </div>
      <div class="card-body pt-2">
        <ul class="timeline ps-0 mb-0">
          <li class="timeline-item ps-4 border-left-primary">
            <span class="timeline-indicator-dot bg-primary"></span>
            <div class="d-flex flex-column">
              <small class="text-muted mb-1">12 min ago</small>
              <div class="d-flex flex-column">
                <span class="fw-semibold">María ha dado de alta a Pedro García</span>
                <span class="text-muted">Invoices have been paid to the company</span>
                <div class="d-flex align-items-center mt-2">
                  <div class="d-flex align-items-center bg-lighter p-2 rounded">
                    <i class="ti ti-file-text text-danger me-2"></i>
                    <span>invoices.pdf</span>
                  </div>
                </div>
              </div>
            </div>
          </li>
          
          <li class="timeline-item ps-4 border-left-success mt-4">
            <span class="timeline-indicator-dot bg-success"></span>
            <div class="d-flex flex-column">
              <small class="text-muted mb-1">45 min ago</small>
              <div class="d-flex flex-column">
                <span class="fw-semibold">Juana Gutiérrez ha aceptado los cambios</span>
                <span class="text-muted">Project meeting with john @10:15am</span>
                <div class="d-flex align-items-center mt-2">
                  <div class="avatar me-2">
                    <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle">
                  </div>
                  <div class="d-flex flex-column">
                    <span class="fw-semibold">Lester McCarthy (Client)</span>
                    <small class="text-muted">CEO of ThemeSelection</small>
                  </div>
                </div>
              </div>
            </div>
          </li>
          
          <li class="timeline-item ps-4 border-left-info mt-4">
            <span class="timeline-indicator-dot bg-info"></span>
            <div class="d-flex flex-column">
              <small class="text-muted mb-1">2 Day Ago</small>
              <div class="d-flex flex-column">
                <span class="fw-semibold">Envío de notificación a Iván Fernández</span>
                <span class="text-muted">6 team members in a project</span>
                <div class="d-flex align-items-center mt-2">
                  <div class="avatar-group">
                    <div class="avatar me-1">
                      <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle">
                    </div>
                    <div class="avatar me-1">
                      <img src="{{ asset('assets/img/avatars/2.png') }}" alt="Avatar" class="rounded-circle">
                    </div>
                    <div class="avatar me-1">
                      <img src="{{ asset('assets/img/avatars/3.png') }}" alt="Avatar" class="rounded-circle">
                    </div>
                    <div class="avatar rounded-circle d-flex align-items-center justify-content-center bg-light text-secondary">
                      <span>+3</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Collaborators with incomplete data (right side) -->
  <div class="col-md-6 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title mb-0">Colaboradoras con datos incompletos</h5>
        @can('collaborator.edit')
        <a href="{{ route('collaborator-list') }}" class="btn btn-sm btn-outline-secondary">Ver todos</a>
        @endcan
      </div>
      <div class="card-body p-0">
        @if($incompleteCollaborators->count() > 0)
          <div class="list-group list-group-flush">
            @foreach($incompleteCollaborators as $collaborator)
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <div class="avatar me-3">
                  <img src="{{ $collaborator['avatar'] }}" alt="Avatar" class="rounded-circle">
                </div>
                <div>
                  <h6 class="mb-0">{{ $collaborator['name'] }}</h6>
                  @if($collaborator['missing_count'] > 1)
                    <small class="text-muted">Faltan: {{ implode(', ', array_slice($collaborator['missing_fields'], 0, 3)) }}{{ count($collaborator['missing_fields']) > 3 ? '...' : '' }}</small>
                  @endif
                </div>
              </div>
              <div class="text-end">
                <span class="text-muted">{{ $collaborator['missing_text'] }}</span>
                @can('collaborator.edit')
                <br><a href="{{ route('collaborator.edit', $collaborator['id']) }}" class="btn btn-xs btn-outline-primary mt-1">
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