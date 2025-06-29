@extends('layouts/layoutMaster')

@section('title', 'Dashboard Colaboradores')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Collaborators chart
    const collaboratorsOptions = {
      chart: {
        height: 90,
        type: 'line',
        toolbar: {
          show: false
        },
        sparkline: {
          enabled: true
        }
      },
      colors: ['#28c76f'],
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      series: [{
        name: 'Colaboradoras',
        data: [105, 120, 90, 170, 130, 190, 140, 200, 120, 170]
      }],
      grid: {
        show: false
      },
      xaxis: {
        labels: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        labels: {
          show: false
        }
      }
    };
    
    // Projects chart
    const projectsOptions = {
      chart: {
        height: 90,
        type: 'line',
        toolbar: {
          show: false
        },
        sparkline: {
          enabled: true
        }
      },
      colors: ['#ff9f43'],
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      series: [{
        name: 'Proyectos',
        data: [50, 90, 30, 70, 20, 80, 30, 90, 50, 70]
      }],
      grid: {
        show: false
      },
      xaxis: {
        labels: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        labels: {
          show: false
        }
      }
    };
    
    // Circle chart
    const circleOptions = {
      chart: {
        height: 170,
        type: 'radialBar',
      },
      series: [83],
      colors: ['#28c76f'],
      plotOptions: {
        radialBar: {
          hollow: {
            size: '65%',
          },
          dataLabels: {
            name: {
              show: false,
            },
            value: {
              fontSize: '22px',
              fontWeight: 600,
              offsetY: 10,
              formatter: function() {
                return '1250';
              }
            },
            total: {
              show: true,
              label: 'Total',
              formatter: function() {
                return '';
              }
            }
          }
        }
      },
    };
    
    // Initialize charts
    if (document.getElementById('collaborators-chart')) {
      const collaboratorsChart = new ApexCharts(
        document.getElementById('collaborators-chart'),
        collaboratorsOptions
      );
      collaboratorsChart.render();
    }
    
    if (document.getElementById('projects-chart')) {
      const projectsChart = new ApexCharts(
        document.getElementById('projects-chart'),
        projectsOptions
      );
      projectsChart.render();
    }
    
    if (document.getElementById('total-circle-chart')) {
      const circleChart = new ApexCharts(
        document.getElementById('total-circle-chart'),
        circleOptions
      );
      circleChart.render();
    }
  });
</script>
@endsection

@section('content')
<div class="row">
  <!-- Cards section -->
  <div class="col-xl-4 col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start">
          <div class="badge bg-label-success rounded p-2 me-3">
            <i class="ti ti-id-badge ti-sm"></i>
          </div>
          <div class="d-flex flex-column">
            <h4 class="mb-0">1262</h4>
            <span>Colaboradoras</span>
          </div>
        </div>
        <div id="collaborators-chart" class="mt-3"></div>
      </div>
    </div>
  </div>
  
  <div class="col-xl-4 col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start">
          <div class="badge bg-label-warning rounded p-2 me-3">
            <i class="ti ti-file-text ti-sm"></i>
          </div>
          <div class="d-flex flex-column">
            <h4 class="mb-0">186</h4>
            <span>Proyectos activos</span>
          </div>
        </div>
        <div id="projects-chart" class="mt-3"></div>
      </div>
    </div>
  </div>
  
  <div class="col-xl-4 col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h6 class="mb-0">Colaboradoras</h6>
            <small class="text-muted">Último mes</small>
            <h4 class="mb-0 mt-2">+49</h4>
            <span class="text-success d-flex align-items-center gap-1">
              <i class="ti ti-trending-up"></i>
              <span>15.8%</span>
            </span>
          </div>
          <div id="total-circle-chart"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Incomplete data section -->
  <div class="col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title mb-0">Colaboradoras con datos incompletos</h5>
        <a href="#" class="btn btn-sm btn-outline-secondary">Ver todos</a>
      </div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush">
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Jordan Stevenson</h6>
              </div>
            </div>
            <span class="text-muted">12 campos a rellenar</span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/2.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Benedetto Rossiter</h6>
              </div>
            </div>
            <span class="text-muted">Faltan: 5 tarifas, 7 campos</span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/3.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Bentlee Emblin</h6>
              </div>
            </div>
            <span class="text-muted"></span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/4.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Bertha Biner</h6>
              </div>
            </div>
            <span class="text-muted"></span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/5.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Beverlie Krabbe</h6>
              </div>
            </div>
            <span class="text-muted"></span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/6.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Bradan Rosebotham</h6>
              </div>
            </div>
            <span class="text-muted"></span>
          </div>
          
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('assets/img/avatars/7.png') }}" alt="Avatar" class="rounded-circle">
              </div>
              <div>
                <h6 class="mb-0">Bree Kilday</h6>
              </div>
            </div>
            <span class="text-muted"></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Language combinations section -->
  <div class="col-md-6 mb-4">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Combinaciones con pocas colaboradoras</h5>
      </div>
      <div class="card-body">
        @if($languageCombinations->count() > 0)
          <div class="table-responsive">
            <ul class="list-unstyled mb-0" style="max-height: 400px; overflow-y: auto;">
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
          </div>
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
  </div>
</div>

<div class="row">
  <!-- Activity section -->
  <div class="col-md-6 mb-4">
    <div class="card h-100">
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
  
  <!-- Top languages section -->
  <div class="col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">Top idiomas</h5>
      </div>
      <div class="card-body">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm bg-label-primary me-3 rounded">
                <i class="ti ti-video ti-sm"></i>
              </div>
              <span>Inglés</span>
            </div>
            <div>
              <span class="badge bg-label-primary rounded-pill">1.1k colaboradoras</span>
            </div>
          </li>
          
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm bg-label-info me-3 rounded">
                <i class="ti ti-code ti-sm"></i>
              </div>
              <span>Español</span>
            </div>
            <div>
              <span class="badge bg-label-info rounded-pill">931 colaboradoras</span>
            </div>
          </li>
          
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm bg-label-success me-3 rounded">
                <i class="ti ti-camera ti-sm"></i>
              </div>
              <span>Chino</span>
            </div>
            <div>
              <span class="badge bg-label-success rounded-pill">294 colaboradoras</span>
            </div>
          </li>
          
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm bg-label-warning me-3 rounded">
                <i class="ti ti-world ti-sm"></i>
              </div>
              <span>Alemán</span>
            </div>
            <div>
              <span class="badge bg-label-warning rounded-pill">167 colaboradoras</span>
            </div>
          </li>
          
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <div class="avatar avatar-sm bg-label-danger me-3 rounded">
                <i class="ti ti-brand-html5 ti-sm"></i>
              </div>
              <span>Noruego</span>
            </div>
            <div>
              <span class="badge bg-label-danger rounded-pill">67 colaboradoras</span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection 