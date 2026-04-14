@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
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
            @foreach (__('auth.registration.qr_whatsapp_steps_local') as $step)
            <li class="mb-1">{{ $step }}</li>
            @endforeach
          </ol>

          <div id="registration-wa-qr-block" class="d-flex flex-column align-items-center mb-4">
            <div id="registration-wa-qr-container" class="mx-auto">
              <img id="registration-wa-qr-img"
                src="{{ $qrImageUrl }}?t={{ time() }}"
                alt="{{ __('auth.registration.qr_whatsapp_image_alt') }}"
                class="d-block mx-auto d-none rounded"
                width="200"
                height="200"
                loading="eager"
                data-qr-base="{{ $qrImageUrl }}">
              <div id="registration-wa-qr-fallback" class="mb-2">
                <div class="chat-qr-fallback-frame position-relative mx-auto rounded overflow-hidden">
                  <div class="chat-qr-fallback-pattern" aria-hidden="true"></div>
                  <div class="chat-qr-fallback-vignette position-absolute top-0 start-0 w-100 h-100"></div>
                </div>
              </div>
            </div>
            <button type="button" id="registration-wa-qr-refresh" class="btn btn-sm btn-outline-secondary mt-2">
              {{ __('auth.registration.qr_whatsapp_refresh') }}
            </button>
            <p id="registration-wa-qr-refresh-message" class="small text-muted mb-0 mt-2 d-none" role="status"></p>
          </div>
          @else
          <p class="text-muted small text-start mb-4">{{ __('auth.registration.qr_whatsapp_intro_cloud') }}</p>
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

@if (!empty($qrImageUrl) && !($teamWhatsAppIsConnected ?? false))
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

  function showRealQr() {
    if (!qrImg || !qrImg.dataset.qrBase) return;
    var retries = 0;
    var maxRetries = 24;
    function bumpSrc() {
      var src = qrImg.dataset.qrBase + '?t=' + Date.now();
      qrImg.onload = function () {
        if (qrImg.naturalWidth > 20) {
          if (qrContainer) qrContainer.classList.remove('registration-wa-qr-loading');
          qrImg.classList.remove('d-none');
          if (fallback) fallback.classList.add('d-none');
          qrImg.onload = null;
          qrImg.onerror = null;
        } else if (retries < maxRetries) {
          retries += 1;
          if (fallback) fallback.classList.remove('d-none');
          if (qrContainer) qrContainer.classList.remove('registration-wa-qr-loading');
          setTimeout(bumpSrc, 2500);
        } else {
          if (qrContainer) qrContainer.classList.remove('registration-wa-qr-loading');
          qrImg.classList.add('d-none');
          if (fallback) fallback.classList.remove('d-none');
          qrImg.onload = null;
          qrImg.onerror = null;
        }
      };
      qrImg.onerror = function () {
        if (qrContainer) qrContainer.classList.remove('registration-wa-qr-loading');
        qrImg.classList.add('d-none');
        if (fallback) fallback.classList.remove('d-none');
        qrImg.onload = null;
        qrImg.onerror = null;
      };
      qrImg.removeAttribute('src');
      setTimeout(function () { qrImg.src = src; }, 0);
    }
    bumpSrc();
  }

  showRealQr();

  if (btn) {
    btn.addEventListener('click', function () {
      if (btn) btn.disabled = true;
      if (msgEl) {
        msgEl.textContent = '';
        msgEl.classList.add('d-none');
      }
      if (qrContainer) {
        qrContainer.classList.add('registration-wa-qr-loading');
        if (qrImg) qrImg.classList.add('d-none');
      }
      if (fallback) fallback.classList.remove('d-none');

      fetch(refreshUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: '_token=' + encodeURIComponent(token ? token.getAttribute('content') : '')
      }).then(function (r) {
        if (!r.ok) throw new Error('refresh failed');
        return r.json();
      }).then(function (data) {
        if (msgEl && data.message) {
          msgEl.textContent = data.message;
          msgEl.classList.remove('d-none');
        }
        showRealQr();
      }).catch(function () {
        if (msgEl) {
          msgEl.textContent = @json(__('auth.registration.qr_whatsapp_refresh_failed'));
          msgEl.classList.remove('d-none');
        }
        if (qrContainer) qrContainer.classList.remove('registration-wa-qr-loading');
      }).finally(function () {
        if (btn) btn.disabled = false;
      });
    });
  }
})();
</script>
@endpush
@endif
