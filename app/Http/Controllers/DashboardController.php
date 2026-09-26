<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\List60;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\SubscriptionProduct;
use App\Models\TeamUsageInvoice;
use App\Models\UserContactAction;
use App\Services\ContactDailySentimentService;
use App\Services\ContactInteractionChartDataService;
use App\Services\UserDailyPerformanceInsightService;
use App\Support\DemoTeam;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;

class DashboardController extends Controller
{
    private const AGGREGATES_CACHE_SECONDS = 600;

    private const ANALYTICS_CACHE_SECONDS = 3600;

    public function __construct(
        private readonly ContactDailySentimentService $contactDailySentimentService,
    ) {}

    public function index()
    {
        $activeTeam = auth()->user()->currentTeam ?? auth()->user()->teams->first();

        $currentMonthRevenue = 0;
        $lastMonthRevenue = 0;

        if (! $activeTeam)
        {
            return redirect()->back()->with('error', 'No team assigned');
        }

        $aggregates = $this->cachedTeamAggregates($activeTeam);

        $totalTeamMinutes = $aggregates['totalTeamMinutes'];
        $sentimentData = $aggregates['sentimentData'];
        $recentLeadsCount = $aggregates['recentLeadsCount'];
        $totalContactsCount = $aggregates['totalContactsCount'];
        $totalClientsCount = $aggregates['totalClientsCount'];
        $latestContactsThisMonthCount = $aggregates['latestContactsThisMonthCount'];
        $dashboardContactsCreatedTrend = $aggregates['dashboardContactsCreatedTrend'];
        $dashboardContactStatusBreakdown = $aggregates['dashboardContactStatusBreakdown'];
        $dashboardPanelMonthComparisons = $aggregates['dashboardPanelMonthComparisons'];
        $dashboardContactInteractionsTrend = $aggregates['dashboardContactInteractionsTrend'];
        $teamInteractionsLast30DaysCount = $aggregates['teamInteractionsLast30DaysCount'];
        $hasProjects = $aggregates['hasProjects'];

        $latestRegisteredContacts = Contact::query()
            ->where('team_id', $activeTeam->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->with('status:id,name,label_class')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'surname', 'status_id', 'created_at']);

        $clientsToContactToday = 0;
        $todayContacts = collect();
        if ($activeTeam->hasModule('list60'))
        {
            $todayContacts = List60::with(['contact.enterprises', 'contact.currentSentiment.sentiment'])
                ->whereHas('contact', function ($query) use ($activeTeam)
                {
                    $query->where('team_id', $activeTeam->id);
                })
                ->whereDate('date_next', Carbon::today())
                ->get();
            $clientsToContactToday = $todayContacts->count();
        }

        $ongoingProjects = null;
        if ($activeTeam->hasModule('projects'))
        {
            $ongoingProjects = Project::with(['client', 'responsible', 'status'])
                ->where('team_id', $activeTeam->id)
                ->whereIn('status_id', ProjectStatus::ongoingDashboardStatusIds())
                ->orderByRaw('CASE status_id WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 WHEN ? THEN 5 WHEN ? THEN 6 ELSE 7 END', [
                    ProjectStatus::STATUS_IN_PROGRESS,
                    ProjectStatus::STATUS_APPROVED,
                    ProjectStatus::STATUS_WAITING_FOR_RESPONSE,
                    ProjectStatus::STATUS_AUTHORIZED,
                    ProjectStatus::STATUS_BUDGETED,
                    ProjectStatus::STATUS_BUDGET,
                ])
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get();
        }

        $formattedActivities = collect();

        [
            'subscriptionLevel' => $subscriptionLevel,
            'mentoringPlan' => $mentoringPlan,
            'mentoringLevelName' => $mentoringLevelName,
            'mentoringMessage' => $mentoringMessage,
        ] = $this->resolveSubscriptionPresentation($activeTeam, $hasProjects);

        $analyticsChartData = $this->cachedAnalyticsChartData($activeTeam);

