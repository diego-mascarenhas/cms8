<span class="app-brand-logo demo logo-full d-block w-100 ps-1" style="max-width: 100%; box-sizing: border-box;" @if(!empty($logoId)) id="{{ $logoId }}" @endif>
  <a href="{{ url('/') }}" class="d-inline-block w-100" style="max-width: 100%;">
    <img src="{{ Helper::logoAsset('dark') }}?v={{ config('variables.templateVersion', '1') }}" alt="{{ config('app.name') }}" class="d-block" style="max-height: 3.25rem; width: auto; height: auto; max-width: 100%; object-fit: contain; object-position: left center;">
  </a>
</span>
