<!-- Navbar: Start -->
<nav class="layout-navbar shadow-none py-0 humano-front-navbar">
  <div class="container">
    <div class="navbar navbar-expand-lg humano-front-topnav px-0 px-md-2 py-2 justify-content-between align-items-center">
      <div class="navbar-brand app-brand demo d-flex py-0 py-lg-2 align-items-center ps-1 min-w-0 me-2 flex-shrink-1" style="max-width: min(14rem, calc(100vw - 12rem));">
        <a href="{{ url('/') }}" class="app-brand-link w-100 min-w-0">
          <span class="app-brand-logo demo d-flex align-items-center w-100 min-w-0">
            <img src="{{ Helper::logoAsset('dark') }}" alt="{{ config('app.name') }}" class="d-block" style="max-height: 3.25rem; width: auto; height: auto; max-width: 100%; object-fit: contain; object-position: left center;">
          </span>
        </a>
      </div>
      <ul class="navbar-nav flex-row align-items-center ms-auto gap-2">
        @if (Route::is('front-pages.landing'))
          <li class="d-none d-md-block">
            <a href="#landingFeatures" class="nav-link px-2">Beneficios</a>
          </li>
          <li class="d-none d-md-block">
            <a href="#landingPricing" class="nav-link px-2">Planes</a>
          </li>
          <li class="d-none d-md-block">
            <a href="#landingContact" class="nav-link px-2">Contacto</a>
          </li>
        @endif
        @if(Route::has('login'))
          <li>
            <a href="{{ route('login') }}" class="btn btn-primary">{{ __('Login') }}</a>
          </li>
        @endif
      </ul>
    </div>
  </div>
</nav>
<!-- Navbar: End -->