        $dailyPerformanceInsight = null;
        $canShowPerformanceInsight = auth()->user()->hasAnyRole(['admin', 'root'])
            && ($activeTeam->hasModule('performance_insights') || DemoTeam::isDemoTeam($activeTeam));

        if ($canShowPerformanceInsight)
        {
            $dailyPerformanceInsight = app(UserDailyPerformanceInsightService::class)
                ->findTodayInsight(auth()->user(), $activeTeam);
        }

        $dashboardCalendarData = $this->buildDashboardCalendarData($activeTeam);

        $usageBillingAttentions = $this->buildUsageBillingAttentions();

        return view('dashboard', compact(
            'activeTeam',
            'totalTeamMinutes',
            'clientsToContactToday',
            'sentimentData',
            'recentLeadsCount',
            'todayContacts',
            'currentMonthRevenue',
            'lastMonthRevenue',
            'ongoingProjects',
            'formattedActivities',
            'subscriptionLevel',
            'mentoringPlan',
            'mentoringLevelName',
            'mentoringMessage',
            'hasProjects',
            'analyticsChartData',
            'totalContactsCount',
            'totalClientsCount',
            'latestContactsThisMonthCount',
            'dashboardContactsCreatedTrend',
            'dashboardContactStatusBreakdown',
            'dashboardPanelMonthComparisons',
            'dashboardContactInteractionsTrend',
            'teamInteractionsLast30DaysCount',
            'latestRegisteredContacts',
            'dailyPerformanceInsight',
            'dashboardCalendarData',
            'usageBillingAttentions',
        ));
    }

    /**
     * @return array{
     *     totalTeamMinutes: int|float,
     *     sentimentData: list<array{label: string, count: int}>,
     *     recentLeadsCount: int,
     *     totalContactsCount: int,
     *     totalClientsCount: int,
     *     latestContactsThisMonthCount: int,
     *     dashboardContactsCreatedTrend: array{labels: list<string>, values: list<int>},
     *     dashboardContactStatusBreakdown: array{labels: list<string>, values: list<int>},
     *     dashboardPanelMonthComparisons: array<string, array{current: int, previous: int, difference: int, percent_change: float, direction: string}>,
     *     dashboardContactInteractionsTrend: array{labels: list<string>, series: list<array{name: string, data: list<int>}>, total: int},
     *     teamInteractionsLast30DaysCount: int,
     *     hasProjects: bool
     * }
     */
    private function cachedTeamAggregates($activeTeam): array
    {
        return Cache::remember(
            "dashboard.aggregates.{$activeTeam->id}",
            self::AGGREGATES_CACHE_SECONDS,
            fn () => $this->buildTeamAggregates($activeTeam),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTeamAggregates($activeTeam): array
    {
        $totalTeamSeconds = UserContactAction::whereHas('contact', function ($query) use ($activeTeam)
        {
            $query->where('team_id', $activeTeam->id);
        })
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->sum('duration_seconds');

        $currentMonthStart = now()->startOfMonth();
        $previousMonthStart = $currentMonthStart->copy()->subMonth();
        $nextMonthStart = $currentMonthStart->copy()->addMonth();

        $interactionChartService = app(ContactInteractionChartDataService::class);
        $dashboardContactInteractionsTrend = $interactionChartService->buildDailyTrendByType(
            $activeTeam->id,
        );

        $latestContactsThisMonthCount = Contact::query()
            ->where('team_id', $activeTeam->id)
            ->where('created_at', '>=', $currentMonthStart)
            ->count();

        $interactionsPreviousMonthCount = $interactionChartService->countForTeamBetween(
            $activeTeam->id,
            $previousMonthStart,
            $currentMonthStart,
        );
        $interactionsThisMonthCount = $interactionChartService->countForTeamBetween(
            $activeTeam->id,
            $currentMonthStart,
            $nextMonthStart,
        );
        $contactsCreatedPreviousMonthCount = $this->countTeamContactsCreatedBetween(
            $activeTeam->id,
            $previousMonthStart,
            $currentMonthStart,
        );
        $leadsCreatedThisMonthCount = $this->countTeamContactsCreatedBetween(
            $activeTeam->id,
            $currentMonthStart,
            $nextMonthStart,
            statusId: 1,
        );
        $leadsCreatedPreviousMonthCount = $this->countTeamContactsCreatedBetween(
            $activeTeam->id,
            $previousMonthStart,
            $currentMonthStart,
            statusId: 1,
        );

        $statusIdsForChart = [1, 2, 3, 4, 5];
        $statusCountsById = Contact::query()
            ->where('team_id', $activeTeam->id)
            ->whereIn('status_id', $statusIdsForChart)
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');
        $statusLabelsById = ContactStatus::query()
            ->whereIn('id', $statusIdsForChart)
            ->orderBy('id')
            ->pluck('name', 'id');

        $dashboardContactStatusBreakdown = [
            'labels' => [],
            'values' => [],
        ];
        foreach ($statusIdsForChart as $statusId)
        {
            $dashboardContactStatusBreakdown['labels'][] = $statusLabelsById[$statusId] ?? (string) $statusId;
            $dashboardContactStatusBreakdown['values'][] = (int) ($statusCountsById[$statusId] ?? 0);
        }

        return [
            'totalTeamMinutes' => max(0, round($totalTeamSeconds / 60)),
            'sentimentData' => $this->contactDailySentimentService->chartDataForTeam($activeTeam),
            'recentLeadsCount' => Contact::query()
                ->where('team_id', $activeTeam->id)
                ->where('status_id', 1)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'totalContactsCount' => Contact::query()
                ->where('team_id', $activeTeam->id)
                ->count(),
            'totalClientsCount' => $activeTeam->hasModule('clients')
                ? Enterprise::query()->where('team_id', $activeTeam->id)->count()
                : 0,
            'latestContactsThisMonthCount' => $latestContactsThisMonthCount,
            'dashboardContactsCreatedTrend' => $this->buildContactsCreatedTrend(
                $activeTeam->id,
                30,
                statusId: 1,
            ),
            'dashboardContactStatusBreakdown' => $dashboardContactStatusBreakdown,
            'dashboardPanelMonthComparisons' => [
                'contacts-trend' => $this->buildMonthComparison(
                    $leadsCreatedThisMonthCount,
                    $leadsCreatedPreviousMonthCount,
                ),
                'status-breakdown' => $this->buildMonthComparison(
                    $latestContactsThisMonthCount,
                    $contactsCreatedPreviousMonthCount,
                ),
                'latest-contacts' => $this->buildMonthComparison(
                    $latestContactsThisMonthCount,
                    $contactsCreatedPreviousMonthCount,
                ),
                'interactions-breakdown' => $this->buildMonthComparison(
                    $interactionsThisMonthCount,
                    $interactionsPreviousMonthCount,
                ),
            ],
            'dashboardContactInteractionsTrend' => $dashboardContactInteractionsTrend,
            'teamInteractionsLast30DaysCount' => $dashboardContactInteractionsTrend['total'],
            'hasProjects' => Project::where('team_id', $activeTeam->id)->exists(),
        ];
    }

    /**
     * @return array{
     *     subscriptionLevel: mixed,
     *     mentoringPlan: ?string,
     *     mentoringLevelName: ?string,
     *     mentoringMessage: ?string
     * }
     */
    private function resolveSubscriptionPresentation($activeTeam, bool $hasProjects): array
    {
        $subscriptionLevel = null;
        $mentoringPlan = null;
        $mentoringLevelName = null;
        $mentoringMessage = null;
        $hasMentoringSubscription = false;

        $activeSubscriptions = $activeTeam->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->get();

        foreach ($activeSubscriptions as $subscription)
        {
            if (! $subscription->active())
            {
                continue;
            }

            $product = null;
            if ($subscription->stripe_price)
            {
                $product = SubscriptionProduct::where('stripe_price', $subscription->stripe_price)->first();
            }

            if (! $product && $subscription->stripe_id)
            {
                try
                {
                    \Stripe\Stripe::setApiKey(config('cashier.secret'));
                    $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_id, ['expand' => ['items.data.price.product']]);
                    if ($stripeSub->items->data[0]->price->product)
                    {
                        $stripeProductId = is_string($stripeSub->items->data[0]->price->product)
                            ? $stripeSub->items->data[0]->price->product
                            : $stripeSub->items->data[0]->price->product->id;

                        $product = SubscriptionProduct::where('stripe_id', $stripeProductId)
                            ->orWhere('stripe_product', $stripeProductId)
                            ->first();
                    }
                } catch (\Exception $e)
                {
                    // Continue with existing logic when Stripe lookup fails.
                }
            }

            if (! $product)
            {
                continue;
            }

            $type = $product->type ?? $product->category;
            $category = $product->category;

            if ($subscription->type !== $category && $category)
            {
                $subscription->type = $category;
                $subscription->save();
            }

            if ($type === 'mailer')
            {
                $subscriptionLevel = EmailPlan::fromStripePriceId($subscription->stripe_price);
            } elseif ($type === 'mentoring' || $category === 'mentoring')
            {
                $hasMentoringSubscription = true;
                $mentoringPlan = $product->plan ?? null;

                if ($mentoringPlan)
                {
                    $mentoringLevelName = match ($mentoringPlan)
                    {
                        'creation' => 'Tu dossier comercial',
                        'operations' => 'Operaciones',
                        'bussiness-exit' => 'Business Exit',
                        'complete' => 'Complete',
                        default => $mentoringPlan,
                    };
                    $mentoringMessage = match ($mentoringPlan)
                    {
                        'creation' => 'Estás en la fase de Creación',
                        'operations' => 'Estás en la fase de Operaciones',
                        'bussiness-exit' => 'Estás en la fase de Business Exit',
                        'complete' => 'Tienes el plan completo',
                        default => '¡Vas viento en popa!',
                    };
                } elseif (! $hasProjects)
                {
                    $mentoringPlan = 'IDEA';
                    $mentoringLevelName = 'Tu dossier comercial';
                    $mentoringMessage = 'Haz tenido una gran IDEA';
                }
            } elseif ($type === 'hosting' || $category === 'hosting')
            {
                $subscriptionLevel = $product->plan ?? 'Hosting';
            }
        }

        if (! $hasMentoringSubscription)
        {
            $mentoringPlan = 'IDEA';
            $mentoringLevelName = 'Tu dossier comercial';
            $mentoringMessage = 'Haz tenido una gran IDEA';
        }

        return [
            'subscriptionLevel' => $subscriptionLevel,
            'mentoringPlan' => $mentoringPlan,
            'mentoringLevelName' => $mentoringLevelName,
            'mentoringMessage' => $mentoringMessage,
        ];
    }

    /**
     * Platform billing queue: usage invoices still draft, open, or uncollectible (root only).
     *
     * @return array{
     *     draft_count: int,
     *     open_count: int,
     *     overdue_count: int,
     *     uncollectible_count: int,
     *     total_cents: int,
     *     currency: string,
     *     items: list<array{
     *         id: int,
     *         team_id: int,
     *         team_name: string,
     *         status: string,
     *         status_label: string,
     *         status_badge: string,
     *         billed_cents: int,
     *         currency: string,
     *         period_label: string,
     *         period_from: string,
     *         period_to: string,
     *         stripe_invoice_id: ?string,
     *         stripe_url: ?string,
     *         account_url: ?string
     *     }>
     * }|null
     */
    private function buildUsageBillingAttentions(): ?array
    {
        if (! auth()->user()?->hasRole('root'))
        {
            return null;
        }

        $attentionStatuses = [
            TeamUsageInvoice::STATUS_DRAFT,
            TeamUsageInvoice::STATUS_OPEN,
            TeamUsageInvoice::STATUS_UNCOLLECTIBLE,
        ];

        $draftCount = TeamUsageInvoice::query()
            ->where('status', TeamUsageInvoice::STATUS_DRAFT)
            ->count();
        $openCount = TeamUsageInvoice::query()
            ->where('status', TeamUsageInvoice::STATUS_OPEN)
            ->count();
        $uncollectibleCount = TeamUsageInvoice::query()
            ->where('status', TeamUsageInvoice::STATUS_UNCOLLECTIBLE)
            ->count();

        $invoices = TeamUsageInvoice::query()
            ->with(['team' => fn ($q) => $q->select('id', 'name')])
            ->whereIn('status', $attentionStatuses)
            ->orderByRaw("CASE status WHEN 'uncollectible' THEN 0 WHEN 'open' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $currency = strtolower((string) ($invoices->first()?->currency ?: 'eur'));
        $stripeBase = str_starts_with((string) config('cashier.key'), 'pk_test_')
            ? 'https://dashboard.stripe.com/test/'
            : 'https://dashboard.stripe.com/';

        $items = $invoices->map(function (TeamUsageInvoice $invoice) use ($stripeBase): array
        {
            $from = $invoice->period_from?->format('d/m/Y') ?? '—';
            $to = $invoice->period_to?->format('d/m/Y') ?? '—';
            $stripeId = $invoice->stripe_invoice_id;
            [$statusLabel, $statusBadge] = $this->usageInvoiceAttentionPresentation($invoice);

            return [
                'id' => (int) $invoice->id,
                'team_id' => (int) $invoice->team_id,
                'team_name' => $invoice->team?->name ?? ('Team #'.$invoice->team_id),
                'status' => (string) $invoice->status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'billed_cents' => (int) $invoice->billed_cents,
                'currency' => strtolower((string) ($invoice->currency ?: 'eur')),
                'period_label' => $from.' – '.$to,
                'period_from' => $from,
                'period_to' => $to,
                'stripe_invoice_id' => $stripeId,
                'stripe_url' => $stripeId ? $stripeBase.'invoices/'.$stripeId : null,
                'account_url' => route('account.edit', $invoice->team_id),
            ];
        })->values()->all();

        $overdueCutoff = now()->subDays(7);
        $overdueCount = TeamUsageInvoice::query()
            ->where('status', TeamUsageInvoice::STATUS_OPEN)
            ->where(function ($query) use ($overdueCutoff)
            {
                $query->where(function ($q) use ($overdueCutoff)
                {
                    $q->whereNotNull('issued_at')->where('issued_at', '<', $overdueCutoff);
                })->orWhere(function ($q) use ($overdueCutoff)
                {
                    $q->whereNull('issued_at')
                        ->whereNotNull('period_to')
                        ->where('period_to', '<', $overdueCutoff);
                });
            })
            ->count();

        return [
            'draft_count' => $draftCount,
            'open_count' => $openCount,
            'overdue_count' => $overdueCount,
            'uncollectible_count' => $uncollectibleCount,
            'total_cents' => (int) $invoices->sum('billed_cents'),
            'currency' => $currency,
            'items' => $items,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function usageInvoiceAttentionPresentation(TeamUsageInvoice $invoice): array
    {
        if ($invoice->status === TeamUsageInvoice::STATUS_UNCOLLECTIBLE)
        {
            return [__('Incobrable'), 'bg-label-dark', true];
        }

        if ($invoice->status === TeamUsageInvoice::STATUS_OPEN)
        {
            $reference = $invoice->issued_at ?? $invoice->period_to;
            $isOverdue = $reference !== null && $reference->lt(now()->subDays(7));

            if ($isOverdue)
            {
                return [__('Vencida / fallida'), 'bg-label-danger', true];
            }

            return [__('Sin cobrar'), 'bg-label-danger', false];
        }

        if ($invoice->status === TeamUsageInvoice::STATUS_DRAFT)
        {
            $isPastPeriod = $invoice->period_to !== null && $invoice->period_to->lt(now());

            if ($isPastPeriod)
            {
                return [__('Borrador vencido'), 'bg-label-warning', true];
            }

            return [__('Borrador'), 'bg-label-warning', false];
        }

        return [__('Borrador'), 'bg-label-warning', false];
    }

    /**
     * @return array{dates: list<mixed>, visitors: list<mixed>, pageViews: list<mixed>}|null
     */
    private function cachedAnalyticsChartData($activeTeam): ?array
    {
        $propertyId = $activeTeam->getSetting('analytics_property_id');
        $credentialsJson = $activeTeam->getSetting('analytics_credentials_json');

        if (! $propertyId || ! $credentialsJson)
        {
            return null;
        }

        return Cache::remember(
            "dashboard.analytics.v3.{$activeTeam->id}",
            self::ANALYTICS_CACHE_SECONDS,
            function () use ($propertyId, $credentialsJson)
            {
                $credentials = is_string($credentialsJson) ? json_decode($credentialsJson, true) : $credentialsJson;
                if (! is_array($credentials))
                {
                    return null;
                }

                config([
                    'analytics.property_id' => $propertyId,
                    'analytics.service_account_credentials_json' => $credentials,
                ]);

                try
                {
                    $period = Period::days(30);
                    $collection = Analytics::fetchTotalVisitorsAndPageViews($period, 30);

                    $dates = $collection->pluck('date')->map(fn ($d) => $d instanceof Carbon ? $d->format('Y-m-d') : $d)->values()->all();
                    $visitors = $collection->pluck('activeUsers')->map(fn ($v) => (int) $v)->values()->all();
                    $pageViews = $collection->pluck('screenPageViews')->map(fn ($v) => (int) $v)->values()->all();

                    $totalVisitors = array_sum($visitors);
                    $totalPageViews = array_sum($pageViews);
                    $userTypes = $this->analyticsUserTypeCounts($period);

                    return [
                        'dates' => $dates,
                        'visitors' => $visitors,
                        'pageViews' => $pageViews,
                        'totals' => [
                            'visitors' => $totalVisitors,
                            'page_views' => $totalPageViews,
                            'new_users' => $userTypes['new'],
                            'returning_users' => $userTypes['returning'],
                        ],
                        'top_pages' => $this->analyticsTopPages($period, 5),
                        'top_countries' => $this->analyticsTopCountries($period, 5),
                    ];
                } catch (\Throwable $e)
                {
                    \Log::warning('Dashboard Google Analytics fetch failed: '.$e->getMessage());

                    return null;
                }
            },
        );
    }

    /**
     * @return list<array{title: string, url: string, views: int}>
     */
    private function analyticsTopPages(Period $period, int $limit = 5): array
    {
        try
        {
            return Analytics::fetchMostVisitedPages($period, $limit)
                ->map(fn ($row): array => [
                    'title' => (string) ($row['pageTitle'] ?? __('Sin título')),
                    'url' => (string) ($row['fullPageUrl'] ?? ''),
                    'views' => (int) ($row['screenPageViews'] ?? 0),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA top pages failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return list<array{country: string, views: int}>
     */
    private function analyticsTopCountries(Period $period, int $limit = 5): array
    {
        try
        {
            return Analytics::fetchTopCountries($period, $limit)
                ->map(fn ($row): array => [
                    'country' => (string) ($row['country'] ?? __('Desconocido')),
                    'views' => (int) ($row['screenPageViews'] ?? 0),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA top countries failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array{new: int, returning: int}
     */
    private function analyticsUserTypeCounts(Period $period): array
    {
        $counts = ['new' => 0, 'returning' => 0];

        try
        {
            foreach (Analytics::fetchUserTypes($period) as $row)
            {
                $type = strtolower((string) ($row['newVsReturning'] ?? ''));
                if (array_key_exists($type, $counts))
                {
                    $counts[$type] = (int) ($row['activeUsers'] ?? 0);
                }
            }
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA user types failed: '.$e->getMessage());
        }

        return $counts;
    }

    /**
     * @return array{
     *     today: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>
     * }|null
     */
    private function buildDashboardCalendarData($activeTeam): ?array
    {
        if (! $activeTeam || (! $activeTeam->hasModule('calendar') && ! $activeTeam->hasModule('today')))
        {
            return null;
        }

        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $upcomingLimit = $today->copy()->addDays(30)->endOfDay();

        $todayEvents = CalendarEvent::query()
            ->with('guests:id,name,surname')
            ->where('end', '>', $today)
            ->where('start', '<', $tomorrow)
            ->orderBy('start')
            ->get();

        $upcomingEvents = CalendarEvent::query()
            ->with('guests:id,name,surname')
            ->where('start', '>=', $tomorrow)
            ->where('start', '<=', $upcomingLimit)
            ->orderBy('start')
            ->limit(30)
            ->get();

        return [
            'today' => $todayEvents->map(fn (CalendarEvent $event) => $this->formatDashboardCalendarEvent($event))->values()->all(),
            'upcoming' => $upcomingEvents->map(fn (CalendarEvent $event) => $this->formatDashboardCalendarEvent($event))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDashboardCalendarEvent(CalendarEvent $event): array
    {
        $start = $event->start;
        $end = $event->end;
        $label = $event->label ?? 'Business';

        return [
            'id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'label' => $label,
            'label_class' => $this->calendarEventLabelClass($label),
            'all_day' => (bool) $event->all_day,
            'date_key' => $start?->toDateString(),
            'date_display' => $start?->isoFormat('D MMM YYYY') ?? '',
            'time_display' => $this->formatDashboardCalendarEventTime($event),
            'calendar_url' => route('app-calendar'),
            'guests' => $event->guests->map(fn (Contact $guest) => trim($guest->name.' '.$guest->surname))->filter()->values()->all(),
        ];
    }

    private function formatDashboardCalendarEventTime(CalendarEvent $event): string
    {
        if ($event->all_day)
        {
            return __('app.dashboard_calendar_all_day');
        }

        $start = $event->start;
        $end = $event->end;
        if ($start === null)
        {
            return '';
        }

        $formatted = $start->isoFormat('HH:mm');
        if ($end !== null && ! $start->equalTo($end))
        {
            $formatted .= ' – '.$end->isoFormat('HH:mm');
        }

        return $formatted;
    }

    private function calendarEventLabelClass(?string $label): string
    {
        return match ($label)
        {
            'Personal' => 'danger',
            'Family' => 'warning',
            'Holiday' => 'success',
            'ETC' => 'info',
            default => 'primary',
        };
    }

    private function countTeamContactsCreatedBetween(
        int $teamId,
        Carbon $start,
        Carbon $end,
        ?int $statusId = null,
        ?int $responsibleId = null,
    ): int {
        $query = Contact::query()
            ->where('team_id', $teamId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);

        if ($statusId !== null)
        {
            $query->where('status_id', $statusId);
        }

        if ($responsibleId !== null)
        {
            $query->where('responsible_id', $responsibleId);
        }

        return $query->count();
    }

    /**
     * @return array{current: int, previous: int, difference: int, percent_change: float, direction: string}
     */
    private function buildMonthComparison(int $current, int $previous): array
    {
        $difference = $current - $previous;
        $percentChange = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : ($current > 0 ? 100.0 : 0.0);

        $direction = 'neutral';
        if ($difference > 0)
        {
            $direction = 'up';
        } elseif ($difference < 0)
        {
            $direction = 'down';
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'difference' => $difference,
            'percent_change' => $percentChange,
            'direction' => $direction,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function buildContactsCreatedTrend(
        int $teamId,
        int $days,
        ?int $statusId = null,
        ?int $responsibleId = null,
    ): array {
        $since = now()->subDays($days - 1)->startOfDay();

        $query = Contact::query()
            ->where('team_id', $teamId)
            ->where('created_at', '>=', $since);

        if ($statusId !== null)
        {
            $query->where('status_id', $statusId);
        }

        if ($responsibleId !== null)
        {
            $query->where('responsible_id', $responsibleId);
        }

        $countsByDay = $query
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day');

        $trend = [
            'labels' => [],
            'values' => [],
        ];

        for ($dayOffset = $days - 1; $dayOffset >= 0; $dayOffset--)
        {
            $dayStart = now()->subDays($dayOffset)->startOfDay();
            $dayKey = $dayStart->toDateString();
            $trend['labels'][] = $dayStart->isoFormat('D MMM');
            $trend['values'][] = (int) ($countsByDay[$dayKey] ?? 0);
        }

        return $trend;
    }
}
