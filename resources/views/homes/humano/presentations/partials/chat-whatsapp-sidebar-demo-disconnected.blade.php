{{-- Backup: demo markup when the WhatsApp service is unreachable (disconnected). --}}
<div class="app-chat card overflow-hidden border-0 shadow-sm">
    <div class="app-chat-sidebar-left app-sidebar" style="width:100%;max-width:380px;margin:0 auto;">
        <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-4 pt-4 pb-3">
            <div class="avatar avatar-xl avatar-offline">
                <span class="avatar-initial rounded-circle bg-label-success">
                    <i class="ti ti-brand-whatsapp" style="font-size: 2rem;"></i>
                </span>
            </div>
            <h5 class="mt-2 mb-0">{{ __('Not linked') }}</h5>
            <div class="position-relative d-inline-block flex-shrink-0 mt-1">
                <span class="badge bg-secondary">{{ __('Disconnected') }}</span>
            </div>
        </div>
        <div class="sidebar-body px-4 pb-4">
            <div class="my-3">
                <div id="chat-sidebar-whatsapp-connection-block" data-wa-status="disconnected">
                    <small class="text-muted text-uppercase">{{ __('WhatsApp connection') }}</small>
                    <div class="d-grid gap-2 mt-3">
                        <div class="d-inline-block text-center chat-qr-loading" id="chat-qr-container">
                            <div id="chat-qr-fallback" class="mb-2">
                                <div class="chat-qr-fallback-frame position-relative mx-auto rounded overflow-hidden">
                                    <div class="chat-qr-loading-overlay position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 rounded" role="status" aria-live="polite">
                                        <div class="spinner-border text-primary" style="width: 2.25rem; height: 2.25rem;" aria-hidden="true"></div>
                                        <span class="small text-muted text-center px-2">{{ __('auth.registration.qr_whatsapp_loading') }}</span>
                                    </div>
                                    <div class="chat-qr-fallback-pattern" aria-hidden="true"></div>
                                    <div class="chat-qr-fallback-vignette position-absolute top-0 start-0 w-100 h-100"></div>
                                </div>
                            </div>
                        </div>
                        <p class="small text-danger mb-0 mt-2 text-center" role="alert">
                            {{ __('auth.registration.qr_whatsapp_service_unreachable') }}
                        </p>
                        <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_timing_hint') }}</p>
                        <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_refresh_hint') }}</p>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" disabled>
                            <i class="ti ti-refresh me-1"></i>{{ __('auth.registration.qr_whatsapp_refresh') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
