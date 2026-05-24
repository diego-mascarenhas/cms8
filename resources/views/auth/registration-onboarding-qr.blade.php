@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
$onboardingQrScanTargetsChatOnly = $onboardingQrScanTargetsChatOnly ?? false;
$registrationWaShowLoader = !($teamWhatsAppIsConnected ?? false) && !empty($qrImageUrl ?? null) && !$onboardingQrScanTargetsChatOnly;
@endphp

@extends('layouts/blankLayout')

@section('title', __('auth.registration.qr_title'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
  #registration-wa-qr-container {
    position: relative;
    min-width: 200px;
    min-height: 200px;
    width: 200px;
  }
  #registration-wa-qr-container.registration-wa-qr-loading {
    background-color: transparent;
  }
  #registration-wa-qr-container.registration-wa-qr-loading .chat-qr-fallback-frame {
    opacity: 0.65;
  }
  .registration-onboarding .chat-qr-fallback-frame {
    width: 200px;
    height: 200px;
    background-color: var(--bs-gray-75, #eceef2);
    box-shadow: none;
  }
  .registration-onboarding .chat-qr-fallback-pattern {
    position: absolute;
    inset: -10px;
    z-index: 0;
    pointer-events: none;
    background-color: #dfe3ea;
    background-image:
      linear-gradient(90deg, rgba(67, 89, 113, 0.22) 50%, transparent 50%),
      linear-gradient(rgba(67, 89, 113, 0.22) 50%, transparent 50%);
    background-size: 7px 7px;
    filter: blur(3px);
    opacity: 0.55;
  }
  .registration-onboarding .chat-qr-fallback-vignette {
    z-index: 1;
    background: radial-gradient(
      ellipse 70% 70% at 50% 50%,
      rgba(255, 255, 255, 0.88) 0%,
      rgba(255, 255, 255, 0.35) 55%,
      rgba(255, 255, 255, 0.12) 100%
    );
    pointer-events: none;
  }
  .registration-onboarding .chat-qr-fallback-frame .registration-wa-qr-loading-overlay {
    z-index: 3;
    background: rgba(255, 255, 255, 0.82);
    display: none;
  }
  #registration-wa-qr-container.registration-wa-qr-loading .chat-qr-fallback-frame .registration-wa-qr-loading-overlay {
    display: flex !important;
  }
  #registration-onboarding-static-qr-loading {
    min-width: 200px;
    min-height: 200px;
  }
