@php
$scheduledMessages = [
    [
        'date' => '09/06/2024',
        'message' => 'Hola! Empezamos la semana con este mensaje para que te alegre el día y así puedas avanzar. Mira ...',
        'sender' => 'Felicia Risker',
        'channel' => 'Email',
        'status' => 'sent'
    ],
    [
        'date' => '15/09/2024',
        'message' => 'Hola! Empezamos la semana con este mensaje para que te alegre el día y así puedas avanzar. Mira ...',
        'sender' => 'Felicia Risker',
        'channel' => 'WhatsApp',
        'status' => 'scheduled'
    ],
];
@endphp

<div class="card mb-4">
    <h5 class="card-header d-flex justify-content-between align-items-center">
        Mensajes programados
        <button type="button" class="btn btn-primary btn-sm">
            + Nnuevo mensaje
        </button>
    </h5>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Mensaje</th>
                        <th>Emisor</th>
                        <th>Canal</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scheduledMessages as $message)
                    <tr>
                        <td>{{ $message['date'] }}</td>
                        <td>{{ Str::limit($message['message'], 35) }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($message['sender']) }}&background=random" alt="{{ $message['sender'] }}" class="rounded-circle me-2" width="32" height="32">
                                {{ $message['sender'] }}
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-label-{{ $message['channel'] == 'Email' ? 'warning' : 'success' }}">
                                {{ $message['channel'] }}
                            </span>
                        </td>
                        <td>
                            @if($message['status'] == 'sent')
                                <i class="ti ti-check text-success"></i>
                                <i class="ti ti-check text-success"></i>
                            @else
                                <i class="ti ti-clock text-muted"></i>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div>Mostrando 1 a 10 de 50</div>
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