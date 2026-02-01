{{-- Left cover panel for auth pages (login, register, forgot-password). Include with $coverIllustration e.g. 'auth-login-illustration'. --}}
<div class="d-none d-lg-flex col-lg-7 p-0">
  <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center position-relative">
    @include('auth.partials.cta-button', ['mobile' => false])
    <img src="{{ asset('assets/img/illustrations/'.$coverIllustration.'-'.$configData['style'].'.png') }}" alt="{{ $coverIllustration }}" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/{{ $coverIllustration }}-light.png" data-app-dark-img="illustrations/{{ $coverIllustration }}-dark.png">

    <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-cover-bg" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
  </div>
</div>