</style>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg registration-onboarding">
  <div class="authentication-inner row justify-content-center">
    <div class="d-flex col-12 col-md-8 col-lg-6 align-items-center p-sm-5 p-4">
      <div class="card w-100 shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
          @if (session('success'))
          <div class="alert alert-success mb-4" role="alert">{{ session('success') }}</div>
          @endif

          <h4 class="mb-2">{{ __('auth.registration.qr_heading') }}</h4>
          <p class="text-muted mb-3">{{ __('auth.registration.qr_description') }}</p>

          @if ($teamWhatsAppIsConnected ?? false)
          <div class="alert alert-success text-start mb-4" role="status">
            {{ __('auth.registration.qr_whatsapp_already_connected') }}
          </div>
          @elseif (!empty($qrImageUrl))
          <ol class="text-start small text-muted mb-4 ps-3">
            @foreach (($onboardingQrScanTargetsChatOnly ? __('auth.registration.qr_whatsapp_steps_chat') : __('auth.registration.qr_whatsapp_steps_local')) as $step)
            <li class="mb-1">{{ $step }}</li>
            @endforeach
          </ol>

          @if ($onboardingQrScanTargetsChatOnly)
          <div class="d-flex flex-column align-items-center mb-4">
            <div class="position-relative d-inline-block">
              <div id="registration-onboarding-static-qr-loading" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 rounded border bg-white" style="z-index: 1;">
                <div class="spinner-border text-primary" style="width: 2.25rem; height: 2.25rem;" aria-hidden="true"></div>
                <span class="small text-muted text-center px-2">{{ __('auth.registration.qr_whatsapp_loading') }}</span>
              </div>
              <img
                id="registration-onboarding-static-qr-img"
                src="{{ $qrImageUrl }}?t={{ time() }}"
                alt="{{ __('auth.registration.qr_whatsapp_image_alt') }}"
                class="d-block mx-auto rounded border"
                width="200"
                height="200"
                loading="eager"
                onload="var el=document.getElementById('registration-onboarding-static-qr-loading'); if(el) el.classList.add('d-none');"
                onerror="var el=document.getElementById('registration-onboarding-static-qr-loading'); if(el) el.classList.add('d-none');">
            </div>
            <p class="small text-muted mb-0 mt-2 text-center px-1">{{ __('auth.registration.qr_whatsapp_timing_hint') }}</p>
          </div>
          @else
          <div id="registration-wa-qr-block" class="d-flex flex-column align-items-center mb-4">
            <div id="registration-wa-qr-container" @class(['mx-auto', 'registration-wa-qr-loading' => $registrationWaShowLoader])>
              <img id="registration-wa-qr-img"
                src="{{ $qrImageUrl }}?t={{ time() }}"
                alt="{{ __('auth.registration.qr_whatsapp_image_alt') }}"
                class="d-block mx-auto d-none rounded"
                width="200"
                height="200"
                loading="eager"
                data-qr-base="{{ $qrImageUrl }}">
              <div id="registration-wa-qr-fallback" @class(['mb-2', 'd-none' => !$registrationWaShowLoader])>
                <div class="chat-qr-fallback-frame position-relative mx-auto rounded overflow-hidden">
                  <div class="registration-wa-qr-loading-overlay position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 rounded" role="status" aria-live="polite">
                    <div class="spinner-border text-primary" style="width: 2.25rem; height: 2.25rem;" aria-hidden="true"></div>
                    <span class="small text-muted text-center px-2">{{ __('auth.registration.qr_whatsapp_loading') }}</span>
                  </div>
                  <div class="chat-qr-fallback-pattern" aria-hidden="true"></div>
                  <div class="chat-qr-fallback-vignette position-absolute top-0 start-0 w-100 h-100"></div>
                </div>
              </div>
            </div>
            <p class="small text-muted mb-2 text-center px-1">{{ __('auth.registration.qr_whatsapp_timing_hint') }}</p>
            <p class="small text-muted mb-2 text-center px-1">{{ __('auth.registration.qr_whatsapp_refresh_hint') }}</p>
            <button type="button" id="registration-wa-qr-refresh" class="btn btn-sm btn-outline-secondary mt-1">
              {{ __('auth.registration.qr_whatsapp_refresh') }}
            </button>
            <p id="registration-wa-qr-refresh-message" class="small text-muted mb-0 mt-2 d-none" role="status"></p>
          </div>
          @endif
          @endif

          <div class="d-flex flex-wrap justify-content-center gap-2">
            @can('chat.list')
            @if (Route::has('chat.index'))
            <a href="{{ route('chat.index') }}" class="btn btn-primary">
              {{ __('auth.registration.qr_open_chat') }}
            </a>
            @endif
            @endcan

            @if (Route::has('dashboard'))
            <a href="{{ route('dashboard') }}" class="btn btn-label-secondary">
              {{ __('auth.registration.qr_continue_dashboard') }}
            </a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@if (!empty($qrImageUrl) && !($teamWhatsAppIsConnected ?? false) && !($onboardingQrScanTargetsChatOnly ?? false))
