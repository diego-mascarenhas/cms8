@php
    $chartId = $chartId ?? 'contactInteractionsActivityChart';
    $trendData = $trendData ?? ['labels' => [], 'series' => [], 'total' => 0];
    $chartHeight = $chartHeight ?? 200;
    $subtitle = $subtitle ?? __('app.contact_interactions_chart_subtitle', ['days' => 30]);
    $interactionChartColors = ['#696cff', '#71dd37', '#03c3ec', '#ffab00', '#ff3e1d', '#8592a3', '#233446', '#e83e8c'];
@endphp

@if (($trendData['total'] ?? 0) > 0)
    <div class="mb-4">
        @if (! empty($subtitle))
            <h6 class="text-muted mb-2">{{ $subtitle }}</h6>
        @endif
        <div id="{{ $chartId }}" class="contact-interactions-activity-chart" style="min-height: {{ (int) $chartHeight }}px;"></div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof ApexCharts === 'undefined') {
                    return;
                }
                const el = document.querySelector('#{{ $chartId }}');
                if (!el || el.dataset.chartRendered === '1') {
                    return;
                }
                const trendData = @json($trendData);
                const themeConfig = typeof config !== 'undefined' ? config : {};
                const isDark = typeof isDarkStyle !== 'undefined' && isDarkStyle;
                const muted = isDark
                    ? (themeConfig.colors_dark && themeConfig.colors_dark.textMuted)
                    : (themeConfig.colors && themeConfig.colors.textMuted);

                new ApexCharts(el, {
                    chart: {
                        height: {{ (int) $chartHeight }},
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
                    colors: @json($interactionChartColors),
                    dataLabels: { enabled: false },
                    series: trendData.series || [],
                    legend: {
                        position: 'bottom',
                        horizontalAlign: 'left',
                        fontSize: '12px',
                        labels: { colors: muted },
                        itemMargin: { vertical: 4, horizontal: 8 },
                    },
                    xaxis: {
                        categories: trendData.labels || [],
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
                            formatter: function (val) {
                                return parseInt(val, 10);
                            },
                        },
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                        y: {
                            formatter: function (val) {
                                return parseInt(val, 10);
                            },
                        },
                    },
                }).render();
                el.dataset.chartRendered = '1';
            });
        </script>
    @endpush
@else
    <p class="text-muted small mb-4">{{ __('app.contact_interactions_chart_empty') }}</p>
@endif
