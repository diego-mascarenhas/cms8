<a href="{{ route('assistant-demo') }}" class="btn btn-primary btn-lg shadow-lg btn-auth-cta-shine {{ $mobile ?? false ? '' : 'position-absolute top-0 start-0 m-4' }}" @if($mobile ?? false)@else style="top: 32px; left: 32px;" @endif>
  <i class="ti ti-robot me-2 ti-md"></i>
  {{ __('Prueba Humano Assistant') }}
</a>
