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
            @can('edit', $client)
                <a href="{{ route('client.edit', $client->id) }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
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

        @php
            $hasBillingData = $billingAddresses->count() > 0;
        @endphp

        {{-- Datos de facturación (similar bloque CMS7) --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientBillingBlock" aria-expanded="{{ $hasBillingData ? 'true' : 'false' }}" aria-controls="clientBillingBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasBillingData ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Datos de facturación</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    @can('edit', $client)
                        <a href="{{ route('client.edit', $client->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-file-invoice me-1"></i>Actualizar datos fiscales
                        </a>
                    @endcan
                </div>
            </div>
            <div class="collapse {{ $hasBillingData ? 'show' : '' }}" id="clientBillingBlock">
                <div class="card-body border-top">
                @if($billingAddresses->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Razón social</th>
                                    <th>ID Fiscal</th>
                                    <th>Condición fiscal</th>
                                    <th>País</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($billingAddresses as $billing)
                                    <tr>
                                        <td>{{ $billing->name }}</td>
                                        <td>{{ $billing->identification_number ?: '—' }}</td>
                                        <td>{{ $billing->taxStatusType->name ?? '—' }}</td>
                                        <td class="text-muted">{{ $billing->country ? strtoupper((string) $billing->country) : '—' }}</td>
                                        <td class="text-center">
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
        </div>

        {{-- Contactos --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 flex-shrink-0">Contactos</h5>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    @if($linkedContacts->count() > 0)
                        <div class="input-group input-group-merge flex-shrink-1 min-w-0" style="max-width: 220px;">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" class="form-control form-control-sm" id="clientContactsTableSearch" placeholder="{{ __('Search') }}" autocomplete="off" aria-label="{{ __('Search') }}">
                        </div>
                    @endif
                    <div class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0">
                        @can('create', \App\Models\Contact::class)
                            <a href="{{ route('contact.create', ['enterprise_id' => $client->id]) }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="ti ti-user-plus me-1"></i>Nuevo contacto
                            </a>
                        @endcan
                        @can('update', $client)
                            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalLinkExistingContact">
                                <i class="ti ti-link me-1"></i>Vincular existente
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if($linkedContacts->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clientContactsTable"
                            data-detach-url="{{ route('client.detach-contact', $client->id) }}">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Área</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($linkedContacts as $contact)
                                    <tr>
                                        <td class="fw-medium">
                                            {{ $contact->name }}{{ $contact->surname ? ' '.$contact->surname : '' }}
                                        </td>
                                        <td>{{ $contact->email ?: '—' }}</td>
                                        <td>{{ $contact->phone ?: '—' }}</td>
                                        <td>{{ optional($enterpriseDepartments->firstWhere('id', $contact->pivot?->department_id))->name ?? '' }}</td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-inline-flex justify-content-center align-items-center gap-1">
                                                @can('view', $contact)
                                                    <a href="{{ route('contact.show', $contact->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="{{ __('View') }}">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                @endcan
                                                @if ($contact->chatIndexUrl() && (auth()->user()->can('chat.list') || auth()->user()->hasAnyRole(['admin', 'collaborator', 'developer', 'technical'])))
                                                    <a href="{{ $contact->chatIndexUrl() }}" class="btn btn-sm btn-icon btn-text-secondary" title="{{ __('Chat') }}">
                                                        <i class="ti ti-message-chatbot"></i>
                                                    </a>
                                                @endif
                                                @can('view', $contact)
                                                    @if ($contact->mailComposeListUrl())
                                                        <a href="{{ $contact->mailComposeListUrl() }}" class="btn btn-sm btn-icon btn-text-secondary" title="{{ __('Mail') }}">
                                                            <i class="ti ti-mail"></i>
                                                        </a>
                                                    @endif
                                                @endcan
                                                @can('update', $client)
                                                    <button type="button" class="btn btn-sm btn-icon btn-text-danger client-detach-contact-btn" title="Desvincular de este cliente" data-contact-id="{{ $contact->id }}">
                                                        <i class="ti ti-unlink"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
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
                        <p class="text-muted mb-0">Crea uno con <strong>Nuevo contacto</strong> o usá <strong>Vincular existente</strong> para elegir un contacto del equipo en el cuadro de diálogo.</p>
                    </div>
                @endif
            </div>
        </div>

        @can('update', $client)
            <div class="modal fade" id="modalLinkExistingContact" tabindex="-1" aria-labelledby="modalLinkExistingContactLabel" aria-hidden="true"
                data-link-url="{{ route('client.linkable-contacts', $client->id) }}"
                data-attach-url="{{ route('client.attach-contact', $client->id) }}">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLinkExistingContactLabel">Vincular contacto existente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label for="linkContactSearchInput" class="form-label">Buscar por nombre, email o teléfono</label>
                            <input type="search" class="form-control" id="linkContactSearchInput" placeholder="{{ __('Search') }}…" autocomplete="off">
                            <label for="linkContactDepartmentId" class="form-label mt-3">Departamento</label>
                            <select id="linkContactDepartmentId" class="form-select">
                                <option value="">— Sin departamento —</option>
                                @foreach($enterpriseDepartments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <div id="linkContactFeedback" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>
                            <div id="linkContactList" class="list-group list-group-flush mt-3 border rounded"></div>
                            <p id="linkContactEmpty" class="text-muted small d-none mb-0 mt-3">No hay contactos para mostrar. Probá con otra búsqueda.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-primary" id="linkContactSubmitBtn" disabled>Vincular</button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @php
            $hasServices = $services->count() > 0;
        @endphp

        {{-- Servicios --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientServicesBlock" aria-expanded="{{ $hasServices ? 'true' : 'false' }}" aria-controls="clientServicesBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasServices ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Servicios</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    @if($services->count() > 0)
                        <div class="input-group input-group-merge flex-shrink-1 min-w-0" style="max-width: 220px;">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" class="form-control form-control-sm" id="clientServicesTableSearch" placeholder="{{ __('Search') }}" autocomplete="off" aria-label="{{ __('Search') }}">
                        </div>
                    @endif
                    <div class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0">
                        @can('create', \App\Models\Service::class)
                            <a href="{{ route('service.create', ['enterprise_id' => $client->id]) }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="ti ti-plus me-1"></i>Ingresar servicio
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="collapse {{ $hasServices ? 'show' : '' }}" id="clientServicesBlock">
                <div class="card-body border-top">
                @if($services->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="clientServicesTable">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Plan / categoría</th>
                                    <th>Valor</th>
                                    <th>Frecuencia</th>
                                    <th>Próxima</th>
                                    <th class="text-center">Estado</th>
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
                                        <td>
                                            @if($service->price !== null && (float) $service->price != 0.0)
                                                {{ number_format((float) $service->price, 2) }} {{ $curCode }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $frequencyLabel }}</td>
                                        <td>{{ $service->next_billing ? $service->next_billing->format('d/m/Y') : '—' }}</td>
                                        <td class="text-center">{!! $service->status_label !!}</td>
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
        </div>

        @php
            $hasInvoices = $invoices->count() > 0;
            $hasProjects = $activeProjects->count() > 0 || $pastProjects->count() > 0;
        @endphp

        {{-- Facturas (bloque colapsable, estilo ibox CMS7) --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientInvoicesBlock" aria-expanded="{{ $hasInvoices ? 'true' : 'false' }}" aria-controls="clientInvoicesBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasInvoices ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Facturas</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    <span class="badge bg-label-secondary">Saldo pendiente: {{ number_format((float) $invoiceBalanceTotal, 2) }}</span>
                    <span class="badge bg-label-primary">{{ $invoices->count() }} {{ $invoices->count() === 1 ? 'registro' : 'registros' }}</span>
                    <div class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0">
                        @can('invoice.create')
                            <a href="{{ route('invoice.create') }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="ti ti-file-plus me-1"></i>Ingresar factura
                            </a>
                        @endcan
                        @can('invoice.index')
                            <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                <i class="ti ti-list me-1"></i>Listado
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="collapse {{ $hasInvoices ? 'show' : '' }}" id="clientInvoicesBlock">
                <div class="card-body border-top">
                    @if($invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="clientInvoicesTable">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Fecha</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                        <tr>
                                            <td>
                                                @can('view', $invoice)
                                                    <a href="{{ route('invoice.show', $invoice->id) }}" class="text-decoration-none">{{ $invoice->number ?: '—' }}</a>
                                                @else
                                                    {{ $invoice->number ?: '—' }}
                                                @endcan
                                            </td>
                                            <td>{{ $invoice->date ? \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') : '—' }}</td>
                                            <td>{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : '—' }}</td>
                                            @php
                                                $invoiceCurrency = $invoice->currency ?: config('verifactu.default_currency', 'EUR');
                                            @endphp
                                            <td class="text-end text-nowrap">{{ number_format((float) ($invoice->total_amount ?? 0), 2) }} <span class="text-muted">{{ $invoiceCurrency }}</span></td>
                                            <td class="text-end text-nowrap">{{ number_format((float) ($invoice->balance ?? 0), 2) }} <span class="text-muted">{{ $invoiceCurrency }}</span></td>
                                            <td class="text-center">{!! $invoice->status_badge !!}</td>
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
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientProjectsBlock" aria-expanded="{{ $hasProjects ? 'true' : 'false' }}" aria-controls="clientProjectsBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasProjects ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Proyectos</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    @if($activeProjects->count() > 0 || $pastProjects->count() > 0)
                        <div class="input-group input-group-merge flex-shrink-1 min-w-0" style="max-width: 220px;">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" class="form-control form-control-sm" id="clientProjectsTableSearch" placeholder="{{ __('Search') }}" autocomplete="off" aria-label="{{ __('Search') }}">
                        </div>
                    @endif
                    <div class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0">
                        @can('project.create')
                            <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="ti ti-plus me-1"></i>Ingresar proyecto
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="collapse {{ $hasProjects ? 'show' : '' }}" id="clientProjectsBlock">
                <div class="card-body border-top">
                    @if($activeProjects->count() > 0)
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th>Transcurrido</th>
                                        <th>Responsable</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeProjects as $project)
                                        <tr>
                                            <td class="fw-medium">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">{{ $project->name }}</a>
                                            </td>
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
                                            <td class="text-center">
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
                    @elseif($pastProjects->count() === 0)
                        <div class="text-center py-3 mb-4">
                            <i class="ti ti-folder-off display-6 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">Sin proyectos.</p>
                        </div>
                    @endif

                    @if($pastProjects->count() > 0)
                        <h6 class="text-muted mb-3">Proyectos pasados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Proyecto</th>
                                        <th>Transcurrido</th>
                                        <th>Responsable</th>
                                        <th>Fin</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pastProjects->take(6) as $project)
                                        <tr>
                                            <td class="fw-medium">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none text-muted">{{ $project->name }}</a>
                                            </td>
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
                                            <td>{{ $project->date_end ? Carbon\Carbon::parse($project->date_end)->format('d/m/Y') : '—' }}</td>
                                            <td class="text-center">
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
        var clientContactsDt = $('#clientContactsTable').DataTable({
            language: dtLang,
            pageLength: 10,
            lengthChange: false,
            dom: 'rtip',
            ordering: true,
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [
                { targets: -1, orderable: false, searchable: false, className: 'text-center' },
            ],
        });
        var contactsSearchInput = document.getElementById('clientContactsTableSearch');
        if (contactsSearchInput) {
            contactsSearchInput.addEventListener('keyup', function () {
                clientContactsDt.search(this.value).draw();
            });
        }

        var contactsTableEl = document.getElementById('clientContactsTable');
        if (contactsTableEl) {
            contactsTableEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.client-detach-contact-btn');
                if (!btn) {
                    return;
                }
                var runDetach = function () {
                    var detachUrl = contactsTableEl.getAttribute('data-detach-url');
                    var contactId = btn.getAttribute('data-contact-id');
                    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
                    btn.disabled = true;
                    fetch(detachUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ contact_id: parseInt(contactId, 10) }),
                    })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                return { ok: r.ok, body: j };
                            });
                        })
                        .then(function (res) {
                            btn.disabled = false;
                            if (!res.ok || !res.body.success) {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'error',
                                        title: (res.body && res.body.message) ? res.body.message : 'No se pudo desvincular.',
                                        showConfirmButton: true,
                                        showCancelButton: false,
                                        showDenyButton: false,
                                        confirmButtonText: 'OK',
                                        buttonsStyling: false,
                                        customClass: { confirmButton: 'btn btn-primary' },
                                    });
                                } else {
                                    window.alert((res.body && res.body.message) ? res.body.message : 'No se pudo desvincular.');
                                }
                                return;
                            }
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: res.body.message || 'Contacto desvinculado',
                                    showConfirmButton: true,
                                    showCancelButton: false,
                                    showDenyButton: false,
                                    confirmButtonText: 'OK',
                                    buttonsStyling: false,
                                    customClass: { confirmButton: 'btn btn-primary' },
                                }).then(function () {
                                    window.location.reload();
                                });
                            } else {
                                window.location.reload();
                            }
                        })
                        .catch(function () {
                            btn.disabled = false;
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de red al desvincular.',
                                    showConfirmButton: true,
                                    showCancelButton: false,
                                    showDenyButton: false,
                                    confirmButtonText: 'OK',
                                    buttonsStyling: false,
                                    customClass: { confirmButton: 'btn btn-primary' },
                                });
                            } else {
                                window.alert('Error de red al desvincular.');
                            }
                        });
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: '¿Desvincular contacto?',
                        text: 'El contacto no se elimina; solo deja de asociarse a esta empresa.',
                        showCancelButton: true,
                        showDenyButton: false,
                        confirmButtonText: 'Sí, desvincular',
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'btn btn-danger me-2',
                            cancelButton: 'btn btn-label-secondary',
                        },
                    }).then(function (result) {
                        if (!result.isConfirmed) {
                            return;
                        }
                        runDetach();
                    });
                } else {
                    if (!window.confirm('¿Desvincular este contacto de este cliente? El contacto no se elimina; solo deja de asociarse a esta empresa.')) {
                        return;
                    }
                    runDetach();
                }
            });
        }
    }

    if (document.getElementById('clientServicesTable')) {
        var clientServicesDt = $('#clientServicesTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthChange: false,
            dom: 'rtip',
            ordering: true,
            responsive: true,
            columnDefs: [
                { targets: -1, className: 'text-center' },
            ],
        });
        var servicesSearchInput = document.getElementById('clientServicesTableSearch');
        if (servicesSearchInput) {
            servicesSearchInput.addEventListener('keyup', function () {
                clientServicesDt.search(this.value).draw();
            });
        }
    }

    if (document.getElementById('clientInvoicesTable')) {
        $('#clientInvoicesTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthChange: false,
            ordering: true,
            order: [[1, 'desc']],
            responsive: true,
            columnDefs: [
                { targets: -1, className: 'text-center' },
            ],
        });
    }

    var projectsSearchInput = document.getElementById('clientProjectsTableSearch');
    if (projectsSearchInput) {
        projectsSearchInput.addEventListener('keyup', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('#clientProjectsBlock table tbody tr').forEach(function (row) {
                row.style.display = !q || row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    var modalLink = document.getElementById('modalLinkExistingContact');
    if (modalLink) {
        var linkUrl = modalLink.getAttribute('data-link-url');
        var attachUrl = modalLink.getAttribute('data-attach-url');
        var listEl = document.getElementById('linkContactList');
        var emptyEl = document.getElementById('linkContactEmpty');
        var feedbackEl = document.getElementById('linkContactFeedback');
        var searchInput = document.getElementById('linkContactSearchInput');
        var departmentSelect = document.getElementById('linkContactDepartmentId');
        var submitBtn = document.getElementById('linkContactSubmitBtn');
        var selectedId = null;
        var searchTimer = null;

        function hideFeedback() {
            feedbackEl.classList.add('d-none');
            feedbackEl.textContent = '';
        }

        function showFeedback(msg) {
            feedbackEl.textContent = msg;
            feedbackEl.classList.remove('d-none');
        }

        function escHtml(s) {
            if (s === null || s === undefined) {
                return '';
            }
            var d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        function renderContacts(rows) {
            listEl.innerHTML = '';
            emptyEl.classList.toggle('d-none', rows.length > 0);
            if (!rows.length) {
                return;
            }
            rows.forEach(function (row) {
                var full = [row.name, row.surname].filter(Boolean).join(' ').trim();
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action link-contact-option text-start';
                btn.setAttribute('data-contact-id', row.id);
                btn.innerHTML = '<span class="fw-medium">' + escHtml(full || '—') + '</span>' +
                    '<span class="d-block small text-muted">' + escHtml(row.email || '—') + ' · ' + escHtml(row.phone || '—') + '</span>';
                listEl.appendChild(btn);
            });
        }

        function setSelected(id) {
            selectedId = id;
            submitBtn.disabled = !id;
            listEl.querySelectorAll('.link-contact-option').forEach(function (el) {
                el.classList.toggle('active', String(el.getAttribute('data-contact-id')) === String(id));
            });
        }

        function loadContacts(q) {
            hideFeedback();
            listEl.innerHTML = '<div class="text-center text-muted py-3">Cargando…</div>';
            emptyEl.classList.add('d-none');
            setSelected(null);
            var sep = linkUrl.indexOf('?') === -1 ? '?' : '&';
            fetch(linkUrl + sep + 'q=' + encodeURIComponent(q || ''), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, body: j };
                    });
                })
                .then(function (res) {
                    if (!res.ok) {
                        showFeedback((res.body && res.body.message) ? res.body.message : 'No se pudo cargar la lista.');
                        listEl.innerHTML = '';
                        return;
                    }
                    renderContacts(res.body.contacts || []);
                })
                .catch(function () {
                    showFeedback('Error de red al cargar contactos.');
                    listEl.innerHTML = '';
                });
        }

        listEl.addEventListener('click', function (e) {
            var opt = e.target.closest('.link-contact-option');
            if (!opt) {
                return;
            }
            setSelected(parseInt(opt.getAttribute('data-contact-id'), 10));
        });

        modalLink.addEventListener('shown.bs.modal', function () {
            hideFeedback();
            if (searchInput) {
                searchInput.value = '';
            }
            if (departmentSelect) {
                departmentSelect.value = '';
            }
            setSelected(null);
            loadContacts('');
        });

        modalLink.addEventListener('hidden.bs.modal', function () {
            hideFeedback();
            listEl.innerHTML = '';
            emptyEl.classList.add('d-none');
            if (searchInput) {
                searchInput.value = '';
            }
            if (departmentSelect) {
                departmentSelect.value = '';
            }
            setSelected(null);
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var q = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    loadContacts(q);
                }, 350);
            });
        }

        submitBtn.addEventListener('click', function () {
            if (!selectedId) {
                return;
            }
            hideFeedback();
            submitBtn.disabled = true;
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
            fetch(attachUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    contact_id: selectedId,
                    department_id: departmentSelect && departmentSelect.value ? parseInt(departmentSelect.value, 10) : null,
                }),
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, body: j };
                    });
                })
                .then(function (res) {
                    submitBtn.disabled = false;
                    if (!res.ok || !res.body.success) {
                        showFeedback((res.body && res.body.message) ? res.body.message : 'No se pudo vincular.');
                        return;
                    }
                    var modalInstance = bootstrap.Modal.getInstance(modalLink);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: res.body.message || 'Listo',
                            showConfirmButton: true,
                            showCancelButton: false,
                            showDenyButton: false,
                            confirmButtonText: 'OK',
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: 'btn btn-primary',
                            },
                            didOpen: function (popup) {
                                var actions = popup.querySelector('.swal2-actions');
                                if (!actions) {
                                    return;
                                }
                                ['.swal2-deny', '.swal2-cancel'].forEach(function (sel) {
                                    var el = actions.querySelector(sel);
                                    if (el) {
                                        el.style.display = 'none';
                                        el.setAttribute('hidden', 'hidden');
                                    }
                                });
                            },
                        }).then(function () {
                            window.location.reload();
                        });
                    } else {
                        window.location.reload();
                    }
                })
                .catch(function () {
                    submitBtn.disabled = false;
                    showFeedback('Error de red al vincular.');
                });
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
