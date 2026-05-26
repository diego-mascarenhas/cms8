{{-- Demo markup aligned with chat/index.blade.php (local WhatsApp, waiting_qr). QR: humano:sync-presentation-whatsapp-qr --}}
<div class="app-chat card overflow-hidden border-0 shadow-sm">
    <div class="app-chat-sidebar-left app-sidebar" style="width:100%;max-width:380px;margin:0 auto;">
        <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-4 pt-4 pb-3">
            <div class="avatar avatar-xl avatar-offline">
                <span class="avatar-initial rounded-circle bg-label-success">
                    <i class="ti ti-brand-whatsapp" style="font-size: 2rem;"></i>
                </span>
            </div>
            <h5 class="mt-2 mb-0">+34 999 000 999</h5>
            <div class="position-relative d-inline-block flex-shrink-0 mt-1">
                <span class="badge bg-warning">{{ __('Scan QR') }}</span>
            </div>
        </div>
        <div class="sidebar-body px-4 pb-4">
            <div class="my-3">
                <div id="chat-sidebar-whatsapp-connection-block" data-wa-status="waiting_qr">
                    <small class="text-muted text-uppercase">{{ __('WhatsApp connection') }}</small>
                    <div class="d-grid gap-2 mt-3">
                        <div class="d-inline-block text-center" id="chat-qr-container">
                            <img id="chat-whatsapp-qr-img"
                                 src="{{ asset('homes/humano/img/presentations/whatsapp-qr.png') }}"
                                 alt="WhatsApp QR"
                                 class="d-block mx-auto"
                                 width="200"
                                 height="200"
                                 loading="eager">
                        </div>
                        <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_timing_hint') }}</p>
                        <p class="small text-muted mb-0 text-center">{{ __('auth.registration.qr_whatsapp_refresh_hint') }}</p>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="chat-whatsapp-qr-refresh-btn">
                            <i class="ti ti-refresh me-1"></i>{{ __('auth.registration.qr_whatsapp_refresh') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
