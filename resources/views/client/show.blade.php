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
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <div class="d-flex flex-column justify-content-center min-w-0">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Clients') }}/</span> {{ $client->name }}</h4>
            <p class="text-muted mb-0">{{ __('Detailed client information') }}</p>
        </div>
        <div class="d-flex align-items-center flex-shrink-0 gap-3">
            @if ($client->trashed())
                @can('update', $client)
                    <form method="POST" action="{{ route('client.restore', $client->id) }}" onsubmit="return confirm('La empresa vuelve al listado. Los proyectos y las facturas siguen en la empresa con la que se fusionó.');">
                        @csrf
                        <button type="submit" class="btn btn-primary waves-effect waves-light">
                            <i class="ti ti-arrow-back-up me-1"></i>Restaurar
                        </button>
                    </form>
                @endcan
            @else
                @can('update', $client)
                    <button type="button" class="btn btn-label-secondary waves-effect" data-bs-toggle="modal" data-bs-target="#modalMergeEnterprise">
                        <i class="ti ti-git-merge me-1"></i>Fusionar
                    </button>
                @endcan
                @can('edit', $client)
                    <a href="{{ route('client.edit', $client->id) }}" class="btn btn-primary waves-effect waves-light">
                        <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
                    </a>
                @endcan
            @endif
        </div>
    </div>

    @if ($client->trashed())
        <div class="alert alert-warning" role="alert">
            Esta empresa está archivada.
            @if ($mergedInto)
                Se fusionó en <a href="{{ route('empresas.show', $mergedInto->id) }}">{{ $mergedInto->name }}</a>.
            @endif
        </div>
    @endif

    <div class="row row-cols-4 g-3 mb-4">
        @foreach($headlineCards as $card)
            <div class="col">
                <div class="card h-100 mb-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-1">
                            <div class="avatar avatar-xs flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-{{ $card['tone'] }}">
                                    <i class="ti {{ $card['icon'] }} ti-xs"></i>
                                </span>
                            </div>
                            <span class="small text-truncate">{{ $card['kicker'] }}</span>
                        </div>
                        <div class="fw-semibold text-nowrap">{{ $card['value'] }}</div>
                        <small class="text-muted text-truncate d-block">{{ $card['hint'] }}</small>
                    </div>
                </div>
            </div>
        @endforeach
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
                                        <td>{{ $billing->taxStatusType?->label() ?: '—' }}</td>
                                        <td>{{ $billing->countryLabel($billingCountryFallback) }}</td>
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

            <div class="modal fade" id="modalMergeEnterprise" tabindex="-1" aria-labelledby="modalMergeEnterpriseLabel" aria-hidden="true"
                data-candidates-url="{{ route('client.merge-candidates', $client->id) }}"
                data-preview-url="{{ route('client.merge-preview', $client->id) }}"
                data-merge-url="{{ route('client.merge', $client->id) }}"
                data-current-stripe="{{ $client->getStripeCustomerId() ? '1' : '0' }}">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalMergeEnterpriseLabel">Fusionar empresa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">La que tiene código <span class="font-monospace">cus_</span> se conserva. La otra se archiva y sus proyectos, facturas y contactos pasan a esa.</p>
                            <label for="mergeEnterpriseSearchInput" class="form-label">Buscar la empresa duplicada</label>
                            <input type="search" class="form-control" id="mergeEnterpriseSearchInput" placeholder="Nombre, código o email…" autocomplete="off">
                            <div id="mergeEnterpriseFeedback" class="alert d-none mt-3 mb-0" role="alert"></div>
                            <div id="mergeEnterpriseList" class="list-group list-group-flush mt-3 border rounded d-none"></div>
                            <div id="mergeEnterprisePreview" class="d-none mt-3">
                                <p id="mergeEnterprisePreviewMessage" class="mb-2"></p>
                                <ul id="mergeEnterprisePreviewLines" class="mb-0"></ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-primary" id="mergeEnterpriseSubmitBtn" disabled>Fusionar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        @php
            $hasServices = $services->count() > 0;
            $hasSubscriptions = $subscriptions->count() > 0;
            $hasConsumptions = $consumptions->count() > 0;
            $stripeCustomerId = trim((string) $client->code);
            $stripeCustomerUrl = null;
            if (str_starts_with($stripeCustomerId, 'cus_'))
            {
                $stripeLivemode = $subscriptions
                    ->concat($consumptions)
                    ->map(fn ($row) => data_get($row->raw_payload, 'livemode'))
                    ->filter(fn ($mode) => is_bool($mode));
                $stripeBase = ($stripeLivemode->isNotEmpty() && $stripeLivemode->every(fn (bool $mode): bool => $mode === false))
                    ? 'https://dashboard.stripe.com/test/customers/'
                    : 'https://dashboard.stripe.com/customers/';
                $stripeCustomerUrl = $stripeBase.$stripeCustomerId;
            }
        @endphp

        {{-- Suscripciones (Stripe). Los consumos van en una tarjeta aparte. --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientSubscriptionsBlock" aria-expanded="{{ $hasSubscriptions ? 'true' : 'false' }}" aria-controls="clientSubscriptionsBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasSubscriptions ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Suscripciones</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0" style="overflow-x: auto;">
                    <span class="badge bg-label-primary">{{ $subscriptions->count() }} {{ $subscriptions->count() === 1 ? 'suscripción' : 'suscripciones' }}</span>
                    @if($stripeCustomerUrl)
                        <a href="{{ $stripeCustomerUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary text-nowrap">
                            <i class="ti ti-brand-stripe me-1"></i>Stripe
                        </a>
                    @endif
                </div>
            </div>
            <div class="collapse {{ $hasSubscriptions ? 'show' : '' }}" id="clientSubscriptionsBlock">
                <div class="card-body border-top">
                    @php
                        $subscriptionDiscountLabels = $subscriptions->map(
                            fn ($subscription) => $subscription->clientDiscountLabel($servicesBySubscription->get($subscription->id))
                        );
                        $hasSubscriptionDiscount = $subscriptionDiscountLabels->contains(fn (string $label): bool => $label !== '');
                    @endphp
                    @if($hasSubscriptions)
                        <div class="table-responsive mb-4">
                            <table class="table table-hover mb-0" id="clientSubscriptionsTable">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th class="text-end">Importe</th>
                                        @if($hasSubscriptionDiscount)
                                            <th class="text-end">Descuento</th>
                                        @endif
                                        <th class="text-center">Frecuencia</th>
                                        <th class="text-end">Próxima</th>
                                        <th>Medio de pago</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subscriptions as $subscription)
                                        @php
                                            $linkedService = $servicesBySubscription->get($subscription->id);
                                            $hosting = $hostingsBySubscription->get($subscription->id);
                                            $discountLabel = $subscriptionDiscountLabels[$loop->index] ?? '';
                                            $subscriptionHref = null;
                                            if ($hosting && auth()->user()?->can('access-infrastructure-modules'))
                                            {
                                                $subscriptionHref = route('domain.show', $hosting->id);
                                            }
                                            elseif ($linkedService)
                                            {
                                                $subscriptionHref = route('service.show', $linkedService->id);
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($subscriptionHref)
                                                    <a href="{{ $subscriptionHref }}" class="text-decoration-none">{{ $subscription->clientFacingName() }}</a>
                                                @else
                                                    {{ $subscription->clientFacingName() }}
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if($subscription->amount_total !== null)
                                                    {{ number_format((float) $subscription->amount_total, 2) }} {{ strtoupper((string) $subscription->price_currency) }}
                                                @endif
                                            </td>
                                            @if($hasSubscriptionDiscount)
                                                <td class="text-end text-nowrap">{{ $discountLabel }}</td>
                                            @endif
                                            <td class="text-center">{{ $subscription->clientFrequencyLabel() }}</td>
                                            <td class="text-end text-nowrap">
                                                @if($subscription->current_period_end)
                                                    {{ strtolower((string) $subscription->status) === 'not_started'
                                                        ? $subscription->current_period_end->copy()->timezone('Europe/Madrid')->format('d/m/Y')
                                                        : $subscription->current_period_end->format('d/m/Y') }}
                                                @endif
                                            </td>
                                            <td>{{ $subscription->clientPaymentMethodLabel() }}</td>
                                            <td class="text-center">{!! $subscription->status_badge !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Sin suscripciones.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex flex-nowrap justify-content-between align-items-center gap-2 py-3">
                <span class="d-inline-flex align-items-center gap-2 text-body fw-semibold user-select-none flex-shrink-0" role="button" tabindex="0" data-bs-toggle="collapse" data-bs-target="#clientConsumptionsBlock" aria-expanded="{{ $hasConsumptions ? 'true' : 'false' }}" aria-controls="clientConsumptionsBlock" style="cursor: pointer;">
                    <i class="ti {{ $hasConsumptions ? 'ti-chevron-up' : 'ti-chevron-down' }} collapse-chevron"></i>
                    <span>Consumos</span>
                </span>
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto min-w-0">
                    <span class="badge bg-label-secondary">{{ $consumptions->count() }} {{ $consumptions->count() === 1 ? 'consumo' : 'consumos' }}</span>
                </div>
            </div>
            <div class="collapse {{ $hasConsumptions ? 'show' : '' }}" id="clientConsumptionsBlock">
                <div class="card-body border-top">
                    @if($hasConsumptions)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="clientConsumptionsTable">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Concepto</th>
                                        <th class="text-end">Importe</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($consumptions as $consumption)
                                        @php
                                            $localInvoice = $consumptionInvoices->get($consumption->external_id);
                                            $consumptionDate = $consumption->invoice_created_at;
                                        @endphp
                                        <tr>
                                            <td data-order="{{ $consumptionDate ? $consumptionDate->format('Y-m-d') : '' }}">{{ $consumptionDate ? $consumptionDate->format('d/m/Y') : '' }}</td>
                                            <td>
                                                @if($localInvoice)
                                                    @can('view', $localInvoice)
                                                        <a href="{{ route('invoice.show', $localInvoice->id) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($consumption->consumptionSummary(), 96) }}</a>
                                                    @else
                                                        {{ \Illuminate\Support\Str::limit($consumption->consumptionSummary(), 96) }}
                                                    @endcan
                                                @else
                                                    {{ \Illuminate\Support\Str::limit($consumption->consumptionSummary(), 96) }}
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if($consumption->total !== null)
                                                    {{ number_format((float) $consumption->total, 2) }} {{ strtoupper((string) $consumption->currency) }}
                                                @endif
                                            </td>
                                            <td class="text-center">{!! $consumption->status_badge !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 mb-4">
                            <i class="ti ti-receipt-off display-6 text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">Sin consumos.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

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
                                        $plan = optional($service->category)->name ?? '—';
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
                    @if($invoices->count() > 0)
                        <div class="input-group input-group-merge flex-shrink-1 min-w-0" style="max-width: 220px;">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" class="form-control form-control-sm" id="clientInvoicesTableSearch" placeholder="{{ __('Search') }}" autocomplete="off" aria-label="{{ __('Search') }}">
                        </div>
                    @endif
                    <div class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0">
                        @can('create', \App\Models\Invoice::class)
                            <a href="{{ route('invoice.create', ['enterprise_id' => $client->id]) }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="ti ti-plus me-1"></i>Ingresar factura
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
                                        <th class="d-none">ID</th>
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
                                            <td class="d-none">{{ $invoice->id }}</td>
                                            <td data-order="{{ $invoice->number ?? '' }}">
                                                @can('view', $invoice)
                                                    <a href="{{ route('invoice.show', $invoice->id) }}" class="text-decoration-none">{{ $invoice->number ?: '—' }}</a>
                                                @else
                                                    {{ $invoice->number ?: '—' }}
                                                @endcan
                                            </td>
                                            <td data-order="{{ $invoice->date ? \Carbon\Carbon::parse($invoice->date)->format('Y-m-d') : '' }}">{{ $invoice->date ? \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') : '—' }}</td>
                                            <td @if($invoice->due_date) data-order="{{ \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') }}" @endif>{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : '' }}</td>
                                            <td class="text-end text-nowrap">{{ number_format((float) ($invoice->total_amount ?? 0), 2) }} <span class="text-muted">{{ $invoice->currency_code }}</span></td>
                                            <td class="text-end text-nowrap">{{ number_format((float) ($invoice->balance ?? 0), 2) }} <span class="text-muted">{{ $invoice->currency_code }}</span></td>
                                            <td class="text-center" data-order="{{ $invoice->listStatusSortPriority() }}">{!! $invoice->status_badge !!}</td>
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
                        @can('create', App\Models\Project::class)
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

    if (document.getElementById('clientSubscriptionsTable')) {
        $('#clientSubscriptionsTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthChange: false,
            dom: 'rtip',
            ordering: true,
            order: [],
            responsive: true,
            columnDefs: [
                { targets: 'text-end', className: 'text-end' },
                { targets: 'text-center', className: 'text-center' },
            ],
        });
    }

    if (document.getElementById('clientConsumptionsTable')) {
        $('#clientConsumptionsTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthChange: false,
            dom: 'rtip',
            ordering: true,
            order: [[0, 'desc']],
            responsive: true,
            columnDefs: [
                { targets: -1, className: 'text-center' },
            ],
        });
    }

    if (document.getElementById('clientInvoicesTable')) {
        var clientInvoicesDt = $('#clientInvoicesTable').DataTable({
            language: dtLang,
            pageLength: 5,
            lengthChange: false,
            dom: 'rtip',
            ordering: true,
            order: [[6, 'asc'], [1, 'desc']],
            responsive: true,
            columnDefs: [
                { targets: 0, visible: false, searchable: false },
                { targets: -1, className: 'text-center' },
            ],
        });
        var invoicesSearchInput = document.getElementById('clientInvoicesTableSearch');
        if (invoicesSearchInput) {
            invoicesSearchInput.addEventListener('input', function () {
                clientInvoicesDt.search(this.value).draw();
            });
        }
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

    var modalMerge = document.getElementById('modalMergeEnterprise');
    if (modalMerge) {
        var mergeCurrentHasStripe = modalMerge.getAttribute('data-current-stripe') === '1';
        var mergeCandidatesUrl = modalMerge.getAttribute('data-candidates-url');
        var mergePreviewUrl = modalMerge.getAttribute('data-preview-url');
        var mergeUrl = modalMerge.getAttribute('data-merge-url');
        var mergeSearch = document.getElementById('mergeEnterpriseSearchInput');
        var mergeList = document.getElementById('mergeEnterpriseList');
        var mergePreview = document.getElementById('mergeEnterprisePreview');
        var mergePreviewMessage = document.getElementById('mergeEnterprisePreviewMessage');
        var mergePreviewLines = document.getElementById('mergeEnterprisePreviewLines');
        var mergeFeedback = document.getElementById('mergeEnterpriseFeedback');
        var mergeSubmit = document.getElementById('mergeEnterpriseSubmitBtn');
        var mergeSelectedId = null;
        var mergeLines = [];
        var mergeTimer = null;

        function mergeEsc(value) {
            var holder = document.createElement('div');
            holder.textContent = value === null || value === undefined ? '' : String(value);
            return holder.innerHTML;
        }

        function mergeFeedbackHide() {
            mergeFeedback.classList.add('d-none');
            mergeFeedback.textContent = '';
        }

        function mergeFeedbackShow(message, tone) {
            mergeFeedback.className = 'alert alert-' + tone + ' mt-3 mb-0';
            mergeFeedback.textContent = message;
        }

        function mergeResetPreview() {
            mergeSelectedId = null;
            mergeSubmit.disabled = true;
            mergePreview.classList.add('d-none');
            mergePreviewMessage.textContent = '';
            mergePreviewLines.innerHTML = '';
            mergeLines = [];
        }

        function mergeDialogHtml(message, lines) {
            var html = '<p class="mb-2">' + mergeEsc(message) + '</p>';
            if (!lines.length) {
                return html;
            }
            html += '<ul class="text-start mb-0">';
            lines.forEach(function (line) {
                html += '<li>' + mergeEsc(line) + '</li>';
            });
            html += '</ul>';
            return html;
        }

        function mergeDialog(options) {
            var modal = window.bootstrap && bootstrap.Modal.getInstance(modalMerge);
            if (modal && modal._focustrap) {
                modal._focustrap.deactivate();
            }
            return Swal.fire({
                icon: options.icon,
                title: options.title,
                html: mergeDialogHtml(options.message, options.lines || []),
                showCancelButton: !!options.showCancel,
                confirmButtonText: options.confirmText,
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                focusCancel: true,
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-label-secondary',
                },
                didOpen: function () {
                    var container = Swal.getContainer();
                    if (container) {
                        container.style.zIndex = '20000';
                    }
                },
            }).then(function (result) {
                if (modal && modal._focustrap) {
                    modal._focustrap.activate();
                }
                return result;
            });
        }

        function loadMergePreview(enterpriseId) {
            mergeResetPreview();
            mergeFeedbackHide();
            fetch(mergePreviewUrl + '?enterprise_id=' + encodeURIComponent(enterpriseId), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) { return response.json(); })
                .then(function (body) {
                    mergePreview.classList.remove('d-none');
                    mergePreviewMessage.textContent = body.message || '';
                    mergeLines = body.lines || [];
                    mergeLines.forEach(function (line) {
                        var item = document.createElement('li');
                        item.textContent = line;
                        mergePreviewLines.appendChild(item);
                    });
                    if (body.blocked) {
                        mergeFeedbackShow(body.message || 'No se puede fusionar.', 'warning');
                        return;
                    }
                    mergeSelectedId = enterpriseId;
                    mergeSubmit.disabled = false;
                })
                .catch(function () {
                    mergeFeedbackShow('No se pudo preparar la fusión.', 'danger');
                });
        }

        function renderMergeCandidates(rows) {
            mergeList.innerHTML = '';
            if (!rows.length) {
                mergeList.classList.add('d-none');
                return;
            }
            mergeList.classList.remove('d-none');
            rows.forEach(function (row) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.innerHTML = '<span class="fw-medium">' + mergeEsc(row.name) + '</span>'
                    + (row.stripe && !mergeCurrentHasStripe ? ' <span class="badge bg-label-success">Se conserva</span>' : '')
                    + '<br><small class="text-muted">' + mergeEsc(row.subtitle) + '</small>';
                button.addEventListener('click', function () {
                    mergeList.querySelectorAll('.active').forEach(function (item) {
                        item.classList.remove('active');
                    });
                    button.classList.add('active');
                    loadMergePreview(row.id);
                });
                mergeList.appendChild(button);
            });
        }

        mergeSearch.addEventListener('input', function () {
            var term = mergeSearch.value.trim();
            mergeResetPreview();
            mergeFeedbackHide();
            clearTimeout(mergeTimer);
            if (term.length < 2) {
                mergeList.classList.add('d-none');
                mergeList.innerHTML = '';
                return;
            }
            mergeTimer = setTimeout(function () {
                fetch(mergeCandidatesUrl + '?q=' + encodeURIComponent(term), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (response) { return response.json(); })
                    .then(function (body) { renderMergeCandidates(body.enterprises || []); })
                    .catch(function () { mergeFeedbackShow('No se pudo buscar.', 'danger'); });
            }, 300);
        });

        function mergePost() {
            mergeSubmit.disabled = true;
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            fetch(mergeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ enterprise_id: mergeSelectedId }),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        mergeSubmit.disabled = false;
                        mergeFeedbackShow((result.body && result.body.message) ? result.body.message : 'No se pudo fusionar.', 'danger');
                        return;
                    }
                    var doneLines = (result.body && result.body.lines) ? result.body.lines : mergeLines;
                    mergeDialog({
                        icon: 'success',
                        title: 'Valores fusionados',
                        message: (result.body && result.body.message) ? result.body.message : 'Empresas fusionadas.',
                        lines: doneLines,
                        confirmText: 'Ver empresa',
                    }).then(function () {
                        window.location.href = result.body.redirect;
                    });
                })
                .catch(function () {
                    mergeSubmit.disabled = false;
                    mergeFeedbackShow('Error de red al fusionar.', 'danger');
                });
        }

        mergeSubmit.addEventListener('click', function () {
            if (!mergeSelectedId) {
                return;
            }
            mergeDialog({
                icon: 'warning',
                title: 'Confirmá la fusión',
                message: mergePreviewMessage.textContent || 'Se archiva la empresa duplicada.',
                lines: mergeLines,
                showCancel: true,
                confirmText: 'Fusionar',
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }
                mergePost();
            });
        });

        modalMerge.addEventListener('hidden.bs.modal', function () {
            mergeSearch.value = '';
            mergeList.innerHTML = '';
            mergeList.classList.add('d-none');
            mergeResetPreview();
            mergeFeedbackHide();
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
