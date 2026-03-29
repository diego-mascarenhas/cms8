@extends('layouts/layoutMaster')

@section('title', __('Tiendas'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Tiendas') }}</h4>
            <p class="text-muted mb-0">{{ __('Gestiona las sucursales de tu negocio') }}</p>
            @php
                $storesBusinessTeam = auth()->user()->currentTeam ?? auth()->user()->teams->first();
                $storesShowBusinessHint = $storesBusinessTeam
                    && auth()->user()->can('update', $storesBusinessTeam)
                    && ! $storesBusinessTeam->hasCompletedBusinessConfiguration();
            @endphp
            @if($storesShowBusinessHint)
                <p class="text-muted small mb-0 mt-1">{{ __('For best results, configure your business details before creating stores.') }}</p>
            @endif
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('store.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-plus me-1"></i> {{ __('Crear tienda') }}
            </a>
        </div>
    </div>

    @include('partials.business-configuration-prompt')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Address') }}</th>
                            <th class="text-center">{{ __('Status') }}</th>
                            <th class="text-center">{{ __('Principal') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                            <tr>
                                <td>{{ $store->name }}</td>
                                <td>{{ $store->code ?? '-' }}</td>
                                <td>{{ $store->address ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $store->status ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ $store->status ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($store->is_main)
                                        <span class="badge bg-label-primary">{{ __('Yes') }}</span>
                                    @else
                                        <span class="badge bg-label-secondary">{{ __('No') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <a href="{{ route('store.show', $store->id) }}" class="text-body" title="{{ __('Ver') }}">
                                            <i class="ti ti-eye ti-sm me-2"></i>
                                        </a>
                                        <a href="{{ route('store.edit', $store->id) }}" class="text-body" title="{{ __('Edit') }}">
                                            <i class="ti ti-pencil ti-sm me-2"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">{{ __('No stores yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
