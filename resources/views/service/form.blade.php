@extends('layouts/layoutMaster')
@section('title', isset($data) ? __('Edit Service') : __('Create Service'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Services') }}/</span>
            {{ isset($data) ? __('Edit') : __('Create') }}
        </h4>
        <p class="text-muted">{{ __('Track your clients\' services') }}</p>
    </div>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

<form id="serviceForm" method="POST" action="{{ isset($data) ? route('service.update', $data->id) : route('service.store') }}">
        @csrf
        @if(isset($data))
            @method('PUT')
            <input type="hidden" name="id" value="{{ $data->id }}">
        @endif

        <!-- Basic Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Basic Information') }}</h5>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-12 col-md-6">
                        <x-team-users-select
                            id="responsible_id"
                            label="Asesor"
                            :selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
                            show-null="false"
                        />
                    </div>

                    <div class="col-12 col-md-6">
                        @php
                            $selectedCategoryId = (string) old('category_id', isset($data) ? ($data->category_id ?? '') : '');
                            $serviceCategoryOptions = collect($categoryOptions ?? []);
                        @endphp
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="category_id" class="form-label mb-0 me-1">{{ __('Tipo de plan') }}</label>
                                @can('viewAny', \App\Models\Category::class)
                                    <span class="d-inline-flex align-items-center lh-1">
                                        @livewire(\App\Livewire\ModuleCategoriesManagerModal::class, [
                                            'moduleKey' => 'services',
                                            'linkedSelectId' => 'category_id',
                                        ], key('service-form-cat-mgr-services'))
                                    </span>
                                @endcan
                            </div>
                            <select
                                id="category_id"
                                name="category_id"
                                class="form-select select2-service-category @error('category_id') is-invalid @enderror"
                                data-placeholder="{{ __('Uncategorized') }}"
                                data-allow-clear="true"
                                data-module-key="services"
                                data-empty-text="{{ __('Uncategorized') }}"
                                data-show-empty-option="1"
                                data-allow-empty-select="1"
                            >
                                <option value="">{{ __('Uncategorized') }}</option>
                                @foreach ($serviceCategoryOptions->groupBy(fn ($option) => $option['group'] ?? '') as $groupLabel => $groupOptions)
                                    @if ($groupLabel !== '')
                                        <optgroup label="{{ $groupLabel }}">
                                    @endif
                                    @foreach ($groupOptions as $categoryOption)
                                        <option value="{{ $categoryOption['id'] }}" {{ $selectedCategoryId === (string) $categoryOption['id'] ? 'selected' : '' }}>
                                            {{ $categoryOption['name'] }}
                                        </option>
                                    @endforeach
                                    @if ($groupLabel !== '')
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            @error('category_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <x-client-select
                            id="enterprise_id"
                            label="{{ __('Empresa') }} (*)"
                            :selected="old('enterprise_id', isset($data) ? $data->enterprise_id : ($enterprise_id ?? ''))"
                            :allowNull="false"
                        />
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="subscription_id" class="form-label mb-0">{{ __('Suscripción') }}</label>
                            </div>
                            <select id="subscription_id" name="subscription_id" class="form-select select2-subscription" data-allow-clear="true" data-placeholder="{{ __('Subscripción local') }}">
                                <option value="" {{ old('subscription_id', isset($data) ? $data->subscription_id : '') === '' ? 'selected' : '' }}>{{ __('Subscripción local') }}</option>
                                @php($serviceSyncOptions = $serviceSyncs ?? $stripeSubscriptions ?? collect())
                                @foreach($serviceSyncOptions as $serviceSync)
                                        <option value="{{ $serviceSync->id }}" {{ old('subscription_id', isset($data) ? $data->subscription_id : '') == $serviceSync->id ? 'selected' : '' }}>
                                            {{ $serviceSync->customer_name ?: $serviceSync->customer_email }} — {{ $serviceSync->plan_name ?: '—' }} ({{ $serviceSync->status ?? '—' }})
                                        </option>
                                    @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">
                                {{ __('Suscripciones de clientes en Stripe.') }}
                                @can('access-billing-modules')
                                <a href="{{ route('subscription.index') }}" target="_blank" rel="noopener">{{ __('Ver todas') }}</a>
                                @endcan
                            </small>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="operation" class="form-label mb-0">{{ __('Operación') }}</label>
                            </div>
                            <select id="operation" name="operation" class="form-select" required>
                                <option value="sell" {{ old('operation', isset($data) ? $data->operation : 'sell') === 'sell' ? 'selected' : '' }}>{{ __('Venta') }}</option>
                                <option value="buy" {{ old('operation', isset($data) ? $data->operation : 'sell') === 'buy' ? 'selected' : '' }}>{{ __('Compra') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
                                <label for="status" class="form-label mb-0">{{ __('Status') }}</label>
                            </div>
                            <select id="status" name="status" class="form-select" required>
                                <option value="">{{ __('Select Status') }}</option>
                                <option value="1" {{ isset($data) && $data->status == 1 ? 'selected' : '' }}>Suspendido</option>
                                <option value="2" {{ isset($data) && $data->status == 2 ? 'selected' : '' }}>Suspender</option>
                                <option value="3" {{ isset($data) && $data->status == 3 ? 'selected' : '' }}>Activar</option>
                                <option value="4" {{ isset($data) && $data->status == 4 ? 'selected' : '' }}>Activo</option>
                                <option value="5" {{ isset($data) && $data->status == 5 ? 'selected' : '' }}>Migrar</option>
                                <option value="6" {{ isset($data) && $data->status == 6 ? 'selected' : '' }}>Migrando</option>
                                <option value="7" {{ isset($data) && $data->status == 7 ? 'selected' : '' }}>Delegar</option>
                                <option value="8" {{ isset($data) && $data->status == 8 ? 'selected' : '' }}>Analizar</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ isset($data) ? $data->description : '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Financial Information') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="currency_id" class="form-label">{{ __('Currency') }} ({{ __('optional') }})</label>
                            <select id="currency_id" name="currency_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">{{ __('Select Currency') }}</option>

                                <option value="840" {{ isset($data) && $data->currency_id == 840 ? 'selected' : '' }}>USD - United States Dollar</option>
                                <option value="978" {{ isset($data) && $data->currency_id == 978 ? 'selected' : '' }}>EUR - Euro</option>
                            </select>
                            <small class="text-muted">{{ __('If not selected, system default will be used') }}</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="price" class="form-label">{{ __('Price') }}</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" value="{{ isset($data) ? $data->price : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="discount" class="form-label">{{ __('Discount') }} (%)</label>
                            <input type="number" id="discount" name="discount" class="form-control" step="1" max="30" value="{{ isset($data) ? $data->discount : '0' }}">
                            <small class="text-muted">{{ __('Maximum discount allowed: 30%') }}</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="frequency" class="form-label">{{ __('Frequency') }} ({{ __('months') }})</label>
                            <input type="number" id="frequency" name="frequency" class="form-control" value="{{ isset($data) ? $data->frequency : '1' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="next_billing" class="form-label">{{ __('Next Billing Date') }}</label>
                            <input type="text" id="next_billing" name="next_billing" class="form-control flatpickr-date" value="{{ isset($data) && $data->next_billing ? $data->next_billing->format('Y-m-d') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expires_at" class="form-label">{{ __('Expiration Date') }}</label>
                            <input type="text" id="expires_at" name="expires_at" class="form-control flatpickr-date" value="{{ isset($data) && $data->expires_at ? $data->expires_at->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain & Hosting Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Domain & Hosting') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_domain" class="form-label">{{ __('Domain') }}</label>
                            <input type="text" id="data_domain" name="data[domain]" class="form-control" value="{{ isset($data) ? ($data->data['domain'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_ip" class="form-label">{{ __('IP Address') }}</label>
                            <input type="text" id="data_ip" name="data[ip]" class="form-control" value="{{ isset($data) ? ($data->data['ip'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_server_id" class="form-label">{{ __('Server') }}</label>
                            <select id="data_server_id" name="data[server_id]" class="form-select">
                                <option value="">{{ __('Select Server') }}</option>
                                @foreach(\App\Models\Server::orderBy('name')->get() as $server)
                                    <option value="{{ $server->id }}" {{ isset($data) && isset($data->data['server_id']) && $data->data['server_id'] == $server->id ? 'selected' : '' }}>
                                        {{ $server->name }} ({{ $server->ip }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_plan" class="form-label">{{ __('Plan') }}</label>
                            <input type="text" id="data_plan" name="data[plan]" class="form-control" value="{{ isset($data) ? ($data->data['plan'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_email_service" class="form-label">{{ __('Email Service') }}</label>
                            <select id="data_email_service" name="data[email_service]" class="form-select">
                                <option value="1" {{ isset($data) && isset($data->data['email_service']) && $data->data['email_service'] == 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                <option value="0" {{ isset($data) && isset($data->data['email_service']) && $data->data['email_service'] == 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DNS Configuration Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('DNS Configuration') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_dns" class="form-label">{{ __('DNS Servers') }}</label>
                            <input type="text" id="data_dns" name="data[dns]" class="form-control" value="{{ isset($data) ? ($data->data['dns'] ?? '') : '' }}">
                            <small class="text-muted">{{ __('Separate multiple values with commas') }}</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_spf" class="form-label">{{ __('SPF Record') }}</label>
                            <input type="text" id="data_spf" name="data[spf]" class="form-control" value="{{ isset($data) ? ($data->data['spf'] ?? '') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technology Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Technology Details') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_os" class="form-label">{{ __('Operating System') }}</label>
                            <select id="data_os" name="data[os]" class="form-select">
                                <option value="">{{ __('Select Operating System') }}</option>
                                <option value="CentOS 7" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'CentOS 7' ? 'selected' : '' }}>CentOS 7</option>
                                <option value="CentOS 8" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'CentOS 8' ? 'selected' : '' }}>CentOS 8</option>
                                <option value="AlmaLinux 8" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'AlmaLinux 8' ? 'selected' : '' }}>AlmaLinux 8</option>
                                <option value="Ubuntu 18.04" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Ubuntu 18.04' ? 'selected' : '' }}>Ubuntu 18.04</option>
                                <option value="Ubuntu 20.04" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Ubuntu 20.04' ? 'selected' : '' }}>Ubuntu 20.04</option>
                                <option value="Ubuntu 22.04" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Ubuntu 22.04' ? 'selected' : '' }}>Ubuntu 22.04</option>
                                <option value="Debian 10" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Debian 10' ? 'selected' : '' }}>Debian 10</option>
                                <option value="Debian 11" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Debian 11' ? 'selected' : '' }}>Debian 11</option>
                                <option value="Windows Server 2019" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Windows Server 2019' ? 'selected' : '' }}>Windows Server 2019</option>
                                <option value="Windows Server 2022" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Windows Server 2022' ? 'selected' : '' }}>Windows Server 2022</option>
                                <option value="Other" {{ isset($data) && isset($data->data['os']) && $data->data['os'] == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_control_panel" class="form-label">{{ __('Control Panel') }}</label>
                            <select id="data_control_panel" name="data[control_panel]" class="form-select">
                                <option value="">{{ __('Select Control Panel') }}</option>
                                <option value="cPanel" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'cPanel' ? 'selected' : '' }}>cPanel</option>
                                <option value="Plesk" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'Plesk' ? 'selected' : '' }}>Plesk</option>
                                <option value="aaPanel" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'aaPanel' ? 'selected' : '' }}>aaPanel</option>
                                <option value="Other" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_db_version" class="form-label">{{ __('Database Version') }}</label>
                            <select id="data_db_version" name="data[db_version]" class="form-select">
                                <option value="">{{ __('Select Database Version') }}</option>
                                <option value="MySQL 5.7" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MySQL 5.7' ? 'selected' : '' }}>MySQL 5.7</option>
                                <option value="MySQL 8.0" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MySQL 8.0' ? 'selected' : '' }}>MySQL 8.0</option>
                                <option value="MariaDB 10.5" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MariaDB 10.5' ? 'selected' : '' }}>MariaDB 10.5</option>
                                <option value="MariaDB 10.6" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MariaDB 10.6' ? 'selected' : '' }}>MariaDB 10.6</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_php_version" class="form-label">{{ __('PHP Version') }}</label>
                            <select id="data_php_version" name="data[php_version]" class="form-select">
                                <option value="">{{ __('Select PHP Version') }}</option>
                                <option value="5.6" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '5.6' ? 'selected' : '' }}>PHP 5.6</option>
                                <option value="7.0" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '7.0' ? 'selected' : '' }}>PHP 7.0</option>
                                <option value="7.1" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '7.1' ? 'selected' : '' }}>PHP 7.1</option>
                                <option value="7.2" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '7.2' ? 'selected' : '' }}>PHP 7.2</option>
                                <option value="7.3" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '7.3' ? 'selected' : '' }}>PHP 7.3</option>
                                <option value="7.4" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '7.4' ? 'selected' : '' }}>PHP 7.4</option>
                                <option value="8.0" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '8.0' ? 'selected' : '' }}>PHP 8.0</option>
                                <option value="8.1" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '8.1' ? 'selected' : '' }}>PHP 8.1</option>
                                <option value="8.2" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '8.2' ? 'selected' : '' }}>PHP 8.2</option>
                                <option value="8.3" {{ isset($data) && isset($data->data['php_version']) && $data->data['php_version'] == '8.3' ? 'selected' : '' }}>PHP 8.3</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_website_type" class="form-label">{{ __('Website Type') }}</label>
                            <select id="data_website_type" name="data[website_type]" class="form-select">
                                <option value="">{{ __('Select Type') }}</option>
                                <option value="wordpress" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'wordpress' ? 'selected' : '' }}>WordPress</option>
                                <option value="laravel" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'laravel' ? 'selected' : '' }}>Laravel</option>
                                <option value="drupal" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'drupal' ? 'selected' : '' }}>Drupal</option>
                                <option value="joomla" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'joomla' ? 'selected' : '' }}>Joomla</option>
                                <option value="magento" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'magento' ? 'selected' : '' }}>Magento</option>
                                <option value="html" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'html' ? 'selected' : '' }}>Static HTML</option>
                                <option value="other" {{ isset($data) && isset($data->data['website_type']) && $data->data['website_type'] == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Server Access Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Server Access') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_email" class="form-label">{{ __('Email') }}</label>
                            <input type="text" id="data_email" name="data[email]" class="form-control" value="{{ isset($data) ? ($data->data['email'] ?? '') : '' }}">
                            <small class="text-muted">{{ __('Separate multiple emails with commas') }}</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_user" class="form-label">{{ __('Username') }}</label>
                            <input type="text" id="data_user" name="data[user]" class="form-control" value="{{ isset($data) ? ($data->data['user'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_rdns" class="form-label">rDNS</label>
                            <input type="text" id="data_rdns" name="data[rdns]" class="form-control" value="{{ isset($data) ? ($data->data['rdns'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_owner" class="form-label">Owner</label>
                            <input type="text" id="data_owner" name="data[owner]" class="form-control" value="{{ isset($data) ? ($data->data['owner'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_shell" class="form-label">Shell</label>
                            <input type="text" id="data_shell" name="data[shell]" class="form-control" value="{{ isset($data) ? ($data->data['shell'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_theme" class="form-label">Theme</label>
                            <input type="text" id="data_theme" name="data[theme]" class="form-control" value="{{ isset($data) ? ($data->data['theme'] ?? '') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quotas & Limits Card -->
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Quotas & Limits') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_backup" class="form-label">{{ __('Backup') }}</label>
                            <select id="data_backup" name="data[backup]" class="form-select">
                                <option value="1" {{ isset($data) && isset($data->data['backup']) && $data->data['backup'] == 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                <option value="0" {{ isset($data) && isset($data->data['backup']) && $data->data['backup'] == 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxftp" class="form-label">Max FTP</label>
                            <input type="number" id="data_maxftp" name="data[maxftp]" class="form-control" value="{{ isset($data) ? ($data->data['maxftp'] ?? '10') : '10' }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxlst" class="form-label">Max LST</label>
                            <input type="number" id="data_maxlst" name="data[maxlst]" class="form-control" value="{{ isset($data) ? ($data->data['maxlst'] ?? '10') : '10' }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxpop" class="form-label">Max POP</label>
                            <input type="number" id="data_maxpop" name="data[maxpop]" class="form-control" value="{{ isset($data) ? ($data->data['maxpop'] ?? '0') : '0' }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxsql" class="form-label">Max SQL</label>
                            <input type="number" id="data_maxsql" name="data[maxsql]" class="form-control" value="{{ isset($data) ? ($data->data['maxsql'] ?? '10') : '10' }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxsub" class="form-label">Max SUB</label>
                            <input type="number" id="data_maxsub" name="data[maxsub]" class="form-control" value="{{ isset($data) ? ($data->data['maxsub'] ?? '10') : '10' }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_maxparked" class="form-label">Max Parked</label>
                            <input type="number" id="data_maxparked" name="data[maxparked]" class="form-control" value="{{ isset($data) ? ($data->data['maxparked'] ?? '10') : '10' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_disklimit" class="form-label">Disk Limit (MB)</label>
                            <input type="number" id="data_disklimit" name="data[disklimit]" class="form-control" value="{{ isset($data) ? ($data->data['disklimit'] ?? '5000') : '5000' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_bandwidthlimit" class="form-label">Bandwidth Limit (MB)</label>
                            <input type="number" id="data_bandwidthlimit" name="data[bandwidthlimit]" class="form-control" value="{{ isset($data) ? ($data->data['bandwidthlimit'] ?? '20000') : '20000' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_inodeslimit" class="form-label">Inodes Limit</label>
                            <input type="number" id="data_inodeslimit" name="data[inodeslimit]" class="form-control" value="{{ isset($data) ? ($data->data['inodeslimit'] ?? '0') : '0' }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_max_email_per_hour" class="form-label">Max Email/Hour</label>
                            <input type="number" id="data_max_email_per_hour" name="data[max_email_per_hour]" class="form-control" value="{{ isset($data) ? ($data->data['max_email_per_hour'] ?? '400') : '400' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

<!-- Submit Buttons -->
<div class="row mt-4">
    <div class="col-md-12 text-start">
        <button type="submit" form="serviceForm" class="btn btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('service-list') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function () {
        'use strict';

        // Initialize Select2 (skip fields that init themselves)
        $('.select2').not('.select2-service-category').not('.select2-subscription').not('.select2-client-enterprise').each(function () {
            var $this = $(this);
            if ($this.hasClass('select2-hidden-accessible') || $this.attr('data-module-key')) {
                return;
            }
            $this.select2({
                placeholder: $this.data('placeholder') || 'Select',
                allowClear: String($this.data('allow-clear')) === 'true',
                dropdownParent: $(document.body),
                width: '100%'
            });
        });

        var $subscriptionSelect = $('#subscription_id');
        if ($subscriptionSelect.length && $.fn.select2 && ! $subscriptionSelect.hasClass('select2-hidden-accessible')) {
            $subscriptionSelect.select2({
                placeholder: $subscriptionSelect.data('placeholder') || @json(__('Subscripción local')),
                allowClear: true,
                width: '100%',
                dropdownParent: $(document.body),
            });
        }

        var $categorySelect = $('#category_id');
        if ($categorySelect.length && $.fn.select2) {
            var categoryPlaceholder = $categorySelect.data('empty-text') || @json(__('Uncategorized'));

            function initServiceCategorySelect() {
                if ($categorySelect.hasClass('select2-hidden-accessible')) {
                    $categorySelect.select2('destroy');
                }

                $categorySelect.select2({
                    placeholder: categoryPlaceholder,
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(document.body),
                });
            }

            initServiceCategorySelect();

            if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
                Livewire.on('module-categories-refreshed', function (event) {
                    var detail = Array.isArray(event) ? event[0] : event;
                    if (! detail || detail.selectId !== 'category_id') {
                        return;
                    }

                    var moduleOptionsUrl = @json(route('categories.module-options'));
                    $.getJSON(moduleOptionsUrl, { module_key: 'services' })
                        .done(function (data) {
                            var prevVal = $categorySelect.val();
                            $categorySelect.empty();
                            $categorySelect.append(new Option(categoryPlaceholder, '', false, false));

                            (data.groups || []).forEach(function (g) {
                                if (g.type === 'option') {
                                    $categorySelect.append(new Option(g.label, String(g.id), false, false));
                                } else if (g.type === 'group') {
                                    var og = $('<optgroup>').attr('label', g.label);
                                    (g.options || []).forEach(function (o) {
                                        og.append(new Option(o.label, String(o.id), false, false));
                                    });
                                    $categorySelect.append(og);
                                }
                            });

                            initServiceCategorySelect();

                            if (prevVal && $categorySelect.find('option[value="' + String(prevVal).replace(/"/g, '\\"') + '"]').length) {
                                $categorySelect.val(String(prevVal)).trigger('change');
                            } else {
                                $categorySelect.val(null).trigger('change');
                            }
                        });
                });
            }
        }

        // Initialize Flatpickr
        $('.flatpickr-date').flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true
        });
    });
</script>
@endsection
