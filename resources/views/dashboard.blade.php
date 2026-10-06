@extends('layouts/layoutMaster')

@section('title', __('app.dashboard'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/swiper/swiper.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}" />
@endsection

@section('page-style')
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/cards-advance.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/swiper/swiper.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>

    @if(!empty($analyticsChartData) && !empty($analyticsChartData['dates']))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const analyticsChartEl = document.querySelector('#analyticsChart');
            if (analyticsChartEl) {
                const chartData = @json($analyticsChartData);
                const analyticsTheme = typeof config !== 'undefined' ? config : {};
                const analyticsIsDark = typeof isDarkStyle !== 'undefined' && isDarkStyle;
                const analyticsLabelColor = analyticsIsDark
                    ? (analyticsTheme.colors_dark?.headingColor || analyticsTheme.colors_dark?.textMuted || '#cfd3ec')
                    : (analyticsTheme.colors?.headingColor || analyticsTheme.colors?.textMuted || '#6f6b7d');
                const analyticsMutedColor = analyticsIsDark
                    ? (analyticsTheme.colors_dark?.textMuted || '#a1acb8')
                    : (analyticsTheme.colors?.textMuted || '#a5a3ae');
                const analyticsBorderColor = analyticsIsDark
                    ? (analyticsTheme.colors_dark?.borderColor || '#444564')
                    : (analyticsTheme.colors?.borderColor || '#e7e7e7');

                new ApexCharts(analyticsChartEl, {
                    chart: {
                        type: 'line',
                        height: 220,
                        fontFamily: 'Public Sans',
                        foreColor: analyticsLabelColor,
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        parentHeightOffset: 0,
                        offsetX: 0,
                    },
                    stroke: { curve: 'smooth', width: 2 },
                    series: [
                        { name: @json(__('Visitantes')), data: chartData.visitors },
                        { name: @json(__('Páginas vistas')), data: chartData.pageViews }
                    ],
                    xaxis: {
                        categories: chartData.dates,
                        labels: {
                            style: { colors: analyticsMutedColor },
                            formatter: function(val) {
                                return val ? new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : val;
                            }
                        },
                        axisBorder: { show: false, color: analyticsBorderColor },
                        axisTicks: { show: false, color: analyticsBorderColor },
                    },
                    yaxis: {
                        labels: {
                            style: { colors: analyticsMutedColor },
                            formatter: function(val) { return val ? parseInt(val, 10) : val; }
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left',
                        offsetX: 0,
                        labels: { colors: analyticsLabelColor },
                    },
                    colors: ['#696cff', '#71dd37'],
                    dataLabels: { enabled: false },
                    grid: {
                        borderColor: analyticsBorderColor,
                        strokeDashArray: 4,
                        padding: { right: 16, left: 4, top: 4 },
                    },
                    tooltip: {
                        theme: analyticsIsDark ? 'dark' : 'light',
                    },
                }).render();
            }
        });
    </script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const panelCard = document.getElementById('dashboardContactPanelCard');
            if (!panelCard) {
                return;
            }

            const panelMeta = {
                'contacts-trend': {
                    title: @json(__('app.dashboard_panel_contacts_trend_title')),
                    subtitle: @json(__('app.dashboard_contacts_chart_subtitle_30')),
                    icon: 'ti-target'
                },
                'status-breakdown': {
                    title: @json(__('app.dashboard_panel_status_title')),
                    subtitle: @json(__('app.dashboard_contacts_status_chart_subtitle')),
                    icon: 'ti-users'
                },
                'latest-contacts': {
                    title: @json(__('app.dashboard_panel_latest_contacts_title')),
                    subtitle: @json(__('app.dashboard_latest_contacts_subtitle')),
                    icon: 'ti-user-plus'
                },
                'interactions-breakdown': {
                    title: @json(__('app.dashboard_panel_interactions_title')),
                    subtitle: @json(__('app.dashboard_interactions_chart_subtitle')),
                    icon: 'ti-history'
                }
            };

            const trendData = @json($dashboardContactsCreatedTrend ?? ['labels' => [], 'values' => []]);
            const statusData = @json($dashboardContactStatusBreakdown ?? ['labels' => [], 'values' => []]);
            const interactionsTrendData = @json($dashboardContactInteractionsTrend ?? ['labels' => [], 'series' => [], 'total' => 0]);
            const interactionTypeColors = ['#696cff', '#71dd37', '#03c3ec', '#ffab00', '#ff3e1d', '#8592a3', '#233446', '#e83e8c'];
            const panelMonthComparisons = @json($dashboardPanelMonthComparisons ?? []);
            const vsLastMonthLabel = @json(__('vs last month'));
            const chartsEnabled = typeof ApexCharts !== 'undefined';
            const themeConfig = typeof config !== 'undefined' ? config : {};
            const isDark = typeof isDarkStyle !== 'undefined' && isDarkStyle;
            const muted = isDark
                ? (themeConfig.colors_dark && themeConfig.colors_dark.textMuted)
                : (themeConfig.colors && themeConfig.colors.textMuted);
            const primaryLabel = themeConfig.colors_label ? themeConfig.colors_label.primary : '#8592a1';
            const primary = themeConfig.colors ? themeConfig.colors.primary : '#696cff';
            const chartInstances = {};
            let latestContactsDt = null;

            function getBarColors(labels) {
                return labels.map(function(_, i) {
                    return i === labels.length - 1 ? primary : primaryLabel;
                });
            }

            function renderContactsTrendChart() {
                if (!chartsEnabled || chartInstances['contacts-trend']) {
                    return;
                }
                const el = document.querySelector('#dashboardContactsTrendChart');
                if (!el) {
                    return;
                }
                const labels = trendData.labels || [];
                const values = trendData.values || [];
                chartInstances['contacts-trend'] = new ApexCharts(el, {
                    chart: {
                        height: 200,
                        parentHeightOffset: 0,
                        type: 'bar',
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            columnWidth: '55%',
                            startingShape: 'rounded',
                            endingShape: 'rounded',
                            borderRadius: 4,
                            distributed: true
                        }
                    },
                    grid: {
                        show: true,
                        borderColor: '#e7e7e7',
                        strokeDashArray: 4,
                        padding: { top: 0, bottom: 0, left: 8, right: 8 }
                    },
                    colors: getBarColors(labels),
                    dataLabels: { enabled: false },
                    series: [{ name: @json(__('app.dashboard_contacts_chart_series')), data: values }],
                    legend: { show: false },
                    xaxis: {
                        categories: labels,
                        tickAmount: 6,
                        labels: {
                            rotate: -45,
                            rotateAlways: false,
                            style: {
                                colors: muted,
                                fontSize: '10px',
                                fontFamily: 'Public Sans'
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: muted, fontSize: '11px' }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return parseInt(val, 10);
                            }
                        }
                    }
                });
                chartInstances['contacts-trend'].render();
            }

            function renderStatusChart() {
                if (!chartsEnabled || chartInstances['status-breakdown']) {
                    return;
                }
                const el = document.querySelector('#dashboardContactStatusChart');
                if (!el) {
                    return;
                }
                const labels = statusData.labels || [];
                const values = statusData.values || [];
                chartInstances['status-breakdown'] = new ApexCharts(el, {
                    chart: {
                        height: 220,
                        type: 'donut',
                        toolbar: { show: false },
                        offsetX: -43,
                    },
                    labels: labels,
                    series: values,
                    colors: ['#71dd37', '#ffab00', '#03c3ec', '#ff3e1d', '#696cff'],
                    legend: {
                        position: 'right',
                        horizontalAlign: 'left',
                        verticalAlign: 'middle',
                        fontSize: '12px',
                        labels: { colors: muted },
                        itemMargin: { vertical: 6, horizontal: 8 },
                        offsetX: -29,
                        formatter: function(seriesName, opts) {
                            const count = opts.w.globals.series[opts.seriesIndex] ?? 0;
                            return seriesName + ' (' + count + ')';
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) {
                            return Math.round(val) + '%';
                        },
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '65%',
                                labels: {
                                    show: true,
                                    name: {
                                        color: muted,
                                        fontSize: '18px',
                                        fontWeight: 500,
                                    },
                                    value: {
                                        color: muted,
                                        fontSize: '22px',
                                        fontWeight: 500,
                                    },
                                    total: {
                                        show: true,
                                        showAlways: true,
                                        label: @json(__('app.dashboard_chart_total')),
                                        color: muted,
                                        fontSize: '12px',
                                        fontWeight: 500,
                                        formatter: function(w) {
                                            return w.globals.seriesTotals.reduce(function(a, b) {
                                                return a + b;
                                            }, 0);
                                        },
                                    },
                                },
                            },
                        },
                    },
                });
                chartInstances['status-breakdown'].render();
            }

            function renderInteractionsChart() {
                if (!chartsEnabled || chartInstances['interactions-breakdown']) {
                    return;
                }
                const el = document.querySelector('#dashboardContactInteractionsTrendChart');
                if (!el) {
                    return;
                }
                const series = interactionsTrendData.series || [];
                if (!series.length) {
                    el.innerHTML = '<p class="text-muted small mb-0">' + @json(__('app.contact_interactions_chart_empty')) + '</p>';
                    return;
                }
                chartInstances['interactions-breakdown'] = new ApexCharts(el, {
                    chart: {
                        height: 200,
                        parentHeightOffset: 0,
                        type: 'bar',
                        stacked: true,
                        toolbar: { show: false },
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            borderRadius: 4,
                        },
                    },
                    grid: {
                        show: true,
                        borderColor: '#e7e7e7',
                        strokeDashArray: 4,
                        padding: { top: 0, bottom: 0, left: 8, right: 8 },
                    },
                    colors: interactionTypeColors,
                    dataLabels: { enabled: false },
                    series: series,
                    legend: {
                        position: 'bottom',
                        horizontalAlign: 'left',
                        fontSize: '12px',
                        labels: { colors: muted },
                        itemMargin: { vertical: 4, horizontal: 8 },
                    },
                    xaxis: {
                        categories: interactionsTrendData.labels || [],
                        tickAmount: 6,
                        labels: {
                            rotate: -45,
                            rotateAlways: false,
                            style: {
                                colors: muted,
                                fontSize: '10px',
                                fontFamily: 'Public Sans',
                            },
                        },
                    },
                    yaxis: {
                        labels: {
                            style: { colors: muted, fontSize: '11px' },
                            formatter: function(val) {
                                return parseInt(val, 10);
                            },
                        },
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                        y: {
                            formatter: function(val) {
                                return parseInt(val, 10);
                            },
                        },
                    },
                });
                chartInstances['interactions-breakdown'].render();
            }

            function initLatestContactsTable() {
                const tableEl = document.getElementById('dashboardLatestContactsTable');
                if (!tableEl || latestContactsDt || typeof $ === 'undefined' || !$.fn || !$.fn.DataTable) {
                    return;
                }
                latestContactsDt = $('#dashboardLatestContactsTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/{{ app()->getLocale() === 'es' ? 'es-ES' : (app()->getLocale() === 'pt' ? 'pt-PT' : 'en') }}.json',
                        emptyTable: @json(__('app.dashboard_latest_contacts_empty')),
                    },
                    pageLength: 5,
                    lengthChange: false,
                    searching: false,
                    ordering: true,
                    order: [[2, 'desc']],
                    dom: 'rt',
                    responsive: true,
                    autoWidth: false,
                    columnDefs: [
                        { targets: 2, className: 'text-end text-nowrap' },
                    ],
                });

                const pagerEl = document.getElementById('dashboardLatestContactsPager');
                const prevBtn = document.getElementById('dashboardLatestContactsPrev');
                const nextBtn = document.getElementById('dashboardLatestContactsNext');

                function updateLatestContactsPager() {
                    if (!latestContactsDt || !pagerEl || !prevBtn || !nextBtn) {
                        return;
                    }
                    const pageInfo = latestContactsDt.page.info();
                    prevBtn.disabled = pageInfo.page <= 0;
                    nextBtn.disabled = pageInfo.page >= pageInfo.pages - 1;
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        if (!latestContactsDt) {
                            return;
                        }
                        latestContactsDt.page('previous').draw('page');
                        updateLatestContactsPager();
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        if (!latestContactsDt) {
                            return;
                        }
                        latestContactsDt.page('next').draw('page');
                        updateLatestContactsPager();
                    });
                }

                latestContactsDt.on('draw', updateLatestContactsPager);
                updateLatestContactsPager();
            }

            function toggleLatestContactsPager(panelKey) {
                const pagerEl = document.getElementById('dashboardLatestContactsPager');
                if (!pagerEl) {
                    return;
                }
                const showPager = panelKey === 'latest-contacts';
                pagerEl.classList.toggle('d-none', !showPager);
                pagerEl.classList.toggle('d-flex', showPager);
                pagerEl.setAttribute('aria-hidden', showPager ? 'false' : 'true');
            }

            function updatePanelMonthComparison(panelKey) {
                const comparisonEl = document.getElementById('dashboardContactPanelMonthChange');
                if (!comparisonEl) {
                    return;
                }

                const data = panelMonthComparisons[panelKey];
                if (!data || (data.current === 0 && data.previous === 0)) {
                    comparisonEl.classList.add('d-none');
                    comparisonEl.textContent = '';

                    return;
                }

                const difference = data.difference ?? 0;
                const percentChange = data.percent_change ?? 0;
                const diffSign = difference > 0 ? '+' : '';
                const percentSign = percentChange > 0 ? '+' : '';
                const colorClass = difference > 0
                    ? 'text-success'
                    : (difference < 0 ? 'text-danger' : 'text-muted');
                const iconClass = difference > 0
                    ? 'ti-trending-up'
                    : (difference < 0 ? 'ti-trending-down' : 'ti-minus');

                comparisonEl.classList.remove('d-none');
                comparisonEl.innerHTML = '<span class="' + colorClass + ' fw-medium">'
                    + '<i class="ti ' + iconClass + ' ti-xs me-1"></i>'
                    + diffSign + difference + ' (' + percentSign + percentChange + '%)</span>'
                    + '<span class="text-muted ms-1">' + vsLastMonthLabel + '</span>';
            }

            function showPanel(panelKey) {
                if (!panelKey || !panelMeta[panelKey]) {
                    return;
                }
                toggleLatestContactsPager(panelKey);
                document.querySelectorAll('.dashboard-contact-panel').forEach(function(panel) {
                    panel.classList.toggle('d-none', panel.getAttribute('data-panel') !== panelKey);
                });
                document.querySelectorAll('[data-dashboard-panel]').forEach(function(trigger) {
                    const isActive = trigger.getAttribute('data-dashboard-panel') === panelKey;
                    trigger.classList.toggle('dashboard-metric-item--active', isActive);
                    trigger.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                const meta = panelMeta[panelKey];
                if (meta) {
                    const titleEl = document.getElementById('dashboardContactPanelTitleText');
                    const subtitleEl = document.getElementById('dashboardContactPanelSubtitle');
                    const iconEl = document.getElementById('dashboardContactPanelIcon');
                    if (titleEl) {
                        titleEl.textContent = meta.title;
                    }
                    if (subtitleEl) {
                        subtitleEl.textContent = meta.subtitle;
                    }
                    if (iconEl) {
                        iconEl.className = 'ti ' + meta.icon + ' ti-xs me-1';
                    }
                }
                updatePanelMonthComparison(panelKey);
                if (panelKey === 'contacts-trend') {
                    renderContactsTrendChart();
                } else if (panelKey === 'status-breakdown') {
                    renderStatusChart();
                } else if (panelKey === 'interactions-breakdown') {
                    renderInteractionsChart();
                } else if (panelKey === 'latest-contacts') {
                    initLatestContactsTable();
                    if (latestContactsDt) {
                        setTimeout(function() {
                            latestContactsDt.columns.adjust().draw(false);
                        }, 50);
                    }
                }
            }

            const leftPanel = document.querySelector('.dashboard-left-panel');
            if (leftPanel) {
                leftPanel.addEventListener('click', function(event) {
                    const trigger = event.target.closest('[data-dashboard-panel]');
                    if (!trigger) {
                        return;
                    }
                    event.preventDefault();
                    showPanel(trigger.getAttribute('data-dashboard-panel'));
                });
            }

            showPanel('interactions-breakdown');
        });
    </script>
