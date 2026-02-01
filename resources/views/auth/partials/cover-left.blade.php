{{-- Left cover panel for auth pages (login, register, forgot-password). Include with $coverIllustration e.g. 'auth-login-illustration'. --}}
<div class="d-none d-lg-flex col-lg-7 p-0">
  <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center position-relative">
    <a href="{{ route('landing') }}" class="btn btn-light position-absolute top-0 start-0 m-4" style="top: 32px; left: 32px;">
      <i class="ti ti-message-question me-1"></i>
      Cuéntanos tu problema de negocio
    </a>
    <img src="{{ asset('assets/img/illustrations/'.$coverIllustration.'-'.$configData['style'].'.png') }}" alt="{{ $coverIllustration }}" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/{{ $coverIllustration }}-light.png" data-app-dark-img="illustrations/{{ $coverIllustration }}-dark.png">

    <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-cover-bg" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
  </div>
</div>
