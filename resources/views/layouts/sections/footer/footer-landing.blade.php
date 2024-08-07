@php
$containerFooter = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-2 flex-md-row flex-column">
      <div>
        <em>
          ©<script>document.write(new Date().getFullYear())
          </script>, powered by <a href="{{ route('terms') }}" target="_blank" class="fw-medium">{{ (!empty(config('variables.creatorName')) ? config('variables.creatorName') : '') }}</a>
        </em>
      </div>
      <div class="d-none d-lg-inline-block">
        <a href="{{ url('/chat') }}" target="_blank" class="footer-link d-none d-sm-inline-block">Chat whit Us</a>
      </div>
    </div>
  </div>
</footer>
<!--/ Footer-->
