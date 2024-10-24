@php
$evolutionSteps = [
    [
        'start_date' => '09-06-2024',
        'end_date' => '28-06-2024',
        'service' => 'Servicio molón #1',
        'step' => 'Paso núm 1',
        'objective' => 'Objetivo del paso 1',
        'report' => true,
        'initial_emoji' => '😊',
        'final_emoji' => '🥳',
        'report_date' => '09-07-2024'
    ],
    [
        'start_date' => '05-07-2024',
        'end_date' => '15-07-2024',
        'service' => 'Servicio molón #1',
        'step' => 'Paso núm 2',
        'objective' => 'Objetivo del paso 2',
        'report' => true,
        'initial_emoji' => '😊',
        'final_emoji' => '🥳',
        'report_date' => '09-07-2024'
    ],
    [
        'start_date' => '01-09-2024',
        'end_date' => '10-09-2024',
        'service' => 'Servicio molón #1',
        'step' => 'Paso núm 3',
        'objective' => 'Objetivo del paso 3',
        'report' => true,
        'initial_emoji' => '😊',
        'final_emoji' => '🥳',
        'report_date' => '09-07-2024'
    ],
    [
        'start_date' => '15-09-2024',
        'end_date' => '25-09-2024',
        'service' => 'Servicio molón #1',
        'step' => 'Paso núm 4',
        'objective' => 'Objetivo del paso 4',
        'report' => false
    ],
    [
        'start_date' => '01-10-2024',
        'end_date' => '15-10-2024',
        'service' => 'Servicio molón #1',
        'step' => 'Paso núm 5',
        'objective' => 'Objetivo del paso 5',
        'report' => false
    ]
];
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Evolución en servicios</h5>
        <div class="dropdown">
            <button class="btn btn-link p-0" type="button" id="evolutionInfo" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-info-circle"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="evolutionInfo">
                <p class="dropdown-item-text">
                    Listado con todos los pasos de los servicios ordenados por fecha (ascendente) para saber qué servicios está haciendo el cliente. Incluye los pasos futuros para saber cuándo los tiene y si va en plazos.
                </p>
                <p class="dropdown-item-text">
                    En gris clarito la fecha estimada (calculada porque el itinerario tiene las fechas de los pasos, no es IA ni predicciones)
                </p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Final</th>
                        <th>Servicio</th>
                        <th>Paso</th>
                        <th>Objetivo</th>
                        <th>Informe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($evolutionSteps as $step)
                    <tr @if(!$step['report']) class="text-muted" @endif>
                        <td>{{ $step['start_date'] }}</td>
                        <td>{{ $step['end_date'] }}</td>
                        <td>{{ $step['service'] }}</td>
                        <td>{{ $step['step'] }}</td>
                        <td>{{ $step['objective'] }}</td>
                        <td>
                            @if($step['report'])
                                <span class="me-1">{{ $step['initial_emoji'] }}</span>
                                <i class="ti ti-arrow-right"></i>
                                <span class="ms-1">{{ $step['final_emoji'] }}</span>
                                {{ $step['report_date'] }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevrons-left"></i></a></li>
                <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevron-left"></i></a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">4</a></li>
                <li class="page-item"><a class="page-link" href="#">5</a></li>
                <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevron-right"></i></a></li>
                <li class="page-item"><a class="page-link" href="#"><i class="ti ti-chevrons-right"></i></a></li>
            </ul>
        </nav>
    </div>
</div>