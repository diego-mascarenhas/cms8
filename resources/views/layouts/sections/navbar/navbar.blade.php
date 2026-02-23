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

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse" x-data="globalSearch()" x-init="init()">

    @if (!isset($menuHorizontal) && (($configData['showSearch'] ?? true) === true))
        <!-- Search -->
        <div class="navbar-nav align-items-center">
            <div class="nav-item navbar-search-wrapper mb-0">
                <!-- Search Toggle Link - Hidden when search is active -->
                <a class="nav-item nav-link search-toggler d-flex align-items-center px-0"
                   href="javascript:void(0);"
                   :class="{ 'd-none': !isHidden }"
                   @click="open()"
                   x-cloak>
                    <i class="ti ti-search ti-md me-2"></i>
                    <span class="d-none d-md-inline-block text-muted">{{ __('app.search_with_shortcut') }}</span>
                </a>

                <!-- Search Input - Shown when search is active -->
                <div class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }}"
                     :class="{ 'd-none': isHidden }"
                     x-cloak>
                    <input type="text"
                        class="form-control search-input {{ isset($menuHorizontal) ? '' : $containerNav }} border-0"
                           placeholder="{{ __('app.search') }}..."
                           aria-label="Search..."
                           style="color: inherit !important;"
                           x-model="query"
                           @input.debounce.300ms="search()"
                           @keydown.arrow-down.prevent="navigateDown()"
                           @keydown.arrow-up.prevent="navigateUp()"
                           @keydown.enter.prevent="selectCurrent()"
                           @keydown.escape="close()"
                           x-ref="searchInput">
                    <i class="ti ti-x ti-sm search-toggler cursor-pointer" @click="close()"></i>

                    <!-- Search Results Dropdown -->
                    <div class="twitter-typeahead">
                        <div class="tt-menu navbar-search-suggestion"
                                 x-cloak
                                 x-ref="resultsContainer"
                                 x-bind:style="(showResults && hasResults) ? 'display: block !important; visibility: visible !important;' : 'display: none !important;'">
                                <!-- Members (Contactos) -->
                                <template x-if="results.members && results.members.length > 0">
                                    <div>
                                        <h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Contactos</h6>
                                        <template x-for="(item, index) in results.members" :key="index">
                                            <a :href="item.url || '#'"
                                               class="suggestion d-flex justify-content-between px-3 py-2 w-100"
                                               :class="{ 'active': selectedItem && selectedItem.category === 'members' && selectedItem.index === index }"
                                               @mouseenter="selectedItem = { category: 'members', index: index }"
                                               @click.prevent="navigateTo(item.url)">
                                                <div class="d-flex align-items-center">
                                                    <i class="ti ti-user me-2"></i>
                                                    <div class="user-info">
                                                        <h6 class="mb-0" x-text="item.name"></h6>
                                                        <small class="text-muted" x-text="item.subtitle"></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <!-- Enterprises (Empresas) -->
                                <template x-if="results.enterprises && results.enterprises.length > 0">
                                    <div>
                                        <h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Empresas</h6>
                                        <template x-for="(item, index) in results.enterprises" :key="index">
                                            <a :href="item.url || '#'"
                                               class="suggestion d-flex justify-content-between px-3 py-2 w-100"
                                               :class="{ 'active': selectedItem && selectedItem.category === 'enterprises' && selectedItem.index === index }"
                                               @mouseenter="selectedItem = { category: 'enterprises', index: index }"
                                               @click.prevent="navigateTo(item.url)">
                                                <div class="d-flex align-items-center">
                                                    <i class="ti ti-building me-2"></i>
                                                    <div class="user-info">
                                                        <h6 class="mb-0" x-text="item.name"></h6>
                                                        <small class="text-muted" x-text="item.subtitle"></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <!-- Services (Servicios) -->
                                <template x-if="results.services && results.services.length > 0">
                                    <div>
                                        <h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Servicios</h6>
                                        <template x-for="(item, index) in results.services" :key="index">
                                            <a :href="item.url || '#'"
                                               class="suggestion d-flex justify-content-between px-3 py-2 w-100"
                                               :class="{ 'active': selectedItem && selectedItem.category === 'services' && selectedItem.index === index }"
                                               @mouseenter="selectedItem = { category: 'services', index: index }"
                                               @click.prevent="navigateTo(item.url)">
                                                <div class="d-flex align-items-center">
                                                    <i class="ti ti-world me-2"></i>
                                                    <div class="user-info">
                                                        <h6 class="mb-0" x-text="item.name"></h6>
                                                        <small class="text-muted" x-text="item.subtitle"></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <!-- Projects (Proyectos) -->
                                <template x-if="results.projects && results.projects.length > 0">
                                    <div>
                                        <h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Proyectos</h6>
                                        <template x-for="(item, index) in results.projects" :key="index">
                                            <a :href="item.url || '#'"
                                               class="suggestion d-flex justify-content-between px-3 py-2 w-100"
                                               :class="{ 'active': selectedItem && selectedItem.category === 'projects' && selectedItem.index === index }"
                                               @mouseenter="selectedItem = { category: 'projects', index: index }"
                                               @click.prevent="navigateTo(item.url)">
                                                <div class="d-flex align-items-center">
                                                    <i class="ti ti-folder me-2"></i>
                                                    <div class="user-info">
                                                        <h6 class="mb-0" x-text="item.name"></h6>
                                                        <small class="text-muted" x-text="item.subtitle"></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <!-- Invoices (Facturas) -->
                                <template x-if="results.invoices && results.invoices.length > 0">
                                    <div>
                                        <h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Facturas</h6>
                                        <template x-for="(item, index) in results.invoices" :key="index">
                                            <a :href="item.url || '#'"
                                               class="suggestion d-flex justify-content-between px-3 py-2 w-100"
                                               :class="{ 'active': selectedItem && selectedItem.category === 'invoices' && selectedItem.index === index }"
                                               @mouseenter="selectedItem = { category: 'invoices', index: index }"
                                               @click.prevent="navigateTo(item.url)">
                                                <div class="d-flex align-items-center">
                                                    <i class="ti ti-file-invoice me-2"></i>
                                                    <div class="user-info">
                                                        <h6 class="mb-0" x-text="item.name"></h6>
                                                        <small class="text-muted" x-text="item.subtitle"></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>
                                </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Search -->
    @endif
    <ul class="navbar-nav flex-row align-items-center ms-auto" :class="{ 'd-none': !isHidden }">
        {{-- Quick Time Tracker (attendance clock-in/out) --}}
        @auth
        <li class="nav-item dropdown me-2" id="quick-timer"
            data-running-url="{{ route('attendance.running') }}"
            data-start-url="{{ route('attendance.start') }}"
            data-stop-url="/attendance/:ID/stop">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
               aria-expanded="false" aria-label="{{ __('Attendance clock') }}">
                <i class="ti ti-clock ti-md text-muted" id="quick-timer-icon"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 320px;">
                <li class="px-3 pt-2 pb-1 d-flex align-items-center">
                    <i class="ti ti-clock me-2" id="quick-timer-icon-inline"></i>
                    <span id="quick-timer-display" class="fw-semibold" style="font-variant-numeric: tabular-nums;">00:00:00</span>
                </li>
                <li class="px-3 pb-2 small text-muted d-none" id="project-running-row">
                    <a href="{{ route('time.index') }}" class="text-decoration-none">
                        <i class="ti ti-hourglass-low me-1"></i>
                        <span id="project-running-name">—</span>
                    </a>
                </li>
                <li><div class="dropdown-divider"></div></li>
                <li><a class="dropdown-item" href="javascript:;" id="att-start"><i class="ti ti-player-play me-2"></i>{{ __('Inicio de jornada') }}</a></li>
                <li><a class="dropdown-item" href="javascript:;" id="att-pause"><i class="ti ti-player-pause me-2"></i>{{ __('Pausar') }}</a></li>
                <li><a class="dropdown-item" href="javascript:;" id="att-resume"><i class="ti ti-player-track-next me-2"></i>{{ __('Reanudar') }}</a></li>
                <li><a class="dropdown-item text-danger" href="javascript:;" id="att-stop"><i class="ti ti-player-stop me-2"></i>{{ __('Fin de jornada') }}</a></li>
            </ul>
        </li>
        @endauth
        <!-- Language -->
        @if ($configData['showLanguageSelector'] && Auth::user()->hasRole('developer'))
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

        @if (isset($menuHorizontal) && (($configData['showSearch'] ?? true) === true))
            <!-- Search -->
            <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
                <a class="nav-link search-toggler" href="javascript:void(0);">
                    <i class="ti ti-search ti-md"></i>
                </a>
            </li>
            <!-- /Search -->
        @endif
        @if ($configData['hasCustomizer'])
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
        @if ($configData['showQuickAccess'] || Auth::user()->hasRole('developer'))
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
                        @php
                            $teamShortcuts = auth()->user()->currentTeam ? auth()->user()->currentTeam->getSetting('team_shortcuts', []) : [];
                        @endphp

                        @if(count($teamShortcuts) > 0)
                            @foreach($teamShortcuts as $index => $shortcut)
                                @if($index % 2 === 0)
                                    <div class="row row-bordered overflow-visible g-0">
                                @endif

                                <div class="dropdown-shortcuts-item col">
                                    <span class="dropdown-shortcuts-icon rounded-circle mb-2">
                                        <i class="{{ $shortcut['icon'] ?? 'ti ti-link' }} fs-4"></i>
                                    </span>
                                    <a href="{{ $shortcut['url'] ?? '#' }}"
                                       class="stretched-link"
                                       @if(isset($shortcut['open_in_new_tab']) && $shortcut['open_in_new_tab']) target="_blank" @endif>
                                        {{ $shortcut['title'] ?? 'Shortcut' }}
                                    </a>
                                    <small class="text-muted mb-0">{{ $shortcut['subtitle'] ?? '' }}</small>
                                </div>

                                @if($index % 2 === 1 || $index === count($teamShortcuts) - 1)
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <!-- Default shortcuts when no team shortcuts are configured -->
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
                        @endif
                    </div>
                </div>
            </li>
        @endif
        <!-- Quick links -->

        <!-- Notification -->
        @if ($configData['showNotifications'])
            <x-task-notifications />
        @endif
        <!--/ Notification -->

        <!-- Mail -->
        <li class="nav-item me-3 me-xl-1">
            <a class="nav-link" href="{{ route('mail-list') }}" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ __('Mail') }}">
                <i class="ti ti-mail ti-md"></i>
            </a>
        </li>
        <!--/ Mail -->

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
		@if(auth()->user()->currentTeam && auth()->user()->currentTeam->hasModule('chat'))
			@livewire('help-center-icon')
		@endif
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

                <li>
                    <a class="dropdown-item" href="{{ route('billing.index') }}">
                        <i class="ti ti-credit-card me-2 ti-sm"></i>
                        <span class="align-middle">Facturación y Planes</span>
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="{{ route('help.index') }}" target="_blank">
                        <i class="ti ti-help me-2 ti-sm"></i>
                        <span class="align-middle">{{ __('app.profile.help_documentation') }}</span>
                    </a>
                </li>

                @if (Auth::check() && auth()->user()->currentTeam && (auth()->user()->ownsTeam(auth()->user()->currentTeam) || auth()->user()->hasRole('root')))
                    {{-- Configuration variables (Team Settings module) --}}
                    <li>
                        <a class="dropdown-item" href="{{ route('team-settings.index', auth()->user()->currentTeam) }}">
                            <i class="ti ti-adjustments-alt me-2 ti-sm"></i>
                            <span class="align-middle">{{ __('app.profile.team.variables') }}</span>
                        </a>
                    </li>
                @endif

            {{-- Root-only: Account Management --}}
            @if (Auth::check() && Auth::user()->hasRole('root'))
                <li>
                    <a class="dropdown-item" href="{{ url('account-management') }}">
                        <i class="ti ti-shield-lock me-2 ti-sm"></i>
                        <span class="align-middle">{{ __('app.profile.team.account_management') }}</span>
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

