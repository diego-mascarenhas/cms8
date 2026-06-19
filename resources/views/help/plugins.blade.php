@extends('layouts/layoutHelpSimple')

@section('title', __('help_plugins.title'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="ti ti-brand-wordpress me-2"></i>{{ __('help_plugins.title') }}
                </h4>
                <a href="{{ route('help.index') }}" class="btn btn-sm btn-label-secondary">{{ __('← Introduction') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('help_plugins.intro') }}</p>

                <div class="alert alert-info">
                    <i class="ti ti-info-circle me-2"></i>{{ __('help_plugins.order_note') }}
                </div>

                {{-- Download cards --}}
                <div class="row g-3 mb-4">
                    @foreach($plugins as $slug => $plugin)
                    <div class="col-md-12">
                        <div class="card border h-100">
                            <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">
                                <div class="flex-shrink-0">
                                    <span class="badge bg-label-primary rounded p-3">
                                        <i class="{{ $plugin['icon'] }} ti-lg"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">{{ $plugin['name'] }}
                                        <span class="badge bg-label-secondary align-middle ms-1">v{{ $plugin['version'] }}</span>
                                    </h5>
                                    <p class="mb-0 text-muted">{{ $plugin['description'] }}</p>
                                </div>
                                <div class="flex-shrink-0 text-md-end">
                                    @if($plugin['available'])
                                        <a href="{{ route('help.plugins.download', $slug) }}" class="btn btn-primary">
                                            <i class="ti ti-download me-1"></i>{{ __('help_plugins.download') }}
                                        </a>
                                        <div class="small text-muted mt-1">{{ __('help_plugins.size_label') }}: {{ $plugin['size'] }}</div>
                                    @else
                                        <span class="badge bg-label-secondary">{{ __('help_plugins.not_available') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Install instructions --}}
                <h5 class="mt-4">{{ __('help_plugins.install_title') }}</h5>
                <ol>
                    <li>{{ __('help_plugins.install_step_download') }}</li>
                    <li>{{ __('help_plugins.install_step_upload') }}</li>
                    <li>{{ __('help_plugins.install_step_activate') }}</li>
                </ol>

                {{-- Configuration --}}
                <h5 class="mt-4">{{ __('help_plugins.config_title') }}</h5>
                <ul class="mb-3">
                    <li><strong>IDONEO Custom Fields:</strong> {{ __('help_plugins.custom_fields_config') }}</li>
                    <li><strong>IDONEO CMS Sync para Humano:</strong> {{ __('help_plugins.cms_sync_config') }}</li>
                    <li><strong>IDONEO Chat for Humano:</strong> {{ __('help_plugins.chat_config') }}</li>
                </ul>

                <div class="alert alert-light border d-flex align-items-center">
                    <i class="ti ti-key text-warning me-2"></i>
                    <div>
                        <span>{{ __('help_plugins.token_note') }}</span>
                        <code class="ms-1">{{ $apiToken }}</code>
                    </div>
                </div>

                <div class="alert alert-warning mb-0">
                    <i class="ti ti-alert-triangle me-2"></i>{{ __('help_plugins.production_note') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