@push('scripts')
<script>
(function () {
  var refreshUrl = @json(route('chat.whatsapp-refresh-qr'));
  var token = document.querySelector('meta[name="csrf-token"]');
  var btn = document.getElementById('registration-wa-qr-refresh');
  var qrImg = document.getElementById('registration-wa-qr-img');
  var qrContainer = document.getElementById('registration-wa-qr-container');
  var fallback = document.getElementById('registration-wa-qr-fallback');
  var msgEl = document.getElementById('registration-wa-qr-refresh-message');
  var retryMs = 1100;
  var maxRetries = 36;

  function setLoadingUi(active) {
    if (!qrContainer) return;
    if (active) {
      qrContainer.classList.add('registration-wa-qr-loading');
      if (qrImg) qrImg.classList.add('d-none');
      if (fallback) fallback.classList.remove('d-none');
    } else {
      qrContainer.classList.remove('registration-wa-qr-loading');
    }
  }

  function showRealQr() {
    if (!qrImg || !qrImg.dataset.qrBase) return;
    var retries = 0;
    function bumpSrc() {
      var src = qrImg.dataset.qrBase + '?t=' + Date.now();
      qrImg.onload = function () {
        if (qrImg.naturalWidth > 20) {
          setLoadingUi(false);
          qrImg.classList.remove('d-none');
          if (fallback) fallback.classList.add('d-none');
          qrImg.onload = null;
          qrImg.onerror = null;
        } else if (retries < maxRetries) {
          retries += 1;
          setLoadingUi(true);
          setTimeout(bumpSrc, retryMs);
        } else {
          setLoadingUi(false);
          qrImg.classList.add('d-none');
          if (fallback) fallback.classList.remove('d-none');
          qrImg.onload = null;
          qrImg.onerror = null;
        }
      };
      qrImg.onerror = function () {
        if (retries < maxRetries) {
          retries += 1;
          setLoadingUi(true);
          setTimeout(bumpSrc, retryMs);
        } else {
          setLoadingUi(false);
          qrImg.classList.add('d-none');
          if (fallback) fallback.classList.remove('d-none');
        }
        qrImg.onload = null;
        qrImg.onerror = null;
      };
      qrImg.removeAttribute('src');
      setTimeout(function () { qrImg.src = src; }, 0);
    }
    setTimeout(bumpSrc, 450);
  }

  function postRefreshThenShow(showServerMessage) {
    if (showServerMessage === undefined) {
      showServerMessage = false;
    }
    if (btn) btn.disabled = true;
    setLoadingUi(true);
    if (msgEl) {
      msgEl.textContent = '';
      msgEl.classList.add('d-none');
    }
    var t = token ? token.getAttribute('content') : '';
    if (!t) {
      showRealQr();
      if (btn) btn.disabled = false;
      return;
    }
    fetch(refreshUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: '_token=' + encodeURIComponent(t)
    }).then(function (r) {
      if (!r.ok) throw new Error('refresh failed');
      return r.json();
    }).then(function (data) {
      if (msgEl && data.message && showServerMessage) {
        msgEl.textContent = data.message;
        msgEl.classList.remove('d-none');
      }
    }).catch(function () {
      if (msgEl) {
        msgEl.textContent = @json(__('auth.registration.qr_whatsapp_refresh_failed'));
        msgEl.classList.remove('d-none');
      }
    }).finally(function () {
      showRealQr();
      if (btn) btn.disabled = false;
    });
  }

  postRefreshThenShow(false);

  if (btn) {
    btn.addEventListener('click', function () {
      postRefreshThenShow(true);
    });
  }
})();
</script>
@endpush
@endif

@if (!($teamWhatsAppIsConnected ?? false) && ($whatsappDriver ?? '') === 'local' && !($onboardingQrScanTargetsChatOnly ?? false))
@push('scripts')
<script>
(function () {
  var waStatusUrl = @json(route('chat.whatsapp-status'));

  function onConnected() {
    if (window.location && window.location.reload) {
      window.location.reload();
    }
  }

  function applyStatus(data) {
    if (data.isTeamConnected) {
      onConnected();
    }
  }

  fetch(waStatusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(applyStatus)
    .catch(function () {});

  var waPoll = setInterval(function () {
    fetch(waStatusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        applyStatus(data);
        if (data.isTeamConnected) {
          clearInterval(waPoll);
        }
      })
      .catch(function () {});
  }, 3000);
})();
</script>
@endpush
@endif