@endsection

@section('content')

    @include('partials.business-configuration-prompt', [
        'team' => $activeTeam ?? null,
        'dashboardTopRow' => true,
    ])

    <!-- Hour chart  -->
    <div class="card bg-transparent shadow-none mt-4 mb-0 border-0">
        <div class="card-body row p-0 pb-2 align-items-stretch dashboard-top-row">
            <div class="col-12 col-md-8 mb-4 mb-md-4 mb-lg-3 mb-sm-2 d-flex flex-column min-h-0">
                <div class="dashboard-left-panel d-flex flex-column flex-grow-1 min-h-0 w-100">
                    <div class="dashboard-metrics-primary flex-shrink-0">
                        <div class="row g-3 g-lg-4">
                            <div class="col-12 col-sm-6 col-xl-3">
                                <button type="button" class="d-flex align-items-center gap-3 dashboard-metric-item dashboard-metric-item--active border-0 bg-transparent text-body w-100 text-start p-0" data-dashboard-panel="interactions-breakdown" aria-pressed="true">
                                    <span class="bg-label-warning p-2 rounded d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-history ti-xl"></i>
                                    </span>
                                    <div class="content-right min-w-0">
                                        <p class="mb-0">{{ __('app.dashboard_metric_logged_interactions') }}</p>
                                        <h4 class="text-warning mb-0">{{ $teamInteractionsLast30DaysCount ?? 0 }}</h4>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <button type="button" class="d-flex align-items-center gap-3 dashboard-metric-item border-0 bg-transparent text-body w-100 text-start p-0" data-dashboard-panel="contacts-trend" aria-pressed="false">
                                    <span class="bg-label-success p-2 rounded d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-target ti-xl"></i>
                                    </span>
                                    <div class="content-right min-w-0">
                                        <p class="mb-0">{{ __('app.dashboard_metric_new_leads') }}</p>
                                        <h4 class="text-success mb-0">{{ $recentLeadsCount }}</h4>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <button type="button" class="d-flex align-items-center gap-3 dashboard-metric-item border-0 bg-transparent text-body w-100 text-start p-0" data-dashboard-panel="latest-contacts" aria-pressed="false">
                                    <span class="bg-label-info p-2 rounded d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-user-plus ti-xl"></i>
                                    </span>
                                    <div class="content-right min-w-0">
                                        <p class="mb-0">{{ __('app.dashboard_metric_recent_activity') }}</p>
                                        <h4 class="text-info mb-0">{{ $latestContactsThisMonthCount ?? 0 }}</h4>
                                    </div>
                                </button>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <button type="button" class="d-flex align-items-center gap-3 dashboard-metric-item border-0 bg-transparent text-body w-100 text-start p-0" data-dashboard-panel="status-breakdown" aria-pressed="false">
                                    <span class="bg-label-primary p-2 rounded d-inline-flex align-items-center justify-content-center">
                                        <i class="ti ti-users ti-xl"></i>
                                    </span>
                                    <div class="content-right min-w-0">
                                        <p class="mb-0">{{ __('app.dashboard_contacts_row_total') }}</p>
                                        <h4 class="text-primary mb-0">{{ $totalContactsCount ?? 0 }}</h4>
                                    </div>
                                </button>
                            </div>
                        </div>

                    </div>

                    <div class="dashboard-recent-activity-slot flex-grow-1 min-h-0 mt-3 d-flex flex-column">
                        @include('partials.dashboard-recent-activities', [
                            'latestRegisteredContacts' => $latestRegisteredContacts ?? collect(),
                            'fillHeight' => true,
                        ])
                    </div>
                </div>
            </div>

            <!-- View sales -->
            <div class="col-12 col-md-4 mb-4 mb-md-4 mb-lg-3 mb-sm-2 d-flex flex-column">
                <div class="card w-100 h-100 d-flex flex-column dashboard-insight-card position-relative">
                    <div class="card-body d-flex flex-column flex-grow-1 dashboard-insight-card-body">
                                @php
                                    $insightCardFirstName = explode(' ', (string) auth()->user()->name, 2)[0] ?? '';
                                @endphp
                                @if($canShowPerformanceInsight ?? false)
                                    <h5 class="card-title mb-1 fw-semibold">
                                        @if($dailyPerformanceInsight ?? null)
                                            <x-notification-subject :subject="$dailyPerformanceInsight->headline" />
                                        @else
                                            {{ e(__('app.dashboard_performance_focus_title')) }}
                                        @endif
                                    </h5>
                                    @if(($dailyPerformanceInsight ?? null) && filled($dailyPerformanceInsight->focus))
                                        <p class="mb-2 text-muted small">{!! nl2br(e($dailyPerformanceInsight->focus)) !!}</p>
                                    @else
                                        <p class="mb-2 text-muted small">{{ e(__('app.dashboard_performance_focus_subtitle')) }}</p>
                                    @endif
                                    @if(!empty($performanceInsightActions))
                                        <ul class="list-unstyled mb-2 small">
                                            @foreach($performanceInsightActions as $action)
                                                <li class="mb-1 text-body">
                                                    <i class="ti ti-checkbox ti-xs me-1 text-primary"></i>{{ $action['label'] }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mb-2 text-body small">{{ e(__('app.dashboard_performance_focus_empty')) }}</p>
                                    @endif
                                @else
                                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                        <span class="badge bg-label-primary p-1 rounded d-inline-flex align-items-center justify-content-center">
                                            <i class="ti ti-calendar-week ti-sm"></i>
                                        </span>
                                        <h5 class="card-title mb-0 fw-semibold">{{ $weeklyWorkPlan['title'] ?? __('app.weekly_plan_title') }}</h5>
                                    </div>
                                    @if(filled($weeklyWorkPlan['challenge'] ?? null))
                                        <p class="mb-2 text-muted small">{{ __('app.weekly_plan_challenge', ['challenge' => $weeklyWorkPlan['challenge']]) }}</p>
                                    @endif
                                    @if(!empty($weeklyWorkPlan['items']))
                                        <ul class="list-unstyled mb-2 small">
                                            @foreach($weeklyWorkPlan['items'] as $item)
                                                <li class="mb-1 text-body">
                                                    <i class="ti {{ !empty($item['done']) ? 'ti-circle-check text-success' : 'ti-checkbox text-primary' }} ti-xs me-1"></i>
                                                    @if(!empty($item['href']))
                                                        <a href="{{ $item['href'] }}" class="text-body">{{ $item['label'] }}</a>
                                                    @else
                                                        {{ $item['label'] }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mb-2 text-body small">{{ __('app.weekly_plan_empty') }}</p>
                                    @endif
                                @endif

                                {{-- <h4 class="text-primary mb-1">{{ number_format($currentMonthRevenue, 2, ',', '.') }}€</h4>
                                <p class="text-muted mb-2">
                                    Mes pasado: {{ number_format($lastMonthRevenue, 2, ',', '.') }}€
                                </p> --}}
                                <div class="dashboard-insight-actions mt-auto pt-2">
                                    <a href="{{ route('weekly-plan.index') }}" class="btn btn-sm btn-primary waves-effect waves-light">
                                        <i class="ti ti-report me-1"></i>{{ __('app.weekly_plan_report') }}
                                    </a>
                                    <a href="{{ route('organization.index') }}" class="btn btn-sm btn-primary waves-effect waves-light">
                                        <i class="ti ti-sitemap me-1"></i>{{ __('Organización') }}
                                    </a>
                                </div>
                    </div>
                    <div class="dashboard-insight-illustration" aria-hidden="true">
                        <img src="{{ asset('assets/img/illustrations/card-advance-sale.png') }}" height="140"
                            class="d-block" alt="" role="presentation">
                    </div>
                </div>
            </div>
            <!-- View sales -->
        </div>
    </div>
    <!-- Hour chart End  -->

    {{-- Placeholder social stat cards: hidden until real team stats are wired. Set to @if(true) to show. --}}
    @if(false)
        <div class="row mb-4">
            <div class="col-12">
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #e8b5e6;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">10,77k <i class="ti ti-arrow-up-right text-success"></i></h2>
                                <span class="fs-5 text-body">Instagram</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #8e97ea;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">8.445 <i class="ti ti-arrow-down-right text-danger"></i></h2>
                                <span class="fs-5 text-body">Facebook</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #99a8b1;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">1.511 <i class="ti ti-arrow-up-right text-success"></i></h2>
                                <span class="fs-5 text-body">TikTok</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #f58462;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">1.070</h2>
                                <span class="fs-5 text-body">YouTube</span>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card h-100 border-0" style="background-color: #5f6bdc;">
                            <div class="card-body py-3 text-center">
                                <h2 class="mb-1">31</h2>
                                <span class="fs-5 text-body">Bluesky</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row align-items-md-stretch dashboard-paired-row">
        <!-- Emotional Balance (right column) -->
        <div class="col-md-4 order-md-2 mb-4 mb-md-0 d-flex flex-column">
            <!-- Emotional Balance -->
            <div class="card mb-4 dashboard-sentiment-card flex-grow-1 d-flex flex-column w-100">
                <div class="card-header pb-0 d-flex justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Balance emocional</h5>
                        <small class="text-muted">¡Bravo! Estás en el buen camino</small>
                    </div>
                </div>
                <div class="card-body flex-grow-1 d-flex flex-column">
                    <div class="row flex-grow-1">
                        <div class="col-12 d-flex flex-column flex-grow-1">
                            <div class="sentiment-chart flex-grow-1 d-flex flex-column justify-content-end">
                                @php
                                    $sentimentMaxCount = max(1, (int) max(array_column($sentimentData, 'count')));
                                    $sentimentBarMaxHeight = 88;
                                @endphp
                                <div class="d-flex align-items-end justify-content-between sentiment-bars-row">
                                    @foreach ($sentimentData as $index => $sentiment)
                                        @php
                                            $sentimentBarHeight = max(32, (int) round(($sentiment['count'] / $sentimentMaxCount) * $sentimentBarMaxHeight));
                                        @endphp
                                        <div class="sentiment-column text-center">
                                            <div class="sentiment-bar" style="height: {{ $sentimentBarHeight }}px">
                                                <span class="sentiment-count">{{ $sentiment['count'] }}</span>
                                            </div>
                                            <div class="sentiment-emoji">
                                                @switch($index)
                                                    @case(0)
                                                        😡
                                                    @break

                                                    @case(1)
                                                        🙁
                                                    @break

                                                    @case(2)
                                                        😐
                                                    @break

                                                    @case(3)
                                                        🙂
                                                    @break

                                                    @case(4)
                                                        🥳
                                                    @break
                                                @endswitch
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Activity Feed section removed - Activity Log package was removed from the project --}}
        </div>

        <!-- Main Content Column -->
        <div class="col-md-8 order-md-1 d-flex flex-column">
            <!-- Today's contacts / calendar — paired with emotional balance -->
            <div class="card mb-4 dashboard-calendar-card w-100 d-flex flex-column">
                <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div class="card-title mb-0">
                        <h5 class="mb-1">{{ __('app.dashboard_calendar_card_title') }}</h5>
                        <small class="text-muted d-block">{{ __('app.dashboard_calendar_card_subtitle') }}</small>
                    </div>
                    @if ($dashboardCalendarData ?? null)
                        <div class="dashboard-calendar-header-actions d-flex align-items-center flex-wrap gap-2 flex-shrink-0">
                            @include('partials.dashboard-calendar-tab-nav')
                            <a href="{{ route('app-calendar') }}" id="dashboard-cal-link-calendar" class="btn btn-sm btn-label-primary">
                                <i class="ti ti-calendar me-1"></i>{{ __('app.dashboard_calendar_tab_calendar') }}
                            </a>
                        </div>
                    @endif
                </div>
                <div class="card-body dashboard-calendar-card-body d-flex flex-column">
                    @if ($dashboardCalendarData ?? null)
                        @include('partials.dashboard-calendar-tab-panes', $dashboardCalendarData)
                    @elseif(isset($todayContacts) && $todayContacts->count() > 0 && $todayContacts->first()->contact)
                        <div class="table-responsive flex-grow-1">
                            <table class="table table-borderless">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Sentimiento</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($todayContacts as $contact)
                                        @if($contact->contact)
                                            <tr>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <h6 class="mb-0">
                                                            <a href="{{ route('contact.show', $contact->contact->id) }}">{{ $contact->contact->name }}</a>
                                                        </h6>
                                                        @if($contact->contact->enterprise)
                                                            <small class="text-muted">{{ $contact->contact->enterprise->name }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge rounded-pill {{ $contact->status->label_class }}">
                                                        {{ $contact->status->name }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ $contact->contact->currentSentiment->sentiment->emoji ?? '' }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('contact.show', $contact->contact->id) }}" class="btn btn-sm btn-primary rounded-pill">
                                                        <i class="ti ti-phone-call me-1"></i>Contactar
                                                    </a>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="dashboard-today-contacts-empty flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center px-3">
                            <i class="ti ti-checkbox text-success ti-3x mb-3"></i>
                            <h5 class="mb-1">¡Todo al día!</h5>
                            <p class="text-muted mb-0">Has completado todas las tareas programadas para hoy</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(isset($activeTeam) && $activeTeam->hasModule('projects'))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                        <h5 class="m-0 me-2">{{ __('Ongoing Projects') }}</h5>
                        <small class="text-muted">{{ __('Current active projects') }}</small>
                    </div>
                    <div class="dropdown">
                        <a href="{{ route('project-list') }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-list ti-xs me-1"></i>{{ __('View All') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless border-top">
                            <thead>
                                <tr>
                                    <th>{{ __('Project') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th style="min-width: 140px;">{{ __('Hours') }}</th>
                                    <th class="text-center">{{ __('Tasks') }}</th>
                                    <th>{{ __('Completion') }}</th>
                                    <th class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ongoingProjects as $project)
                                    @php
                                        $workedHours = (float) ($project->dashboard_worked_hours ?? 0);
                                        $estimatedHours = (float) ($project->dashboard_estimated_hours ?? 0);
                                        $openTasks = (int) ($project->dashboard_open_tasks ?? 0);
                                        $totalTasks = (int) ($project->dashboard_total_tasks ?? 0);
                                        $hoursProgress = $estimatedHours > 0
                                            ? min(100, (int) round(($workedHours / $estimatedHours) * 100))
                                            : ($workedHours > 0 ? 100 : 0);
                                        $dueDate = $project->date_end;
                                        $dueOverdue = $dueDate !== null && $dueDate->lt(now()->startOfDay());
                                        $dueSoon = $dueDate !== null && ! $dueOverdue && $dueDate->lte(now()->copy()->addDays(7)->startOfDay());
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <h6 class="mb-0">
                                                    @can('view', $project)
                                                        <a href="{{ route('project.show', $project->id) }}" class="text-body text-decoration-none">{{ $project->name }}</a>
                                                    @else
                                                        {{ $project->name }}
                                                    @endcan
                                                </h6>
                                                <small class="text-muted">
                                                    @if ($project->client)
                                                        @can('view', $project->client)
                                                            <a href="{{ route('client.show', $project->client->id) }}" class="text-muted text-decoration-none">{{ $project->client->name }}</a>
                                                        @else
                                                            {{ $project->client->name }}
                                                        @endcan
                                                    @else
                                                        —
                                                    @endif
                                                </small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            {!! $project->status_label !!}
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span class="fw-semibold">{{ \App\Helpers\Helpers::formatHoursHuman($workedHours) }}</span>
                                                <span class="text-muted">
                                                    @if ($estimatedHours > 0)
                                                        / {{ \App\Helpers\Helpers::formatHoursHuman($estimatedHours) }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar {{ $estimatedHours > 0 && $hoursProgress >= 100 ? 'bg-warning' : 'bg-primary' }}"
                                                    role="progressbar"
                                                    style="width: {{ $hoursProgress }}%;"
                                                    aria-valuenow="{{ $hoursProgress }}"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <span class="fw-semibold">{{ $openTasks }}</span>
                                            <span class="text-muted">/ {{ $totalTasks }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($dueDate)
                                                <span class="{{ $dueOverdue ? 'text-danger' : ($dueSoon ? 'text-warning' : 'text-body') }}">
                                                    {{ $dueDate->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if((int) $project->status_id === \App\Models\ProjectStatus::STATUS_IN_PROGRESS)
                                                <a href="{{ route('task.index', ['view' => 'kanban', 'project_id' => $project->id]) }}" class="text-body" title="{{ __('View Kanban') }}">
                                                    <i class="ti ti-layout-kanban ti-sm"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('project.show', $project->id) }}" class="text-body" title="{{ __('View Details') }}">
                                                    <i class="ti ti-eye ti-sm"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="ti ti-mood-check text-success ti-3x mb-3"></i>
                                            <h5>{{ __('No ongoing projects') }}</h5>
                                            <p class="text-muted">{{ __('All projects are completed or not yet started') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @php
        $hasAnalyticsChart = ! empty($analyticsChartData) && ! empty($analyticsChartData['dates']);
        $hasUsageBillingAttentions = is_array($usageBillingAttentions ?? null);
    @endphp

    @if ($hasAnalyticsChart || $hasUsageBillingAttentions)
    <div class="row mb-4 dashboard-analytics-row g-4">
        @if ($hasAnalyticsChart)
        <div class="{{ $hasUsageBillingAttentions ? 'col-lg-7' : 'col-12' }}">
            <div class="card dashboard-analytics-card h-100">
                <div class="card-header pb-0">
                    <div class="card-title mb-0">
                        <h5 class="mb-0">Google Analytics</h5>
                        <small class="text-muted">{{ __('Visitors and page views (últimos 30 días)') }}</small>
                    </div>
                </div>
                <div class="card-body pt-2 overflow-hidden">
                    @php
                        $analyticsTotals = $analyticsChartData['totals'] ?? [];
                        $analyticsTopPages = $analyticsChartData['top_pages'] ?? [];
                        $analyticsTopCountries = $analyticsChartData['top_countries'] ?? [];
                        $analyticsNewUsers = (int) ($analyticsTotals['new_users'] ?? 0);
                        $analyticsReturningUsers = (int) ($analyticsTotals['returning_users'] ?? 0);
                        $analyticsAudience = $analyticsNewUsers + $analyticsReturningUsers;
                        $analyticsNewPct = $analyticsAudience > 0
                            ? (int) round(($analyticsNewUsers / $analyticsAudience) * 100)
                            : 0;
                    @endphp
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <div class="small text-muted">{{ __('Visitantes') }}</div>
                            <div class="fw-semibold">{{ number_format((int) ($analyticsTotals['visitors'] ?? 0), 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">{{ __('Páginas vistas') }}</div>
                            <div class="fw-semibold">{{ number_format((int) ($analyticsTotals['page_views'] ?? 0), 0, ',', '.') }}</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">{{ __('Usuarios nuevos') }}</div>
                            <div class="fw-semibold">
                                {{ number_format($analyticsNewUsers, 0, ',', '.') }}
                                @if ($analyticsAudience > 0)
                                    <span class="text-muted fw-normal small">({{ $analyticsNewPct }}%)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div id="analyticsChart" class="dashboard-analytics-chart"></div>
                    @if ($analyticsTopPages !== [] || $analyticsTopCountries !== [])
                        <div class="row g-3 mt-2 pt-2 border-top">
                            @if ($analyticsTopPages !== [])
                                <div class="col-md-8">
                                    <div class="small text-muted mb-2">{{ __('Páginas top') }}</div>
                                    <ul class="list-unstyled mb-0">
                                        @foreach ($analyticsTopPages as $page)
                                            <li class="d-flex justify-content-between gap-2 mb-1 small">
                                                <span class="text-truncate" title="{{ $page['title'] }}{{ $page['url'] !== '' ? ' — '.$page['url'] : '' }}">
                                                    {{ $page['title'] }}
                                                </span>
                                                <span class="text-muted text-nowrap">{{ number_format($page['views'], 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($analyticsTopCountries !== [])
                                <div class="col-md-4">
                                    <div class="small text-muted mb-2">{{ __('Países top') }}</div>
                                    <ul class="list-unstyled mb-0">
                                        @foreach ($analyticsTopCountries as $country)
                                            <li class="d-flex justify-content-between gap-2 mb-1 small">
                                                <span class="text-truncate">{{ $country['country'] }}</span>
                                                <span class="text-muted text-nowrap">{{ number_format($country['views'], 0, ',', '.') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if ($hasUsageBillingAttentions)
        <div class="{{ $hasAnalyticsChart ? 'col-lg-5' : 'col-12' }}">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="card-title mb-0">
                            <h5 class="mb-0">{{ __('Cobros de consumo') }}</h5>
                            <small class="text-muted">{{ __('Borradores de Stripe (todos los equipos)') }}</small>
                        </div>
                        <a href="{{ route('invoice.index', ['summary_filter' => 'draft']) }}" class="btn btn-sm btn-label-secondary text-nowrap">
                            <i class="ti ti-file-invoice ti-xs me-1"></i>{{ __('View drafts') }}
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="badge bg-label-warning">
                            {{ $usageBillingAttentions['draft_count'] }} {{ __('borrador') }}{{ $usageBillingAttentions['draft_count'] === 1 ? '' : 'es' }}
                        </span>
                        <span class="badge bg-label-danger">
                            {{ $usageBillingAttentions['open_count'] }} {{ __('sin cobrar') }}
                        </span>
                        @if (($usageBillingAttentions['overdue_count'] ?? 0) > 0)
                            <span class="badge bg-label-danger">
                                {{ $usageBillingAttentions['overdue_count'] }} {{ __('vencida') }}{{ $usageBillingAttentions['overdue_count'] === 1 ? '' : 's' }}
                            </span>
                        @endif
                        @if (($usageBillingAttentions['uncollectible_count'] ?? 0) > 0)
                            <span class="badge bg-label-dark">
                                {{ $usageBillingAttentions['uncollectible_count'] }} {{ __('incobrable') }}{{ $usageBillingAttentions['uncollectible_count'] === 1 ? '' : 's' }}
                            </span>
                        @endif
                        <span class="badge bg-label-secondary">
                            {{ number_format($usageBillingAttentions['total_cents'] / 100, 2, ',', '.') }}
                            {{ strtoupper($usageBillingAttentions['currency'] ?? 'EUR') }}
                        </span>
                    </div>
                </div>
                <div class="card-body pt-3">
                    @if (empty($usageBillingAttentions['items']))
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-circle-check ti-lg d-block mb-2 text-success"></i>
                            {{ __('No hay borradores de Stripe pendientes.') }}
                        </div>
                    @else
                        <div class="table-responsive" style="max-height: 360px;">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Equipo') }}</th>
                                        <th class="text-end">{{ __('Importe') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($usageBillingAttentions['items'] as $item)
                                        <tr>
                                            <td class="align-middle">
                                                <a href="{{ $item['account_url'] }}" class="text-body fw-medium d-block">{{ $item['team_name'] }}</a>
                                                <span class="small text-muted text-nowrap">{{ $item['period_from'] }} – {{ $item['period_to'] }}</span>
                                            </td>
                                            <td class="align-middle text-end text-nowrap">
                                                {{ number_format($item['billed_cents'] / 100, 2, ',', '.') }}
                                                {{ strtoupper($item['currency']) }}
                                            </td>
                                            <td class="align-middle text-end text-nowrap">
                                                @if (! empty($item['stripe_url']))
                                                    <a href="{{ $item['stripe_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-label-secondary" title="{{ __('Abrir en Stripe') }}">
                                                        <i class="ti ti-external-link ti-xs"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

@endsection

<style>
    @media (min-width: 768px) {
        .dashboard-top-row > .col-md-8,
        .dashboard-top-row > .col-md-4 {
            display: flex;
            flex-direction: column;
            overflow: visible;
        }

        .dashboard-left-panel {
            height: 100%;
        }

        .dashboard-metrics-primary .row,
        .dashboard-metrics-secondary .row {
            align-items: flex-start;
        }
    }

    .dashboard-metric-item {
        min-height: 0;
        cursor: pointer;
        border-radius: 0.375rem;
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }

    .dashboard-metric-item:hover,
    .dashboard-metric-item--active {
        background-color: rgba(105, 108, 255, 0.06);
    }

    .dashboard-metric-item--active {
        box-shadow: inset 0 0 0 1px rgba(105, 108, 255, 0.35);
    }

    #dashboardContactStatusChart {
        width: 100%;
        overflow: hidden;
        padding-right: 0.75rem;
    }

    #dashboardContactStatusChart .apexcharts-legend {
        padding: 0 0 0 12px;
    }

    #dashboardContactStatusChart .apexcharts-legend.apx-legend-position-right {
        top: 50% !important;
        right: 2.4rem !important;
        bottom: auto !important;
        left: auto !important;
        transform: translateY(-50%);
        max-height: none !important;
        flex-direction: column;
        align-items: flex-start !important;
    }

    #dashboardContactStatusChart .apexcharts-legend-series {
        margin: 4px 0 !important;
    }

    #dashboardContactStatusChart .apexcharts-datalabel-label {
        font-size: 18px !important;
        font-weight: 500 !important;
    }

    .contact-interactions-activity-chart {
        width: 100%;
        overflow: hidden;
    }

    .dashboard-latest-contacts-panel .dataTables_wrapper {
        min-height: 0;
    }

    .dashboard-latest-contacts-panel table.dataTable {
        margin-top: 0 !important;
        margin-bottom: 0.5rem !important;
    }

    .dashboard-latest-contacts-panel .dataTables_info {
        display: none;
    }

    #dashboardLatestContactsPager .btn {
        white-space: nowrap;
    }

    .dashboard-metrics-primary {
        flex: 0 0 auto;
    }

    .dashboard-top-row {
        overflow-x: clip;
    }

    .dashboard-insight-card {
        overflow: visible;
        container-type: inline-size;
    }

    .dashboard-insight-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    @container (max-width: 26rem) {
        .dashboard-insight-actions {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    .dashboard-top-row > .col-md-4:has(.dashboard-insight-card) {
        overflow: visible;
    }

    .dashboard-insight-card-body {
        position: relative;
        z-index: 1;
        padding-right: 5.5rem;
    }

    .dashboard-insight-illustration {
        position: absolute;
        right: 0;
        bottom: 0;
        line-height: 0;
        pointer-events: none;
        z-index: 0;
    }

    .dashboard-insight-illustration img {
        display: block;
        height: 140px;
        width: auto;
        max-width: none;
        transform: translateX(18%);
    }

    .dashboard-analytics-card .card-body {
        overflow: hidden;
    }

    .dashboard-analytics-chart {
        min-height: 280px;
        max-width: 100%;
    }

    .dashboard-analytics-chart .apexcharts-canvas,
    .dashboard-analytics-chart svg {
        max-width: 100% !important;
    }

    .dark-style .dashboard-analytics-chart .apexcharts-legend-text,
    .dark-style .dashboard-analytics-chart .apexcharts-xaxis text,
    .dark-style .dashboard-analytics-chart .apexcharts-yaxis text,
    .dark-style .dashboard-analytics-chart .apexcharts-text {
        fill: #cfd3ec !important;
        color: #cfd3ec !important;
    }

    @media (max-width: 767.98px) {
        .dashboard-paired-row > .col-md-4 {
            flex: 0 0 auto;
            width: min(100%, 22.5rem);
            max-width: 22.5rem;
        }
    }

    @media (min-width: 768px) {
        .dashboard-paired-row > [class*='col-md-'] > .card {
            min-height: 330px;
        }

        .dashboard-paired-row > [class*='col-md-'] > .dashboard-calendar-card {
            flex-grow: 0;
            height: auto;
            min-height: 0;
        }

        .dashboard-paired-row > [class*='col-md-'] > .dashboard-sentiment-card {
            height: 100%;
            min-height: 0;
        }
    }

    .dashboard-calendar-card .card-header {
        padding-bottom: 1.5rem;
    }

    .dashboard-calendar-card .dashboard-calendar-header-actions {
        margin-left: auto;
    }

    .dashboard-calendar-card .dashboard-calendar-card-body {
        padding: 0.5rem 1.25rem 0.75rem;
    }

    .dashboard-calendar-card .dashboard-calendar-tabs .btn-label-primary:not(.active) {
        opacity: 0.72;
    }

    .dashboard-calendar-card .tab-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding: 0;
        margin: 0;
    }

    .dashboard-calendar-card .tab-pane {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding: 0;
    }

    .dashboard-calendar-card .dashboard-calendar-tab-inner {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .dashboard-calendar-card .dashboard-calendar-events-table {
        margin-bottom: 0;
    }

    .dashboard-calendar-card .dashboard-calendar-events-table tbody td {
        padding-top: 0.35rem;
        padding-bottom: 0.35rem;
        vertical-align: middle;
    }

    .dashboard-calendar-card .dashboard-calendar-empty {
        flex-grow: 1;
        justify-content: center;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .sentiment-chart {
        padding: 0.5rem 0 0;
        min-height: 0;
        justify-content: flex-end;
    }

    .sentiment-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        align-self: flex-end;
        height: auto;
        padding: 0 5px;
    }

    .sentiment-bar {
        width: 100%;
        max-width: 60px;
        background-color: #696cff;
        border-radius: 8px;
        position: relative;
        min-height: 32px;
        transition: height 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sentiment-count {
        color: #fff;
        font-weight: 600;
        font-size: 0.8125rem;
        line-height: 1;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.35);
    }

    .sentiment-column:nth-child(2) .sentiment-count,
    .sentiment-column:nth-child(3) .sentiment-count,
    .sentiment-column:nth-child(4) .sentiment-count {
        color: #434343;
        text-shadow: none;
    }

    .sentiment-emoji {
        font-size: 1.5rem;
        margin-top: 0.25rem;
        margin-bottom: 0;
        flex-shrink: 0;
        line-height: 1;
    }

    .sentiment-column:nth-child(1) .sentiment-bar {
        background-color: #ff4d4f;
    }

    .sentiment-column:nth-child(2) .sentiment-bar {
        background-color: #ffa39e;
    }

    .sentiment-column:nth-child(3) .sentiment-bar {
        background-color: #ffd666;
    }

    .sentiment-column:nth-child(4) .sentiment-bar {
        background-color: #95de64;
    }

    .sentiment-column:nth-child(5) .sentiment-bar {
        background-color: #52c41a;
    }

    .sentiment-chart .sentiment-bars-row {
        flex: 0 0 auto;
        width: 100%;
        align-items: flex-end;
    }

    @media (max-width: 576px) {
        .sentiment-column {
            padding: 0 2px;
        }

        .sentiment-bar {
            max-width: 40px;
        }

        .sentiment-count {
            font-size: 0.75rem;
        }

        .sentiment-emoji {
            font-size: 1.2rem;
        }

        .sentiment-chart .sentiment-bars-row {
            width: 100%;
        }

        .dashboard-paired-row > [class*='col-md-'] > .card {
            min-height: 280px;
        }

        .dashboard-paired-row > [class*='col-md-'] > .dashboard-calendar-card,
        .dashboard-paired-row > [class*='col-md-'] > .dashboard-sentiment-card {
            min-height: 0;
        }
    }

    /* Activity Feed Styles */
    .activity-feed {
        max-height: 400px;
        overflow-y: auto;
    }

    .activity-item {
        transition: background-color 0.2s ease;
    }

    .activity-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        padding: 8px;
        margin: -8px;
        margin-bottom: 4px;
    }

    .activity-content p {
        line-height: 1.4;
        font-size: 0.875rem;
    }

    .activity-content small {
        font-size: 0.75rem;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
</style>
