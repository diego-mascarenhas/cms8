<span class="app-brand-logo demo logo-full d-block w-100 ps-1" style="max-width: 100%; box-sizing: border-box;" @if(!empty($logoId)) id="{{ $logoId }}" @endif>
  <a href="{{ url('/') }}" class="d-inline-block w-100" style="max-width: 100%;">
    <img
      src="{{ Helper::logoAssetForStyle() }}?v={{ config('variables.templateVersion', '1') }}"
      data-app-light-img="{{ Helper::logoThemeDataImg('light') }}"
      data-app-dark-img="{{ Helper::logoThemeDataImg('dark') }}"
      alt="{{ config('app.name') }}"
      class="d-block"
      style="max-height: 3.25rem; width: auto; height: auto; max-width: 100%; object-fit: contain; object-position: left center;"
    >
  </a>
</span>
