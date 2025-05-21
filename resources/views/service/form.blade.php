@extends('layouts/layoutMaster')
@section('title', isset($data) ? 'Edit Service' : 'Create Service')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Service /</span> {{ isset($data) ? 'Edit Service' : 'Create Service' }}
    </h4>

    <form id="serviceForm" method="POST" action="{{ isset($data) ? route('service.update', $data->id) : route('service.store') }}">
        @csrf
        @if(isset($data))
            @method('PUT')
            <input type="hidden" name="id" value="{{ $data->id }}">
        @endif

        <!-- Basic Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">Basic Information</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6" style="display: none;">
                        <div class="form-group">
                            <label for="enterprise_id" class="form-label">Client</label>
                            <select id="enterprise_id" name="enterprise_id" class="select2 form-select" data-allow-clear="true" required>
                                <option value="">Select Client</option>
                                @foreach(\App\Models\Enterprise::all() as $enterprise)
                                    <option value="{{ $enterprise->id }}" {{ (isset($data) && $data->enterprise_id == $enterprise->id) || (isset($enterprise_id) && $enterprise_id == $enterprise->id) ? 'selected' : '' }}>{{ $enterprise->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <x-team-users-select 
                            id="responsible_id" 
                            label="Asesor"
                            :selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
                            show-null="false"
                        />
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_id" class="form-label">Category</label>
                            <select id="category_id" name="category_id" class="select2 form-select" data-allow-clear="true" required>
                                <option value="">Select Category</option>
                                @foreach(\App\Models\Category::all() as $category)
                                    <option value="{{ $category->id }}" {{ isset($data) && $data->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="operation" class="form-label">Operation</label>
                            <select id="operation" name="operation" class="form-select" required>
                                <option value="">Select Operation</option>
                                <option value="buy" {{ isset($data) && $data->operation == 'buy' ? 'selected' : '' }}>Buy</option>
                                <option value="sell" {{ isset($data) && $data->operation == 'sell' ? 'selected' : '' }}>Sell</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="1" {{ isset($data) && $data->status == 1 ? 'selected' : '' }}>Suspendido</option>
                                <option value="2" {{ isset($data) && $data->status == 2 ? 'selected' : '' }}>Suspender</option>
                                <option value="3" {{ isset($data) && $data->status == 3 ? 'selected' : '' }}>Activar</option>
                                <option value="4" {{ isset($data) && $data->status == 4 ? 'selected' : '' }}>Activo</option>
                                <option value="5" {{ isset($data) && $data->status == 5 ? 'selected' : '' }}>Migrar</option>
                                <option value="6" {{ isset($data) && $data->status == 6 ? 'selected' : '' }}>Cambiar DNS</option>
                                <option value="7" {{ isset($data) && $data->status == 7 ? 'selected' : '' }}>Delegar</option>
                                <option value="8" {{ isset($data) && $data->status == 8 ? 'selected' : '' }}>Corregir precio</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ isset($data) ? $data->description : '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Information Card -->
        <div class="card mb-4">
            <h5 class="card-header">Financial Information</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="currency_id" class="form-label">Currency (optional)</label>
                            <select id="currency_id" name="currency_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">Select Currency</option>
                                <option value="32" {{ isset($data) && $data->currency_id == 32 ? 'selected' : '' }}>ARS - Argentine Peso</option>
                                <option value="840" {{ isset($data) && $data->currency_id == 840 ? 'selected' : '' }}>USD - United States Dollar</option>
                                <option value="978" {{ isset($data) && $data->currency_id == 978 ? 'selected' : '' }}>EUR - Euro</option>
                            </select>
                            <small class="text-muted">If not selected, system default will be used</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" value="{{ isset($data) ? $data->price : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="discount" class="form-label">Discount (%)</label>
                            <input type="number" id="discount" name="discount" class="form-control" step="0.01" value="{{ isset($data) ? $data->discount : '0' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="frequency" class="form-label">Frequency (months)</label>
                            <input type="number" id="frequency" name="frequency" class="form-control" value="{{ isset($data) ? $data->frequency : '1' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="next_billing" class="form-label">Next Billing Date</label>
                            <input type="text" id="next_billing" name="next_billing" class="form-control flatpickr-date" value="{{ isset($data) && $data->next_billing ? $data->next_billing->format('Y-m-d') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expires_at" class="form-label">Expiration Date</label>
                            <input type="text" id="expires_at" name="expires_at" class="form-control flatpickr-date" value="{{ isset($data) && $data->expires_at ? $data->expires_at->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain & Hosting Card -->
        <div class="card mb-4">
            <h5 class="card-header">Domain & Hosting</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_domain" class="form-label">Domain</label>
                            <input type="text" id="data_domain" name="data[domain]" class="form-control" value="{{ isset($data) ? ($data->data['domain'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_ip" class="form-label">IP Address</label>
                            <input type="text" id="data_ip" name="data[ip]" class="form-control" value="{{ isset($data) ? ($data->data['ip'] ?? '') : '' }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="data_plan" class="form-label">Plan</label>
                            <input type="text" id="data_plan" name="data[plan]" class="form-control" value="{{ isset($data) ? ($data->data['plan'] ?? '') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DNS Configuration Card -->
        <div class="card mb-4">
            <h5 class="card-header">DNS Configuration</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_dns" class="form-label">DNS Servers</label>
                            <input type="text" id="data_dns" name="data[dns]" class="form-control" value="{{ isset($data) ? ($data->data['dns'] ?? '') : '' }}">
                            <small class="text-muted">Separate multiple values with commas</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_spf" class="form-label">SPF Record</label>
                            <input type="text" id="data_spf" name="data[spf]" class="form-control" value="{{ isset($data) ? ($data->data['spf'] ?? '') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technology Card -->
        <div class="card mb-4">
            <h5 class="card-header">Technology Details</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_os" class="form-label">Operating System</label>
                            <select id="data_os" name="data[os]" class="form-select">
                                <option value="">Select Operating System</option>
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
                            <label for="data_control_panel" class="form-label">Control Panel</label>
                            <select id="data_control_panel" name="data[control_panel]" class="form-select">
                                <option value="">Select Control Panel</option>
                                <option value="cPanel" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'cPanel' ? 'selected' : '' }}>cPanel</option>
                                <option value="Plesk" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'Plesk' ? 'selected' : '' }}>Plesk</option>
                                <option value="aaPanel" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'aaPanel' ? 'selected' : '' }}>aaPanel</option>
                                <option value="Other" {{ isset($data) && isset($data->data['control_panel']) && $data->data['control_panel'] == 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_db_version" class="form-label">Database Version</label>
                            <select id="data_db_version" name="data[db_version]" class="form-select">
                                <option value="">Select Database Version</option>
                                <option value="MySQL 5.7" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MySQL 5.7' ? 'selected' : '' }}>MySQL 5.7</option>
                                <option value="MySQL 8.0" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MySQL 8.0' ? 'selected' : '' }}>MySQL 8.0</option>
                                <option value="MariaDB 10.5" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MariaDB 10.5' ? 'selected' : '' }}>MariaDB 10.5</option>
                                <option value="MariaDB 10.6" {{ isset($data) && isset($data->data['db_version']) && $data->data['db_version'] == 'MariaDB 10.6' ? 'selected' : '' }}>MariaDB 10.6</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_php_version" class="form-label">PHP Version</label>
                            <select id="data_php_version" name="data[php_version]" class="form-select">
                                <option value="">Select PHP Version</option>
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
                            <label for="data_website_type" class="form-label">Website Type</label>
                            <select id="data_website_type" name="data[website_type]" class="form-select">
                                <option value="">Select Type</option>
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

                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="data_email_service" class="form-label">Email Service</label>
                            <select id="data_email_service" name="data[email_service]" class="form-select">
                                <option value="1" {{ isset($data) && isset($data->data['email_service']) && $data->data['email_service'] == 1 ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ isset($data) && isset($data->data['email_service']) && $data->data['email_service'] == 0 ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Server Access Card -->
        <div class="card mb-4">
            <h5 class="card-header">Server Access</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_email" class="form-label">Email</label>
                            <input type="text" id="data_email" name="data[email]" class="form-control" value="{{ isset($data) ? ($data->data['email'] ?? '') : '' }}">
                            <small class="text-muted">Separate multiple emails with commas</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="data_user" class="form-label">Username</label>
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
            <h5 class="card-header">Quotas & Limits</h5>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="data_backup" class="form-label">Backup</label>
                            <select id="data_backup" name="data[backup]" class="form-select">
                                <option value="1" {{ isset($data) && isset($data->data['backup']) && $data->data['backup'] == 1 ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ isset($data) && isset($data->data['backup']) && $data->data['backup'] == 0 ? 'selected' : '' }}>No</option>
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
            <button type="submit" form="serviceForm" class="btn btn-primary">Save</button>
            <a href="{{ route('service-list') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function () {
        'use strict';

        // Initialize Select2
        $('.select2').each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2({
                placeholder: 'Select',
                dropdownParent: $this.parent()
            });
        });

        // Initialize Flatpickr
        $('.flatpickr-date').flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true
        });
    });
</script>
@endsection 