@auth
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('quick-timer');
    if (!container) return;

    const displayEl = document.getElementById('quick-timer-display');
    const startEl = document.getElementById('att-start');
    const pauseEl = document.getElementById('att-pause');
    const resumeEl = document.getElementById('att-resume');
    const stopEl = document.getElementById('att-stop');
    const runningUrl = container.getAttribute('data-running-url');
    const startUrl = container.getAttribute('data-start-url');
    const stopUrlTpl = container.getAttribute('data-stop-url');
    const timeRunningUrl = '{{ route('time.running') }}';

    let running = false;
    let paused = false;
    let startTs = null;
    let pausedTs = null;
    let pausedSeconds = 0;
    let timerId = null;
    let timerInterval = null;

    function fmt(n){ return String(n).padStart(2,'0'); }
    function ts(input){
        if (!input) return null;
        if (typeof input === 'number') return Math.floor(input);
        // Try native ISO first
        let d = new Date(input);
        if (!isNaN(d.getTime())) return Math.floor(d.getTime()/1000);
        // Fallback for 'YYYY-MM-DD HH:MM:SS'
        const m = String(input).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/);
        if (m){
            const year = parseInt(m[1],10), mon = parseInt(m[2],10)-1, day = parseInt(m[3],10);
            const hh = parseInt(m[4],10), mm = parseInt(m[5],10), ss = parseInt(m[6],10);
            const local = new Date(year, mon, day, hh, mm, ss);
            return Math.floor(local.getTime()/1000);
        }
        return null;
    }
    function render(){
        if (!running || !startTs) { displayEl.textContent = '00:00:00'; return; }
        const now = Math.floor(Date.now() / 1000);
        const effectiveNow = paused && pausedTs ? pausedTs : now;
        const elapsedRaw = Math.max(0, effectiveNow - startTs);
        const elapsed = Math.max(0, elapsedRaw - (pausedSeconds || 0));
        const h = Math.floor(elapsed / 3600);
        const m = Math.floor((elapsed % 3600) / 60);
        const s = elapsed % 60;
        displayEl.textContent = `${fmt(h)}:${fmt(m)}:${fmt(s)}`;
    }

    function startTick(){ if (timerInterval) clearInterval(timerInterval); timerInterval = setInterval(render, 1000); render(); }
    function stopTick(){ if (timerInterval) clearInterval(timerInterval); timerInterval = null; }

    function setButton(){
        const icon = document.getElementById('quick-timer-icon');
        const iconInline = document.getElementById('quick-timer-icon-inline');
        const applyIconState = (el) => {
            if (!el) return;
            el.classList.remove('text-success', 'text-muted', 'text-warning');
            if (!running) {
                el.classList.add('text-muted');
            } else if (paused) {
                el.classList.add('text-warning');
            } else {
                el.classList.add('text-success');
            }
        };
        applyIconState(icon);
        applyIconState(iconInline);
		const setDisabled = (el, isDisabled) => {
			if (!el) return;
			if (isDisabled) {
				el.classList.add('disabled');
				el.classList.add('text-muted');
				if (el.id === 'att-stop') el.classList.remove('text-danger');
				el.setAttribute('aria-disabled', 'true');
				el.setAttribute('tabindex', '-1');
			} else {
				el.classList.remove('disabled');
				el.classList.remove('text-muted');
				if (el.id === 'att-stop') el.classList.add('text-danger');
				el.removeAttribute('aria-disabled');
				el.removeAttribute('tabindex');
			}
		};
        setDisabled(startEl, running);
        setDisabled(pauseEl, !running || paused);
        setDisabled(resumeEl, !running || !paused);
        setDisabled(stopEl, !running);
    }

    function fetchRunning(){
        fetch(runningUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (data && data.running && data.attendance) {
                    running = true;
                    timerId = data.attendance.id;
                    startTs = ts(data.attendance.start_at);
                    paused = !!data.attendance.paused_at;
                    pausedTs = paused ? Math.floor(new Date(data.attendance.paused_at).getTime() / 1000) : null;
                    pausedSeconds = parseInt(data.attendance.paused_seconds || 0, 10);
                    startTick();
                } else {
                    running = false; paused = false; timerId = null; startTs = null; pausedTs = null; pausedSeconds = 0; stopTick(); render();
                }
                setButton();
            })
            .catch(() => {});

                // Fetch running time and show task title above
        fetch(timeRunningUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                const row = document.getElementById('project-running-row');
                const nameEl = document.getElementById('project-running-name');
                if (data && data.running && data.time) {
                    const taskTitle = data.time.task ? data.time.task.title : '{{ __('Tiempo en proyecto') }}';
                    if (row && nameEl) {
                        nameEl.textContent = taskTitle;
                        row.classList.remove('d-none');
                    }
                } else if (row) {
                    row.classList.add('d-none');
                }
            })
            .catch(() => {});
    }

    if (startEl) startEl.addEventListener('click', function(){
        if (!running) {
            fetch(startUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success && data.attendance) {
                    running = true;
                    paused = false;
                    timerId = data.attendance.id;
                    startTs = Math.floor(new Date(data.attendance.start_at).getTime() / 1000);
                    pausedTs = null;
                    pausedSeconds = 0;
                    setButton();
                    startTick();
                }
            });
        }
    });

    if (pauseEl) pauseEl.addEventListener('click', function(){
        if (running && timerId) {
            const pauseUrl = '{{ route('attendance.pause', [ 'id' => ':ID' ]) }}'.replace(':ID', timerId);
            fetch(pauseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            }).then(r => r.json()).then(data => {
                if (data && data.success && data.attendance) {
                    paused = true;
                    pausedTs = data.attendance.paused_at ? ts(data.attendance.paused_at) : Math.floor(Date.now()/1000);
                    // pausedSeconds remains the cumulative value before this pause
                    setButton();
                    render();
                }
            });
        }
    });

    if (resumeEl) resumeEl.addEventListener('click', function(){
        if (running && timerId) {
            const resumeUrl = '{{ route('attendance.resume', [ 'id' => ':ID' ]) }}'.replace(':ID', timerId);
            fetch(resumeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            }).then(r => r.json()).then(data => {
                if (data && data.success && data.attendance) {
                    paused = false;
                    pausedTs = null;
                    pausedSeconds = parseInt(data.attendance.paused_seconds || 0, 10);
                    setButton();
                }
            });
        }
    });

    if (stopEl) stopEl.addEventListener('click', function(){
        if (running && timerId) {
            const stopUrl = stopUrlTpl.replace(':ID', timerId);
            fetch(stopUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    running = false; paused = false; timerId = null; startTs = null; pausedTs = null; pausedSeconds = 0; stopTick(); render(); setButton();
                }
            });
        }
    });

    // Defer loading by 1 second to avoid blocking initial page load
    setTimeout(fetchRunning, 1000);
});
</script>
@endauth

