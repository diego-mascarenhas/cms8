@php
    $showHumanoSectionNav = Route::is('humano', 'pricing', 'front-pages.pricing');
@endphp
<!-- Navbar: Start -->
<nav class="layout-navbar shadow-none py-0 humano-front-navbar">
  <div class="container">
    <div class="navbar navbar-expand-lg humano-front-topnav px-0 px-md-2 py-2">
      <div class="navbar-brand app-brand demo d-flex py-0 py-lg-2 align-items-center ps-1 min-w-0 me-2 flex-shrink-1" style="max-width: min(14rem, calc(100vw - 12rem));">
        <a href="{{ url('/') }}" class="app-brand-link w-100 min-w-0">
          <span class="app-brand-logo demo d-flex align-items-center w-100 min-w-0">
            <img
              src="{{ Helper::logoAsset('light') }}"
              data-app-light-img="{{ Helper::logoThemeDataImg('light') }}"
              data-app-dark-img="{{ Helper::logoThemeDataImg('dark') }}"
              alt="{{ config('app.name') }}"
              class="d-block"
              style="max-height: 3.25rem; width: auto; height: auto; max-width: 100%; object-fit: contain; object-position: left center;"
            >
          </span>
        </a>
      </div>

      @if ($showHumanoSectionNav || Route::has('login'))
        <button
          class="navbar-toggler border-0 shadow-none ms-auto"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#humanoFrontNavCollapse"
          aria-controls="humanoFrontNavCollapse"
          aria-expanded="false"
          aria-label="{{ __('Toggle navigation') }}"
        >
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="humanoFrontNavCollapse">
          <ul class="navbar-nav ms-lg-auto align-items-lg-center gap-lg-2 pt-3 pt-lg-0 pb-2 pb-lg-0">
            @if ($showHumanoSectionNav)
              @include('homes.humano.partials.nav-links')
            @endif
            @if (Route::has('login'))
              <li class="nav-item ms-lg-2">
                <a href="{{ route('login') }}" class="btn btn-primary w-100 w-lg-auto">{{ __('Login') }}</a>
              </li>
            @endif
          </ul>
        </div>
      @endif
    </div>
  </div>
</nav>
<!-- Navbar: End -->
