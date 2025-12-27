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

    @if (!isset($menuHorizontal) && (($configData['showSearch'] ?? true) === true))
        <!-- Livewire Search -->
        <div class="navbar-nav align-items-center">
            <div class="nav-item navbar-search-wrapper mb-0">
                @livewire('global-search')
            </div>
        </div>
        <!-- /Livewire Search -->
    @endif
    <ul class="navbar-nav flex-row align-items-center ms-auto">
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
            <!-- Livewire Search -->
            <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
                @livewire('global-search')
            </li>
            <!-- /Livewire Search -->
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

                @if (Auth::check() && auth()->user()->currentTeam && auth()->user()->ownsTeam(auth()->user()->currentTeam))
                    {{-- Variables de configuración (Team Settings module) --}}
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
