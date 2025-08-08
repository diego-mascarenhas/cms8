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

    <div class="row">
        <!-- Client Information -->
        <div class="col-xl-4 col-lg-5 col-md-5">
            <!-- Client Details Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section">
                        <div class="d-flex align-items-center flex-column">
                            <div class="user-info w-100">
                                <h4 class="mb-2">{{ $client->name }}</h4>
                                <div class="d-flex flex-wrap">
                                    {!! $client->status_label !!}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="info-container">
                        <h5 class="mb-3">{{ __('Details') }}</h5>
                        <ul class="list-unstyled">
                            @if($client->email)
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Email:</span>
                                <span><a href="mailto:{{ $client->email }}">{{ $client->email }}</a></span>
                            </li>
                            @endif
                            @if($client->phone)
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">{{ __('Phone') }}:</span>
                                <span><a href="tel:{{ $client->phone }}">{{ $client->phone }}</a></span>
                            </li>
                            @endif
                            @if($client->whatsapp)
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">{{ __('WhatsApp') }}:</span>
                                <span><a href="https://wa.me/{{ str_replace(['+', ' ', '-'], '', $client->whatsapp) }}" target="_blank">{{ $client->whatsapp }}</a></span>
                            </li>
                            @endif
                            @if($client->website)
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">{{ __('Website') }}:</span>
                                <span><a href="{{ $client->website }}" target="_blank">{{ $client->website }}</a></span>
                            </li>
                            @endif
                            @if($client->responsible)
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">{{ __('Contact') }}:</span>
                                <span><a href="{{ route('contact.show', $client->responsible->id) }}">{{ $client->responsible->name }}</a></span>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            @if($client->address || $client->locality || $client->province || $client->postal_code)
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Address') }}</h5>
                    <ul class="list-unstyled">
                        @if($client->address)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Address') }}:</span>
                            <span>{{ $client->address }}</span>
                        </li>
                        @endif
                        @if($client->postal_code)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Postal Code') }}:</span>
                            <span>{{ $client->postal_code }}</span>
                        </li>
                        @endif
                        @if($client->locality)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Locality') }}:</span>
                            <span>{{ $client->locality }}</span>
                        </li>
                        @endif
                        @if($client->province)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Province') }}:</span>
                            <span>{{ $client->province }}</span>
                        </li>
                        @endif
                        @if($client->country)
                        <li class="mb-2 pt-1">
                            <span class="fw-medium me-1">{{ __('Country') }}:</span>
                            <span>{{ $client->country }}</span>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif

            <!-- Social Networks -->
            @if($client->data && (
                $client->data->facebook ??
                $client->data->instagram ??
                $client->data->twitter ??
                $client->data->linkedin ??
                $client->data->youtube ??
                $client->data->tiktok ??
                $client->data->pinterest ??
                $client->data->snapchat ?? null
            ))
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Social Networks') }}</h5>
                    <div class="info-container">
                        @if($client->data->facebook ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-facebook text-primary me-2"></i>
                            <a href="{{ $client->data->facebook }}" target="_blank">Facebook</a>
                        </div>
                        @endif
                        @if($client->data->instagram ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-instagram text-danger me-2"></i>
                            <a href="{{ $client->data->instagram }}" target="_blank">Instagram</a>
                        </div>
                        @endif
                        @if($client->data->twitter ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-twitter text-info me-2"></i>
                            <a href="{{ $client->data->twitter }}" target="_blank">Twitter</a>
                        </div>
                        @endif
                        @if($client->data->linkedin ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-linkedin text-primary me-2"></i>
                            <a href="{{ $client->data->linkedin }}" target="_blank">LinkedIn</a>
                        </div>
                        @endif
                        @if($client->data->youtube ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-youtube text-danger me-2"></i>
                            <a href="{{ $client->data->youtube }}" target="_blank">YouTube</a>
                        </div>
                        @endif
                        @if($client->data->tiktok ?? null)
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-brand-tiktok text-dark me-2"></i>
                            <a href="{{ $client->data->tiktok }}" target="_blank">TikTok</a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Projects & Services Section -->
        <div class="col-xl-8 col-lg-7 col-md-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Projects') }} ({{ $client->projects->count() }})</h5>
                    @can('project.create')
                    <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i>{{ __('New Project') }}
                    </a>
                    @endcan
                </div>
                <div class="card-body">
                    @if($client->projects->count() > 0)
                        <div class="row">
                            @foreach($client->projects as $project)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-1">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">
                                                    {{ $project->name }}
                                                </a>
                                            </h6>
                                            @if($project->status)
                                                <span class="badge bg-label-{{ $project->status->color ?? 'secondary' }} rounded-pill">
                                                    {{ $project->status->name }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($project->description)
                                        <p class="card-text small text-muted mb-2">
                                            {{ Str::limit($project->description, 80) }}
                                        </p>
                                        @endif

                                        <div class="small text-muted">
                                            @if($project->responsible)
                                                <div class="mb-1">
                                                    <i class="ti ti-user me-1"></i>
                                                    {{ $project->responsible->name }}
                                                </div>
                                            @endif
                                            @if($project->category)
                                                <div class="mb-1">
                                                    <i class="ti ti-category me-1"></i>
                                                    {{ $project->category->name }}
                                                </div>
                                            @endif
                                            @if($project->price)
                                                <div class="mb-1">
                                                    <i class="ti ti-currency-dollar me-1"></i>
                                                    ${{ number_format($project->price, 2) }}
                                                </div>
                                            @endif
                                            @if($project->date_start)
                                                <div class="mb-1">
                                                    <i class="ti ti-calendar me-1"></i>
                                                    {{ Carbon\Carbon::parse($project->date_start)->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-folder-off display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">{{ __('No ongoing projects') }}</h6>
                            <p class="text-muted mb-3">{{ __('This client has no projects assigned yet.') }}</p>
                            @can('project.create')
                            <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>{{ __('Add Project') }}
                            </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Services') }} ({{ $client->services->count() }})</h5>
                    @can('service.create')
                    <a href="{{ route('service.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i>{{ __('New Service') }}
                    </a>
                    @endcan
                </div>
                <div class="card-body">
                    @if($client->services->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Description') }}</th>
                                        <th>{{ __('Operation') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        <th>{{ __('Frequency') }}</th>
                                        <th>{{ __('Next Billing') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($client->services as $service)
                                    <tr>
                                        <td>{{ Str::limit($service->description, 60) }}</td>
                                        <td><span class="badge bg-label-info">{{ strtoupper($service->operation) }}</span></td>
                                        <td>{{ $service->currency?->code ?? 'EUR' }} {{ number_format($service->price, 2) }}</td>
                                        <td>{{ $service->frequency }} {{ __('months') }}</td>
                                        <td>{{ optional($service->next_billing)->format('Y-m-d') }}</td>
                                        <td>{!! $service->status_label !!}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">{{ __('This client has no services yet.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Invoices') }} ({{ $client->invoices->count() }})</h5>
                    @can('invoice.create')
                    <a href="{{ route('invoice.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i>{{ __('New Invoice') }}
                    </a>
                    @endcan
                </div>
                <div class="card-body">
                    @if($client->invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Billing Address') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                        <th class="text-end">{{ __('Balance') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($client->invoices as $invoice)
                                    <tr>
                                        <td><a href="{{ route('invoice.show', $invoice->id) }}">{{ $invoice->number }}</a></td>
                                        <td>{{ \Carbon\Carbon::parse($invoice->date)->format('Y-m-d') }}</td>
                                        <td>
                                            @if($invoice->billingAddress)
                                                {{ $invoice->billingAddress->name }}<br>
                                                <small class="text-muted">{{ $invoice->billingAddress->address }}, {{ $invoice->billingAddress->locality }}</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($invoice->total_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($invoice->balance, 2) }}</td>
                                        <td>{!! $invoice->status_label !!}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">{{ __('This client has no invoices yet.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
