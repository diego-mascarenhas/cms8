<div class="d-flex flex-wrap gap-2 {{ $mobile ?? false ? '' : 'position-absolute top-0 start-0 m-4' }}" @if($mobile ?? false)@else style="top: 32px; left: 32px;" @endif>
  <a href="{{ route('assistant-demo') }}" class="btn btn-primary btn-lg shadow-lg btn-auth-cta-shine">
    <i class="ti ti-robot me-2 ti-md"></i>
    {{ __('Prueba Humano Assistant') }}
  </a>
  <a href="{{ route('landing.business-creation') }}" class="btn btn-outline-primary btn-lg">
    <i class="ti ti-building-store me-2 ti-md"></i>
    {{ __('Crear tu negocio') }}
  </a>
</div>
