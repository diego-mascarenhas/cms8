@php
$containerFooter = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-center py-2 flex-md-row flex-column m-2">
      <div class="small">
        <em>
          ©<script>document.write(new Date().getFullYear())</script>, powered by <a href="{{ route('terms') }}" target="_blank" class="fw-medium">{{ (!empty(config('variables.creatorName')) ? config('variables.creatorName') : '') }}</a>
        </em>
      </div>
    </div>
  </div>
</footer>
<!--/ Footer-->