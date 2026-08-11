@php
$configData = Helper::appClasses();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

  <!-- ! Hide app brand if navbar-full -->
  @if(!isset($navbarFull))
  <div class="app-brand demo">
    <a href="{{url('/')}}" class="app-brand-link">
      <span id="menu-logo" class="app-brand-logo demo app-brand-img">
        <img
          src="{{ Helper::logoAssetForStyle($configData['style'] ?? 'light') }}"
          data-app-light-img="{{ Helper::logoThemeDataImg('light') }}"
          data-app-dark-img="{{ Helper::logoThemeDataImg('dark') }}"
          alt="{{ config('app.name') }}"
          style="height: 44px; width: auto;"
        >
      </span>
      <span class="app-brand-logo demo app-brand-img-collapsed">
        @include('_partials.macros', ['height' => 20])
      </span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
      <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
    </a>
  </div>
  @endif


  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    @foreach ($menuData[0]->menu as $menu)

    {{-- adding active and open class if child is active --}}

    {{-- menu headers --}}
    @if (isset($menu->menuHeader))
    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">{{ MenuHelper::menuLabel($menu->menuHeader) }}</span>
    </li>

    @else

    {{-- active menu method --}}
    @php
    $activeClass = \App\Helpers\MenuHelper::menuActiveClass($menu, Route::currentRouteName(), true);
    @endphp

    {{-- main menu --}}
    <li class="menu-item {{$activeClass}}">
      <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}" class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
        @isset($menu->icon)
        <i class="{{ $menu->icon }}"></i>
        @endisset
        <div>{{ MenuHelper::menuLabel($menu->name ?? null) }}</div>
        @isset($menu->badge)
        <div class="badge bg-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>

        @endisset
      </a>

      {{-- submenu --}}
      @isset($menu->submenu)
      @include('layouts.sections.menu.submenu',['menu' => $menu->submenu])
      @endisset
    </li>
    @endif
    @endforeach
  </ul>

</aside>

<style>
  @keyframes vibrate {
    0% { transform: translate(0); }
    25% { transform: translate(-2px, 2px); }
    50% { transform: translate(2px, -2px); }
    75% { transform: translate(-2px, -2px); }
    100% { transform: translate(0); }
  }
  .vibrate {
    animation: vibrate 0.5s infinite;
  }
</style>

<script>
  const animateLogo = {{ config('custom.animateLogo') ? 'true' : 'false' }};
  if (animateLogo) {
    setInterval(() => {
      const logo = document.getElementById('menu-logo');
      logo.classList.add('vibrate');
      setTimeout(() => {
        logo.classList.remove('vibrate');
      }, 500);
    }, 3600000);
  }
</script>
