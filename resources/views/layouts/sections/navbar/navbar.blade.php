@php
    $containerNav =
        isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact'
            ? 'container-xxl'
            : 'container-fluid';
    $navbarDetached = $navbarDetached ?? '';
@endphp

<!-- Navbar -->
@if (isset($navbarDetached) && $navbarDetached == 'navbar-detached')
    <nav class="layout-navbar {{ $containerNav }} navbar navbar-expand-xl {{ $navbarDetached }} align-items-center bg-navbar-theme"
        id="layout-navbar">
@endif
@if (isset($navbarDetached) && $navbarDetached == '')
    <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="{{ $containerNav }}">
@endif

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if (isset($navbarFull))
    <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
        <a href="{{ url('/') }}" class="app-brand-link gap-2">
            <span class="app-brand-logo demo">
                @include('_partials.macros', ['height' => 20])
            </span>
            <span class="app-brand-text demo menu-text fw-bold">{{ config('variables.templateName') }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
            <i class="ti ti-x ti-sm align-middle"></i>
        </a>
    </div>
@endif

<!-- ! Not required for layout-without-menu -->
@if (!isset($navbarHideToggle))
    <div
        class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="ti ti-menu-2 ti-sm"></i>
        </a>
    </div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

    @if (!isset($menuHorizontal))
        <!-- Search -->
        <div class="navbar-nav align-items-center">
            <div class="nav-item navbar-search-wrapper mb-0">
                <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
                    <i class="ti ti-search ti-md me-2"></i>
                    <span class="d-none d-md-inline-block text-muted">{{ __('app.search_with_shortcut') }}</span>
                </a>
            </div>
        </div>
        <!-- /Search -->
    @endif
    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <!-- Language -->
        @if ($configData['showLanguageSelector'] == true && Auth::user()->hasRole('developer'))
        <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                <i class='ti ti-language rounded-circle ti-md'></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
                        href="{{ url('lang/en') }}" data-language="en" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.english') }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'es' ? 'active' : '' }}"
                        href="{{ url('lang/es') }}" data-language="es" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.spanish') }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'fr' ? 'active' : '' }}"
                        href="{{ url('lang/fr') }}" data-language="fr" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.french') }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'de' ? 'active' : '' }}"
                        href="{{ url('lang/de') }}" data-language="de" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.german') }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'it' ? 'active' : '' }}"
                        href="{{ url('lang/it') }}" data-language="it" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.italian') }}</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === 'pt' ? 'active' : '' }}"
                        href="{{ url('lang/pt') }}" data-language="pt" data-text-direction="ltr">
                        <span class="align-middle">{{ __('app.languages.portuguese') }}</span>
                    </a>
                </li>
            </ul>
        </li>
        @endif
        <!--/ Language -->

        @if (isset($menuHorizontal))
            <!-- Search -->
            <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
                <a class="nav-link search-toggler" href="javascript:void(0);">
                    <i class="ti ti-search ti-md"></i>
                </a>
            </li>
            <!-- /Search -->
        @endif
        @if ($configData['hasCustomizer'] == true)
            <!-- Style Switcher -->
            <li class="nav-item dropdown-style-switcher dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class='ti ti-md'></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                            <span class="align-middle">{{ __('app.theme.light') }}</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                            <span class="align-middle">{{ __('app.theme.dark') }}</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                            <span class="align-middle">{{ __('app.theme.system') }}</span>
                        </a>
                    </li>
                </ul>
            </li>
            <!--/ Style Switcher -->
        @endif

        <!-- Quick links  -->
        @if ($configData['showQuickAccess'] == true || Auth::user()->hasRole('developer'))
            <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false">
                    <i class='ti ti-layout-grid-add ti-md'></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end py-0">
                    <div class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h5 class="text-body mb-0 me-auto">{{ __('app.shortcuts.title') }}</h5>
                        </div>
                    </div>
                    <div class="dropdown-shortcuts-list scrollable-container">
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                                    <i class="ti ti-calendar fs-4"></i>
                                </span>
                                <a href="{{ url('app/calendar') }}"
                                    class="stretched-link">{{ __('app.shortcuts.calendar') }}</a>
                                <small class="text-muted mb-0">{{ __('app.shortcuts.appointments') }}</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                                    <i class="ti ti-file-invoice fs-4"></i>
                                </span>
                                <a href="{{ url('app/invoice/list') }}"
                                    class="stretched-link">{{ __('app.shortcuts.invoice_app') }}</a>
                                <small class="text-muted mb-0">{{ __('app.shortcuts.manage_accounts') }}</small>
                            </div>
                        </div>
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                                    <i class="ti ti-users fs-4"></i>
                                </span>
                                <a href="{{ url('user-management') }}"
                                    class="stretched-link">{{ __('app.shortcuts.user_app') }}</a>
                                <small class="text-muted mb-0">{{ __('app.shortcuts.manage_users') }}</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                                    <i class="ti ti-settings fs-4"></i>
                                </span>
                                <a href="{{ url('account-management') }}"
                                    class="stretched-link">{{ __('app.shortcuts.accounts') }}</a>
                                <small class="text-muted mb-0">{{ __('app.shortcuts.accounts_settings') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        @endif
        <!-- Quick links -->

        <!-- Notification -->
        @if ($configData['showNotifications'] == true)
            <x-task-notifications />
        @endif
        <!--/ Notification -->

        <!-- WhatsApp Support -->
        @if(config('app.whatsapp_support'))
            <li class="nav-item me-3 me-xl-1">
                <a class="nav-link" href="https://wa.me/{{ trim(config('app.whatsapp_support')) }}" target="_blank"
                   data-bs-toggle="tooltip" data-bs-placement="bottom" title="Soporte por WhatsApp">
                    <i class="ti ti-brand-whatsapp ti-md"></i>
                </a>
            </li>
        @endif

        <!-- Help Center -->
        @livewire('help-center-icon')
        <!-- /Help Center -->

        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                    <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/1.png') }}"
                        alt class="h-auto rounded-circle">
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item"
                        href="{{ Route::has('profile.show') ? route('profile.show') : url('pages/profile-user') }}">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-online">
                                    <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/1.png') }}"
                                        alt class="h-auto rounded-circle">
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <span class="fw-medium d-block">
                                    @if (Auth::check())
                                        {{ Auth::user()->name }}
                                    @endif
                                </span>
                                <small class="text-muted">
                                    @if (Auth::check() && Auth::user()->roles()->exists())
                                        @foreach (Auth::user()->roles as $role)
                                            {{ ucfirst($role->name) }}
                                            @if (!$loop->last)
                                                ,
                                            @endif
                                        @endforeach
                                    @endif
                                </small>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider"></div>
                </li>
                <li>
                    <a class="dropdown-item"
                        href="{{ Route::has('profile.show') ? route('profile.show') : url('pages/profile-user') }}">
                        <i class="ti ti-user-check me-2 ti-sm"></i>
                        <span class="align-middle">{{ __('app.profile.my_profile') }}</span>
                    </a>
                </li>

                @if (Auth::check() && auth()->user()->currentTeam && auth()->user()->ownsTeam(auth()->user()->currentTeam))
                    <li>
                        <a class="dropdown-item" href="{{ route('team-settings.edit', auth()->user()->currentTeam->id) }}">
                            <i class="ti ti-settings-automation me-2 ti-sm"></i>
                            <span class="align-middle">Settings</span>
                        </a>
                    </li>
                @endif

                @if (Auth::check() && Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <li>
                        <a class="dropdown-item" href="{{ route('api-tokens.index') }}">
                            <i class='ti ti-key me-2 ti-sm'></i>
                            <span class="align-middle">{{ __('app.profile.api_tokens') }}</span>
                        </a>
                    </li>
                @endif
                <!--
              <li>
                <a class="dropdown-item" href="{{ url('app/invoice/list') }}">
                  <span class="d-flex align-items-center align-middle">
                    <i class="flex-shrink-0 ti ti-credit-card me-2 ti-sm"></i>
                    <span class="flex-grow-1 align-middle">Billing</span>
                    <span class="flex-shrink-0 badge badge-center rounded-pill bg-label-danger w-px-20 h-px-20">2</span>
                  </span></a>
              </li>
              -->
                @if ((Auth::User() && Laravel\Jetstream\Jetstream::hasTeamFeatures() && config('custom.TeamManager')) || (Auth::check() && Auth::user()->hasRole('admin')))
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <h6 class="dropdown-header">{{ __('app.profile.team.manage') }}</h6>
                    </li>
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    @if (Auth::check() && Auth::user()->currentTeam)
                        <li>
                            <a class="dropdown-item" href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                                <i class='ti ti-settings me-2'></i>
                                <span class="align-middle">{{ __('app.profile.team.settings') }}</span>
                            </a>
                        </li>
                    @endif
                    @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                        <li>
                            <a class="dropdown-item" href="{{ route('teams.create') }}">
                                <i class='ti ti-user me-2'></i>
                                <span class="align-middle">{{ __('app.profile.team.create') }}</span>
                            </a>
                        </li>
                    @endcan
                    @if (Auth::check() && Auth::user()->allTeams()->count() > 1)
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li>
                            <h6 class="dropdown-header">{{ __('app.profile.team.switch') }}</h6>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                    @endif
                    @if (Auth::user())
                        @foreach (Auth::user()->allTeams() as $team)
                            {{-- Below commented code read by artisan command while installing jetstream. !! Do not remove if you want to use jetstream. --}}

                            <x-switchable-team :team="$team" />
                        @endforeach
                    @endif
                @endif
                <li>
                    <div class="dropdown-divider"></div>
                </li>
                @if (Auth::check())
                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class='ti ti-logout me-2'></i>
                            <span class="align-middle">{{ __('app.profile.logout') }}</span>
                        </a>
                    </li>
                    <form method="POST" id="logout-form" action="{{ route('logout') }}">
                        @csrf
                    </form>
                @else
                    <li>
                        <a class="dropdown-item"
                            href="{{ Route::has('login') ? route('login') : url('auth/login-basic') }}">
                            <i class='ti ti-login me-2'></i>
                            <span class="align-middle">{{ __('app.profile.login') }}</span>
                        </a>
                    </li>
                @endif
            </ul>
        </li>
        <!--/ User -->
    </ul>
</div>

<!-- Search Small Screens -->
<div class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }} d-none">
    <input type="text"
        class="form-control search-input {{ isset($menuHorizontal) ? '' : $containerNav }} border-0"
        placeholder="{{ __('app.search') }}..." aria-label="Search...">
    <i class="ti ti-x ti-sm search-toggler cursor-pointer"></i>
</div>
@if (isset($navbarDetached) && $navbarDetached == '')
    </div>
@endif
</nav>
<!-- / Navbar -->

<div id="search-spinner" class="spinner-border text-primary d-none" role="status">
    <span class="visually-hidden">{{ __('app.searching') }}...</span>
</div>