@if (isset($navbarDetached) && $navbarDetached == '')
    </div>
@endif
</nav>
<!-- / Navbar -->

<div id="search-spinner" class="spinner-border text-primary d-none" role="status">
    <span class="visually-hidden">{{ __('app.searching') }}...</span>
</div>

<script>
function globalSearch() {
    return {
        query: '',
        results: {},
        isHidden: true,
        showResults: false,
        selectedItem: null,
        psInstance: null,
        baseUrl: '',
        isLoading: false,
        ajaxCache: {
            lastQuery: null,
            lastResponse: null,
            inflight: null
        },

        init() {
            // Get baseUrl from HTML attribute
            const htmlEl = document.documentElement;
            this.baseUrl = (htmlEl.getAttribute('data-base-url') || '') + '/';

            // Setup keyboard shortcut CTRL+/ or CMD+/
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && (e.key === '/' || e.keyCode === 191)) {
                    e.preventDefault();
                    this.open();
                }
            });

            // Setup click handlers for search togglers
            document.querySelectorAll('.search-toggler').forEach(btn => {
                if (!btn.closest('.search-input-wrapper')) {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.open();
                    });
                }
            });

            // Setup click outside handler to close search
            const handleClickOutside = (e) => {
                if (this.isHidden) return; // Search is already closed

                const searchWrapper = this.$el?.querySelector('.search-input-wrapper');
                const resultsContainer = this.$el?.querySelector('.tt-menu.navbar-search-suggestion');
                const searchToggler = this.$el?.querySelector('.search-toggler:not(.search-input-wrapper .search-toggler)');

                // Check if click is outside search area
                const clickedInsideSearch = searchWrapper?.contains(e.target) ||
                                          resultsContainer?.contains(e.target) ||
                                          searchToggler?.contains(e.target);

                if (!clickedInsideSearch) {
                    this.close();
                }
            };

            document.addEventListener('mousedown', handleClickOutside);
        },

        get hasResults() {
            return (this.results.members && this.results.members.length > 0) ||
                   (this.results.enterprises && this.results.enterprises.length > 0) ||
                   (this.results.services && this.results.services.length > 0) ||
                   (this.results.projects && this.results.projects.length > 0) ||
                   (this.results.invoices && this.results.invoices.length > 0);
        },

        open() {
            this.isHidden = false;
            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },

        close() {
            this.isHidden = true;
            this.query = '';
            this.results = {};
            this.showResults = false;
            this.selectedItem = null;
            this.updateBackdrop(false);

            // Destroy PerfectScrollbar if initialized
            if (this.psInstance) {
                try {
                    this.psInstance.destroy();
                } catch (e) {}
                this.psInstance = null;
            }
        },

        async search() {
            const query = this.query.trim();

            if (!query || query.length < 3) {
                this.results = {};
                this.showResults = false;
                this.selectedItem = null;
                this.updateBackdrop(false);
                if (this.psInstance) {
                    try {
                        this.psInstance.destroy();
                    } catch (e) {}
                    this.psInstance = null;
                }
                return;
            }

            // Check cache first
            if (this.ajaxCache.lastQuery === query && this.ajaxCache.lastResponse) {
                this.results = this.ajaxCache.lastResponse;
                this.showResults = true;
                this.selectedItem = null;
                this.updateBackdrop(true);
                this.$nextTick(() => {
                    this.forceDisplayUpdate();
                    this.initPerfectScrollbar();
                });
                return;
            }

            // Cancel previous request if in-flight
            if (this.ajaxCache.inflight) {
                this.ajaxCache.inflight.abort();
            }

            this.isLoading = true;
            this.ajaxCache.lastQuery = query;

            const controller = new AbortController();
            this.ajaxCache.inflight = controller;

            try {
                const url = this.baseUrl + 'contact/search?q=' + encodeURIComponent(query);
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                this.ajaxCache.lastResponse = data;
                this.results = data;
                this.showResults = true;
                this.selectedItem = null;
                this.updateBackdrop(true);
                this.$nextTick(() => {
                    this.forceDisplayUpdate();
                    this.initPerfectScrollbar();
                });
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('[Search] AJAX Error!', error);
                    this.results = {};
                    this.showResults = false;
                    this.updateBackdrop(false);
                }
            } finally {
                this.isLoading = false;
                if (this.ajaxCache.inflight === controller) {
                    this.ajaxCache.inflight = null;
                }
            }
        },

        updateBackdrop(show) {
            this.$nextTick(() => {
                const backdrop = document.querySelector('.content-backdrop');
                if (backdrop) {
                    if (show && this.hasResults) {
                        backdrop.classList.add('show');
                        backdrop.classList.remove('fade');
                    } else {
                        backdrop.classList.add('fade');
                        backdrop.classList.remove('show');
                    }
                }
            });
        },

        initPerfectScrollbar() {
            if (typeof PerfectScrollbar === 'undefined') return;

            const container = this.$refs.resultsContainer;
            if (!container) return;

            // Destroy existing instance
            if (this.psInstance) {
                try {
                    this.psInstance.destroy();
                } catch (e) {}
                this.psInstance = null;
            }

            // Create new instance
            try {
                this.psInstance = new PerfectScrollbar(container, {
                    wheelPropagation: false,
                    suppressScrollX: true
                });
            } catch (e) {
                console.error('[Search] PerfectScrollbar initialization error:', e);
            }
        },

        // Force display update after Alpine renders
        forceDisplayUpdate() {
            const container = this.$refs.resultsContainer;
            if (container && this.showResults && this.hasResults) {
                // Force display block to override Typeahead CSS
                container.style.setProperty('display', 'block', 'important');
            }
        },

        getAllItemsFlat() {
            const items = [];
            if (this.results.members) {
                this.results.members.forEach((item, i) => {
                    items.push({ ...item, category: 'members', index: i });
                });
            }
            if (this.results.enterprises) {
                this.results.enterprises.forEach((item, i) => {
                    items.push({ ...item, category: 'enterprises', index: i });
                });
            }
            if (this.results.services) {
                this.results.services.forEach((item, i) => {
                    items.push({ ...item, category: 'services', index: i });
                });
            }
            if (this.results.projects) {
                this.results.projects.forEach((item, i) => {
                    items.push({ ...item, category: 'projects', index: i });
                });
            }
            if (this.results.invoices) {
                this.results.invoices.forEach((item, i) => {
                    items.push({ ...item, category: 'invoices', index: i });
                });
            }
            return items;
        },

        navigateDown() {
            if (!this.showResults || !this.hasResults) return;
            const items = this.getAllItemsFlat();
            if (items.length === 0) return;

            const currentIndex = this.selectedItem
                ? items.findIndex(item => item.category === this.selectedItem.category && item.index === this.selectedItem.index)
                : -1;

            const nextIndex = (currentIndex + 1) % items.length;
            this.selectedItem = {
                category: items[nextIndex].category,
                index: items[nextIndex].index
            };
            this.scrollToSelected();
        },

        navigateUp() {
            if (!this.showResults || !this.hasResults) return;
            const items = this.getAllItemsFlat();
            if (items.length === 0) return;

            const currentIndex = this.selectedItem
                ? items.findIndex(item => item.category === this.selectedItem.category && item.index === this.selectedItem.index)
                : -1;

            const prevIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
            this.selectedItem = {
                category: items[prevIndex].category,
                index: items[prevIndex].index
            };
            this.scrollToSelected();
        },

        scrollToSelected() {
            this.$nextTick(() => {
                if (!this.selectedItem) return;
                const container = this.$refs.resultsContainer;
                if (!container) return;

                const active = container.querySelector('.suggestion.active');
                if (active) {
                    active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    if (this.psInstance) {
                        try {
                            this.psInstance.update();
                        } catch (e) {}
                    }
                }
            });
        },

        selectCurrent() {
            if (!this.showResults || !this.hasResults || !this.selectedItem) return;

            const items = this.getAllItemsFlat();
            const selected = items.find(item =>
                item.category === this.selectedItem.category &&
                item.index === this.selectedItem.index
            );

            if (selected && selected.url && selected.url !== 'javascript:;' && selected.url !== '#') {
                this.navigateTo(selected.url);
            }
        },

        navigateTo(url) {
            if (url && url !== 'javascript:;' && url !== '#') {
                window.location.href = url;
            }
        }
    };
}
</script>
