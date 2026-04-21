@extends('layouts/layoutMaster')

@section('title', __('app.clients'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Clients') }}/</span> {{ $client->name }}</h4>
            <p class="text-muted">{{ __('Detailed client information') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @can('client.edit')
                <a href="{{ route('client.edit', $client->id) }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-edit me-1"></i>{{ __('Edit') }} {{ __('Client') }}
                </a>
            @endcan
            @can('project.create')
                <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-success waves-effect waves-light">
                    <i class="ti ti-folder-plus me-1"></i>{{ __('Create') }} {{ __('Project') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="col-12">
        @if($client->data && ($client->data->style_guide ?? null))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Guía de estilo</h5>
                </div>
                <div class="card-body">
                    <p>{{ $client->data->style_guide }}</p>
                </div>
            </div>
        @endif

        {{-- Datos de facturación (similar bloque CMS7) --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Datos de facturación</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-label-primary">{{ $billingAddresses->count() }} {{ $billingAddresses->count() === 1 ? 'registro' : 'registros' }}</span>
                    @can('client.edit')
                        <a href="{{ route('client.edit', $client->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-file-invoice me-1"></i>Actualizar datos fiscales
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                @if($billingAddresses->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Razón social</th>
                                    <th>C.U.I.T. / ID fiscal</th>
                                    <th>Condición fiscal</th>
                                    <th>Dirección</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($billingAddresses as $billing)
                                    <tr>
                                        <td>{{ $billing->name }}</td>
                                        <td>{{ $billing->identification_number ?: '—' }}</td>
                                        <td>{{ $billing->taxStatusType->name ?? '—' }}</td>
                                        <td class="text-muted small">
                                            {{ \Illuminate\Support\Str::limit(trim(implode(', ', array_filter([$billing->address, $billing->locality, $billing->province]))), 80) ?: '—' }}
                                        </td>
                                        <td>
                                            @if((int) $billing->status === 1)
                                                <span class="badge bg-label-success rounded-pill">Activo</span>
                                            @else
                                                <span class="badge bg-label-secondary rounded-pill">Inactivo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="ti ti-receipt-2 display-4 text-muted"></i>
                        </div>
                        <h6 class="mb-1">Sin datos de facturación</h6>
                        <p class="text-muted mb-0">Agrega una razón social y datos fiscales desde la edición del cliente.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Contactos --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Contactos</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-label-primary">{{ $linkedContacts->count() }} {{ $linkedContacts->count() === 1 ? 'registro' : 'registros' }}</span>
                    @can('contact.create')
                        <a href="{{ route('contact.create') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-user-plus me-1"></i>Ingresar contacto
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                @if($linkedContacts->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clientContactsTable">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Cargo</th>
                                    <th>Rol vinculado</th>
                                    <th>Última actualización</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($linkedContacts as $contact)
                                    <tr>
                                        <td>
                                            <a href="{{ route('contact.show', $contact->id) }}" class="text-decoration-none fw-medium">
                                                {{ $contact->name }}{{ $contact->surname ? ' '.$contact->surname : '' }}
                                            </a>
                                        </td>
                                        <td>{{ $contact->email ?: '—' }}</td>
                                        <td>{{ $contact->phone ?: '—' }}</td>
                                        <td>{{ $contact->pivot->position ?: '—' }}</td>
                                        <td>
                                            @if($contact->user && $contact->user->roles->isNotEmpty())
                                                {{ $contact->user->roles->pluck('name')->join(', ') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $contact->updated_at ? $contact->updated_at->format('d/m/Y H:i') : '—' }}</td>
                                        <td>{{ optional($contact->status)->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="ti ti-users display-4 text-muted"></i>
                        </div>
                        <h6 class="mb-1">Sin contactos vinculados</h6>
                        <p class="text-muted mb-0">Asocia contactos a este cliente desde la ficha de cada contacto.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Servicios --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Servicios</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-label-primary">{{ $services->count() }} {{ $services->count() === 1 ? 'registro' : 'registros' }}</span>
                    @can('service.create')
                        <a href="{{ route('service.create', ['enterprise_id' => $client->id]) }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus me-1"></i>Ingresar servicio
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                @if($services->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clientServicesTable">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Plan / categoría</th>
                                    <th>Dominio</th>
                                    <th>Usuario</th>
                                    <th>Valor</th>
                                    <th>Frecuencia</th>
                                    <th>Próxima</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($services as $service)
                                    @php
                                        $freq = (int) ($service->frequency ?? 1);
                                        $frequencyLabel = match ($freq) {
                                            1 => 'Mensual',
                                            3 => 'Trimestral',
                                            6 => 'Semestral',
                                            12 => 'Anual',
                                            default => $freq > 0 ? $freq.' mes(es)' : '—',
                                        };
                                        $desc = $service->description ?: ($service->service_name ?? '—');
                                        $plan = optional($service->serviceType)->name ?? '—';
                                        $cur = $service->currency;
                                        $curCode = $cur->code ?? ($cur->symbol ?? '');
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('service.show', $service->id) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($desc, 64) }}</a>
                                        </td>
                                        <td>{{ $plan }}</td>
                                        <td>{{ $service->domain ?: '—' }}</td>
                                        <td>{{ $service->username ?: '—' }}</td>
                                        <td>
                                            @if($service->price !== null && (float) $service->price != 0.0)
                                                {{ number_format((float) $service->price, 2) }} {{ $curCode }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $frequencyLabel }}</td>
                                        <td>{{ $service->next_billing ? $service->next_billing->format('d/m/Y') : '—' }}</td>
                                        <td>{!! $service->status_label !!}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="ti ti-tools display-4 text-muted"></i>
                        </div>
                        <h6 class="mb-1">Sin servicios</h6>
                        <p class="text-muted mb-0">No hay servicios registrados para este cliente.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Facturas (bloque colapsable, estilo ibox CMS7) --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientInvoicesBlock" aria-expanded="false" aria-controls="clientInvoicesBlock" style="cursor: pointer;">
                    <i class="ti ti-chevron-down collapse-chevron"></i>
                    <span>Facturas</span>
                </span>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-label-secondary">Saldo pendiente: {{ number_format((float) $invoiceBalanceTotal, 2) }}</span>
                    <span class="badge bg-label-primary">{{ $invoices->count() }} {{ $invoices->count() === 1 ? 'registro' : 'registros' }}</span>
                    @can('invoice.create')
                        <a href="{{ route('invoice.create') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-file-plus me-1"></i>Ingresar factura
                        </a>
                    @endcan
                    @can('invoice.index')
                        <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-list me-1"></i>Listado
                        </a>
                    @endcan
                </div>
            </div>
            <div class="collapse" id="clientInvoicesBlock">
                <div class="card-body border-top">
                    @if($invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="clientInvoicesTable">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Fecha</th>
                                        <th>Vencimiento</th>
                                        <th>Total</th>
                                        <th>Saldo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                        <tr>
                                            <td>
                                                @can('invoice.show')
                                                    <a href="{{ route('invoice.show', $invoice->id) }}" class="text-decoration-none">{{ $invoice->number ?: '—' }}</a>
                                                @else
                                                    {{ $invoice->number ?: '—' }}
                                                @endcan
                                            </td>
                                            <td>{{ $invoice->date ? \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') : '—' }}</td>
                                            <td>{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : '—' }}</td>
                                            <td>{{ number_format((float) ($invoice->total_amount ?? 0), 2) }} {{ $invoice->currency ?? '' }}</td>
                                            <td>{{ number_format((float) ($invoice->balance ?? 0), 2) }} {{ $invoice->currency ?? '' }}</td>
                                            <td>{!! $invoice->status_badge !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-file-invoice display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin facturas</h6>
                            <p class="text-muted mb-0">No hay facturas emitidas para este cliente.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Proyectos (colapsable; activos + historial) --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientProjectsBlock" aria-expanded="true" aria-controls="clientProjectsBlock" style="cursor: pointer;">
                    <i class="ti ti-chevron-up collapse-chevron"></i>
                    <span>Proyectos</span>
                </span>
                @can('project.create')
                    <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i>Ingresar proyecto
                    </a>
                @endcan
            </div>
            <div class="collapse show" id="clientProjectsBlock">
                <div class="card-body border-top">
                    @if($activeProjects->count() > 0)
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th>Descripción</th>
                                        <th>Transcurrido</th>
                                        <th>Responsable</th>
                                        <th>Categoría</th>
                                        <th class="text-end">Importe</th>
                                        <th>Inicio</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeProjects as $project)
                                        <tr>
                                            <td class="fw-medium">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">{{ $project->name }}</a>
                                            </td>
                                            <td class="text-muted small">{{ $project->description ? Str::limit($project->description, 72) : '—' }}</td>
                                            <td class="text-muted small">
                                                @php
                                                    $elapsedFrom = $project->date_start
                                                        ? \Carbon\Carbon::parse($project->date_start)
                                                        : ($project->created_at ? \Carbon\Carbon::parse($project->created_at) : null);
                                                @endphp
                                                @if($elapsedFrom)
                                                    {{ $elapsedFrom->locale(app()->getLocale())->diffForHumans(\Carbon\Carbon::now(), true) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ optional($project->responsible)->name ?? '—' }}</td>
                                            <td>{{ optional($project->category)->name ?? '—' }}</td>
                                            <td class="text-end">
                                                @if($project->price)
                                                    ${{ number_format($project->price, 2) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $project->date_start ? Carbon\Carbon::parse($project->date_start)->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                @if($project->status)
                                                    <span class="badge bg-label-success rounded-pill">{{ $project->status->translated_name }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 mb-4">
                            <i class="ti ti-folder-off display-6 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">Sin proyectos activos.</p>
                        </div>
                    @endif

                    @if($pastProjects->count() > 0)
                        <h6 class="text-muted mb-3">Proyectos pasados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th>Descripción</th>
                                        <th>Transcurrido</th>
                                        <th>Responsable</th>
                                        <th>Categoría</th>
                                        <th>Fin</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pastProjects->take(6) as $project)
                                        <tr>
                                            <td class="fw-medium">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none text-muted">{{ $project->name }}</a>
                                            </td>
                                            <td class="text-muted small">{{ $project->description ? Str::limit($project->description, 72) : '—' }}</td>
                                            <td class="text-muted small">
                                                @php
                                                    $pastFrom = $project->date_start
                                                        ? \Carbon\Carbon::parse($project->date_start)
                                                        : ($project->created_at ? \Carbon\Carbon::parse($project->created_at) : null);
                                                    $pastTo = $project->date_end
                                                        ? \Carbon\Carbon::parse($project->date_end)
                                                        : ($project->updated_at ? \Carbon\Carbon::parse($project->updated_at) : null);
                                                @endphp
                                                @if($pastFrom && $pastTo)
                                                    {{ $pastFrom->locale(app()->getLocale())->diffForHumans($pastTo, true) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ optional($project->responsible)->name ?? '—' }}</td>
                                            <td>{{ optional($project->category)->name ?? '—' }}</td>
                                            <td>{{ $project->date_end ? Carbon\Carbon::parse($project->date_end)->format('d/m/Y') : '—' }}</td>
                                            <td>
                                                @if($project->status)
                                                    <span class="badge bg-label-secondary rounded-pill">{{ $project->status->translated_name }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($pastProjects->count() > 6)
                            <div class="text-center mt-2">
                                <small class="text-muted">Mostrando 6 de {{ $pastProjects->count() }} proyectos pasados</small>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var dtLang = { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' };

    if (document.getElementById('clientContactsTable')) {
        $('#clientContactsTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            ordering: true,
            responsive: true,
            order: [[5, 'desc']],
        });
    }

    if (document.getElementById('clientServicesTable')) {
        $('#clientServicesTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            ordering: true,
            responsive: true,
        });
    }

    if (document.getElementById('clientInvoicesTable')) {
        $('#clientInvoicesTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            ordering: true,
            order: [[1, 'desc']],
            responsive: true,
        });
    }

    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (toggle) {
        var target = document.querySelector(toggle.getAttribute('data-bs-target'));
        if (!target) {
            return;
        }
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle.click();
            }
        });
        target.addEventListener('shown.bs.collapse', function () {
            var icon = toggle.querySelector('.collapse-chevron');
            if (icon) {
                icon.classList.remove('ti-chevron-down');
                icon.classList.add('ti-chevron-up');
            }
        });
        target.addEventListener('hidden.bs.collapse', function () {
            var icon = toggle.querySelector('.collapse-chevron');
            if (icon) {
                icon.classList.remove('ti-chevron-up');
                icon.classList.add('ti-chevron-down');
            }
        });
    });
});
</script>
@endsection
