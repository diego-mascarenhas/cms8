<span class="app-brand-logo demo logo-full" @if(!empty($logoId)) id="{{ $logoId }}" @endif>
  <a href="{{ url('/') }}">
    <img src="{{ Helper::logoAsset('dark') }}?v={{ config('variables.templateVersion', '1') }}" alt="{{ config('app.name') }}" height="40" style="width: auto;">
  </a>
</span>
