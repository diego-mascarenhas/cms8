@extends('layouts/layoutHelpSimple')

@section('title', __('WooCommerce configuration'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('WooCommerce configuration') }}</h4>
                <a href="{{ route('help.environment-variables') }}" class="btn btn-sm btn-label-secondary">{{ __('← Configuraciones') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Configure the connection between Humano and your WooCommerce store to manage products and orders from the application.') }}</p>

                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-info-circle me-2"></i>{{ __('Where to configure') }}</h6>
                    <p class="mb-0">{{ __('All settings are configured in') }} <strong>{{ __('Team Settings') }}</strong> → <strong>{{ __('WooCommerce Integration') }}</strong> → {{ __('Configure.') }} {{ __('Access from the user menu or') }} <code>{{ url('/team') }}/&lt;id&gt;/settings/woocommerce</code>. {{ __('No server environment variables are required.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('1. What you need') }}</h5>
                <ul>
                    <li>{{ __('A WordPress site with WooCommerce installed and active.') }}</li>
                    <li>{{ __('The store URL') }}: {{ __('the full URL of your site (e.g.') }} <code>https://tu-tienda.com</code>).</li>
                    <li>{{ __('REST API keys') }}: {{ __('Consumer Key and Consumer Secret generated in WooCommerce.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('2. How to generate the API keys in WooCommerce') }}</h5>
                <ol>
                    <li>
                        <strong>{{ __('Log in to WordPress') }}</strong><br>
                        {{ __('Access your site as administrator.') }}
                    </li>
                    <li>
                        <strong>{{ __('Open WooCommerce settings') }}</strong><br>
                        {{ __('In the left menu') }} → <strong>{{ __('WooCommerce') }}</strong> → <strong>{{ __('Settings') }}</strong>.
                    </li>
                    <li>
                        <strong>{{ __('REST API tab') }}</strong><br>
                        {{ __('Click the') }} <strong>{{ __('Advanced') }}</strong> {{ __('tab') }} → <strong>{{ __('REST API') }}</strong>. {{ __('There you will see the list of API keys.') }}
                    </li>
                    <li>
                        <strong>{{ __('Add key') }}</strong><br>
                        {{ __('Click') }} <strong>{{ __('Add key') }}</strong>. {{ __('Description (e.g. "Humano"). User: choose an administrator user. Permissions:') }} <strong>{{ __('Read/Write') }}</strong> {{ __('so that Humano can read and update products and orders.') }}
                    </li>
                    <li>
                        <strong>{{ __('Copy the keys') }}</strong><br>
                        {{ __('After creating the key, WooCommerce will show') }} <strong>{{ __('Consumer key') }}</strong> {{ __('(starts with') }} <code>ck_</code>) {{ __('and') }} <strong>{{ __('Consumer secret') }}</strong> {{ __('(starts with') }} <code>cs_</code>). {{ __('Copy both; the secret is only shown once.') }}
                    </li>
                </ol>

                <h5 class="mt-4">{{ __('3. Fields to configure in Humano') }}</h5>
                <table class="table table-bordered mb-4">
                    <thead>
                        <tr>
                            <th>{{ __('Field') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>{{ __('Store URL') }}</strong></td>
                            <td>{{ __('Full URL of your WooCommerce store (e.g.') }} <code>https://tu-tienda.com</code>). {{ __('Do not add') }} <code>/wp-json</code> {{ __('or a trailing slash.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('API Version') }}</strong></td>
                            <td>{{ __('Use') }} <strong>wc/v3</strong> {{ __('(recommended) unless your WooCommerce version requires an older version.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('Consumer Key') }}</strong></td>
                            <td>{{ __('The Consumer Key from WooCommerce (starts with') }} <code>ck_</code>).</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('Consumer Secret') }}</strong></td>
                            <td>{{ __('The Consumer Secret from WooCommerce (starts with') }} <code>cs_</code>). {{ __('Stored encrypted.') }}</td>
                        </tr>
                        <tr>
                            <td><strong>{{ __('Verify SSL Certificate') }}</strong></td>
                            <td>{{ __('Recommended on for production sites with valid HTTPS. Turn off only for local or test environments without valid SSL.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h5 class="mt-4">{{ __('4. Complete the configuration in Humano') }}</h5>
                <ol>
                    <li>{{ __('Go to') }} <strong>{{ __('Team Settings') }}</strong> ({{ __('e.g.') }} <code>/team/&lt;id&gt;/settings</code>).</li>
                    <li>{{ __('Find the') }} <strong>{{ __('WooCommerce Integration') }}</strong> {{ __('card') }} → {{ __('Configure.') }}</li>
                    <li>{{ __('Store URL: enter the full URL of your store.') }}</li>
                    <li>{{ __('API Version: leave') }} <strong>wc/v3</strong> {{ __('unless you need another.') }}</li>
                    <li>{{ __('Consumer Key and Consumer Secret: paste the values generated in WooCommerce.') }}</li>
                    <li>{{ __('Verify SSL: enable for production, disable only for local/testing.') }}</li>
                    <li>{{ __('Save changes.') }}</li>
                </ol>

                <h5 class="mt-4">{{ __('5. After configuration') }}</h5>
                <p>{{ __('Once the connection is configured:') }}</p>
                <ul>
                    <li>{{ __('In') }} <strong>{{ __('Products') }}</strong> {{ __('you will see the list of products from your WooCommerce store and you can create or edit them from Humano.') }}</li>
                    <li>{{ __('In') }} <strong>{{ __('Orders') }}</strong> {{ __('you will see the orders and you can edit their status and details.') }}</li>
                    <li>{{ __('You can open the product or order in WooCommerce (WordPress) from the action links in the list.') }}</li>
                </ul>

                <div class="alert alert-warning mt-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-alert-triangle me-2"></i>{{ __('Troubleshooting') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('If you see "WooCommerce is not configured": check that the four fields (URL, API version, Consumer Key, Consumer Secret) are filled in and saved.') }}</li>
                        <li>{{ __('If the API returns errors: verify that the REST API is enabled in WooCommerce and that the key has Read/Write permission.') }}</li>
                        <li>{{ __('For sites with self-signed or invalid SSL, disable "Verify SSL Certificate" only in development.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
