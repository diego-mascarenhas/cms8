{{-- Left illustration panel. Hidden when AUTH_MINIMAL_LAYOUT=true. Pass $coverIllustration (e.g. auth-login-illustration). --}}
@unless (config('custom.custom.authMinimalLayout'))
<div class="d-none d-lg-flex col-lg-7 p-0">
  <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
    <img src="{{ asset('assets/img/illustrations/'.$coverIllustration.'-'.$configData['style'].'.png') }}" alt="{{ $coverIllustration }}" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/{{ $coverIllustration }}-light.png" data-app-dark-img="illustrations/{{ $coverIllustration }}-dark.png">

    <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-cover-bg" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
  </div>
</div>
@endunless
