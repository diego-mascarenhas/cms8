@extends('layouts/layoutMaster')

@section('title', __('WhatsApp Connection'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('WhatsApp Connection') }}</h4>
        <p class="text-muted">{{ __('Link your WhatsApp account by scanning the QR code (local service only).') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('chat.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Chat') }}
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if($driver === 'local')
            @if($qrUrl)
                @if(empty($status) || ($status['status'] ?? '') !== 'connected')
                    <form method="POST" action="{{ route('chat.whatsapp-refresh-qr') }}" class="mb-3">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="ti ti-refresh me-1"></i>{{ __('Generate new QR code') }}
                        </button>
                    </form>
                @endif
                <p class="text-muted mb-3">{{ __('Scan the QR code with WhatsApp on your phone. If the box below is empty (e.g. when using HTTPS), use the button to open the QR in a new tab.') }}</p>
                <a href="{{ $qrUrl }}" target="_blank" rel="noopener" class="btn btn-primary mb-3">
                    <i class="ti ti-external-link me-1"></i>{{ __('Open QR in new tab') }}
                </a>
                <p class="small text-muted mb-2">{{ __('The box below updates every few seconds until the QR code appears.') }}</p>
                <div class="border rounded p-3 d-inline-block bg-light">
                    <iframe
                        id="whatsapp-qr-iframe"
                        src="{{ $qrUrl }}"
                        title="WhatsApp QR"
                        width="320"
                        height="400"
                        class="border-0"
                        data-qr-url="{{ $qrUrl }}"
                    ></iframe>
                </div>
                @if(empty($status) || ($status['status'] ?? '') !== 'connected')
                <script>
                    (function() {
                        var iframe = document.getElementById('whatsapp-qr-iframe');
                        if (iframe && iframe.dataset.qrUrl) {
                            setInterval(function() {
                                iframe.src = iframe.dataset.qrUrl + '?t=' + Date.now();
                            }, 5000);
                        }
                    })();
                </script>
                @endif
            @else
                <p class="text-muted">{{ __('QR code URL not configured. Set WHATSAPP_LOCAL_BASE_URL and ensure the Node.js service is running.') }}</p>
            @endif

            @if($status)
                <div class="mt-4">
                    <h6 class="text-muted">{{ __('Status') }}</h6>
                    <p class="mb-0">
                        <span class="badge bg-{{ $status['status'] === 'connected' ? 'success' : ($status['status'] === 'waiting_qr' ? 'warning' : 'secondary') }}">
                            {{ __($status['status']) }}
                        </span>
                        @if(!empty($status['number']))
                            <span class="ms-2">{{ \App\Helpers\PhoneHelper::formatForDisplayReadable($status['number']) }}</span>
                        @endif
                    </p>
                </div>
            @endif
        @else
            <p class="text-muted mb-0">{{ __('WhatsApp is currently using Twilio. To use the local QR connection, set WHATSAPP_DRIVER=local in .env and run the Node.js WhatsApp service.') }}</p>
        @endif
    </div>
</div>
@endsection
