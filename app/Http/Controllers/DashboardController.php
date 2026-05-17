<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Enterprise;
use App\Models\List60;
use App\Models\Project;
use App\Models\SubscriptionProduct;
use App\Models\UserContactAction;
use App\Services\UserDailyPerformanceInsightService;
use Carbon\Carbon;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Stripe\Balance;
use Stripe\Stripe;

class DashboardController extends Controller
{
    public function index()
    {
        // Get active team
        $activeTeam = auth()->user()->currentTeam ?? auth()->user()->teams->first();

        // Initialize Stripe variables
        $totalBalance = 0;
        $currentMonthRevenue = 0;
        $lastMonthRevenue = 0;

        if (! $activeTeam)
        {
            return redirect()->back()->with('error', 'No team assigned');
        }

        // Calculate total team minutes (only positive values)
        $totalTeamSeconds = UserContactAction::whereHas('contact', function ($query) use ($activeTeam)
        {
            $query->where('team_id', $activeTeam->id);
        })
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)  // Only count positive durations
            ->sum('duration_seconds');

        // Ensure we never have negative minutes
        $totalTeamMinutes = max(0, round($totalTeamSeconds / 60));

        // Clients to contact today (List of 60) - only when module is enabled
        $today = Carbon::today();
        $clientsToContactToday = 0;
        $todayContacts = null;
        if ($activeTeam && $activeTeam->hasModule('list60'))
        {
            $clientsToContactToday = List60::whereHas('contact', function ($query) use ($activeTeam)
            {
                $query->where('team_id', $activeTeam->id);
            })->whereDate('date_next', $today)->count();

            $todayContacts = List60::with(['contact.enterprises', 'contact.currentSentiment.sentiment'])
                ->whereHas('contact', function ($query) use ($activeTeam)
                {
                    $query->where('team_id', $activeTeam->id);
                })
                ->whereDate('date_next', Carbon::today())
                ->get();
        }

        // Get latest sentiment for each contact (filtered by team)
        $contacts = Contact::where('team_id', $activeTeam->id)
            ->with(['currentSentiment' => function ($query)
            {
                $query->latest();
            }])
            ->get();

        // Count current sentiments
        $sentimentCounts = $contacts
            ->pluck('currentSentiment.sentiment_id')
            ->filter()
            ->countBy();

        // Define sentiments
        $sentiments = [
            1 => 'Muy Negativo',
            2 => 'Negativo',
            3 => 'Neutral',
            4 => 'Positivo',
            5 => 'Muy Positivo',
        ];

        // Ensure all sentiments are represented
        foreach ($sentiments as $id => $name)
        {
            if (! isset($sentimentCounts[$id]))
            {
                $sentimentCounts[$id] = 0;
            }
        }

        // Prepare data for view
        $sentimentData = [];
        foreach ($sentiments as $id => $name)
        {
            $sentimentData[] = [
                'label' => $name,
                'count' => $sentimentCounts[$id],
            ];
        }

        // Count leads from last 7 days (filtered by team)
        $recentLeadsCount = Contact::where('team_id', $activeTeam->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $authUser = auth()->user();
        $totalContactsCount = Contact::query()->count();
        $totalClientsCount = $activeTeam->hasModule('clients')
            ? Enterprise::query()->where('team_id', $activeTeam->id)->count()
            : 0;
        $contactsWithRecentActivityCount = (int) ContactInteraction::query()
            ->whereHas('contact', function ($query) use ($activeTeam, $authUser): void
            {
                $query->where('team_id', $activeTeam->id);
                if ($authUser->hasRole('collaborator'))
                {
                    $query->where('responsible_id', $authUser->id);
                }
            })
            ->where('occurred_at', '>=', now()->subDays(7))
            ->distinct()
            ->count('contact_id');

        $dashboardContactsCreatedTrend = [
            'labels' => [],
            'values' => [],
        ];
        for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--)
        {
            $dayStart = now()->subDays($dayOffset)->startOfDay();
            $dayEnd = $dayStart->copy()->addDay();
            $dashboardContactsCreatedTrend['labels'][] = $dayStart->isoFormat('ddd');
            $dashboardContactsCreatedTrend['values'][] = Contact::query()
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->count();
        }

        // Get contacts to follow up today (filtered by team)
        $todayContacts = List60::with(['contact.enterprises', 'contact.currentSentiment.sentiment'])
            ->whereHas('contact', function ($query) use ($activeTeam)
            {
                $query->where('team_id', $activeTeam->id);
            })
            ->whereDate('date_next', Carbon::today())
            ->get();

        // Retrieve ongoing projects only if Projects module is enabled for the team
        $ongoingProjects = null;
        if ($activeTeam && $activeTeam->hasModule('projects'))
        {
            // Include BUDGET (1), BUDGETED (2), and IN_PROGRESS (9) statuses
            // Order by status (IN_PROGRESS first), then by updated_at
            $ongoingProjects = Project::with(['client', 'responsible', 'status'])
                ->where('team_id', $activeTeam->id)
                ->whereIn('status_id', [1, 2, 9])  // BUDGET, BUDGETED, IN_PROGRESS
                ->orderByRaw('CASE status_id WHEN 9 THEN 1 WHEN 2 THEN 2 WHEN 1 THEN 3 ELSE 4 END')
                ->orderBy('updated_at', 'desc')
                ->take(10)  // Limit to 10 projects
                ->get();
        }

        // Recent activities removed - Activity Log package was removed from the project
        $formattedActivities = collect();

        // Get subscription level
        $subscriptionLevel = null;
        $mentoringPlan = null;
        $mentoringLevelName = null;
        $mentoringMessage = null;
        $hasProjects = Project::where('team_id', $activeTeam->id)->exists();
        $hasMentoringSubscription = false;

        // Get active subscriptions
        $activeSubscriptions = $activeTeam->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->get();

        // Determine subscription level based on active subscriptions
        foreach ($activeSubscriptions as $subscription)
        {
            if (! $subscription->active())
            {
                continue;
            }

            // Get product from subscription - try multiple ways to find it
            $product = null;
            if ($subscription->stripe_price)
            {
                $product = SubscriptionProduct::where('stripe_price', $subscription->stripe_price)->first();
            }

            // If not found by price, try to find by subscription metadata or type
            if (! $product && $subscription->stripe_id)
            {
                // Try to get product info from Stripe directly
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
                    // Silently fail, continue with existing logic
                }
            }

            if ($product)
            {
                $type = $product->type ?? $product->category;
                $category = $product->category;

                // Update subscription type if it doesn't match the product category
                if ($subscription->type !== $category && $category)
                {
                    $subscription->type = $category;
                    $subscription->save();
                }

                if ($type === 'mailer')
                {
                    // Mailer subscription
                    $subscriptionLevel = EmailPlan::fromStripePriceId($subscription->stripe_price);
                } elseif ($type === 'mentoring' || $category === 'mentoring')
                {
                    // Mentoring subscription
                    $hasMentoringSubscription = true;
                    // Get the plan from the product
                    $mentoringPlan = $product->plan ?? null;

                    // If there's a plan, use it; otherwise check if no projects (IDEA plan)
                    if ($mentoringPlan)
                    {
                        // Plan pago activo
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
                        // Plan gratuito IDEA (dossier comercial) - solo si no hay plan y no hay proyectos
                        $mentoringPlan = 'IDEA';
                        $mentoringLevelName = 'Tu dossier comercial';
                        $mentoringMessage = 'Haz tenido una gran IDEA';
                    }
                } elseif ($type === 'hosting' || $category === 'hosting')
                {
                    // Hosting subscription
                    $subscriptionLevel = $product->plan ?? 'Hosting';
                }
            }
        }

        // If no mentoring subscription exists, show IDEA plan (free)
        if (! $hasMentoringSubscription)
        {
            $mentoringPlan = 'IDEA';
            $mentoringLevelName = 'Tu dossier comercial';
            $mentoringMessage = 'Haz tenido una gran IDEA';
        }

        // Stripe revenue calculation - COMMENTED OUT (resource intensive)
        // if ($activeTeam && $activeTeam->getSetting('stripe_secret'))
        // {
        //     try
        //     {
        //         Stripe::setApiKey($activeTeam->getSetting('stripe_secret'));

        //         // Get balance
        //         $balance = Balance::retrieve();
        //         \Log::info('Stripe Balance:', ['balance' => $balance]);
        //         $availableBalance = collect($balance->available)->sum('amount') / 100;
        //         $pendingBalance = collect($balance->pending)->sum('amount') / 100;
        //         $totalBalance = $availableBalance + $pendingBalance;

        //         // Get current and last month revenue
        //         $startOfCurrentMonth = Carbon::now()->startOfMonth()->timestamp;
        //         $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth()->timestamp;
        //         $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth()->timestamp;

        //         \Log::info('Date ranges:', [
        //             'current_month_start' => date('Y-m-d H:i:s', $startOfCurrentMonth),
        //             'last_month_start' => date('Y-m-d H:i:s', $startOfLastMonth),
        //             'last_month_end' => date('Y-m-d H:i:s', $endOfLastMonth),
        //         ]);

        //         // Get all paid invoices
        //         $invoices = \Stripe\Invoice::all([
        //             'status' => 'paid',
        //             'limit' => 100,
        //         ]);

        //         // Filter invoices by payment date
        //         $currentMonthInvoices = collect($invoices->data)->filter(function ($invoice) use ($startOfCurrentMonth)
        //         {
        //             $paidAt = $invoice->status_transitions->paid_at ?? $invoice->created;

        //             return $paidAt >= $startOfCurrentMonth;
        //         });

        //         $lastMonthInvoices = collect($invoices->data)->filter(function ($invoice) use ($startOfLastMonth, $endOfLastMonth)
        //         {
        //             $paidAt = $invoice->status_transitions->paid_at ?? $invoice->created;

        //             return $paidAt >= $startOfLastMonth && $paidAt <= $endOfLastMonth;
        //         });

        //         // Use total instead of amount_paid for external payments
        //         if ($currentMonthInvoices->isNotEmpty())
        //         {
        //             $currentMonthRevenue = $currentMonthInvoices->sum('total') / 100;
        //         }

        //         if ($lastMonthInvoices->isNotEmpty())
        //         {
        //             $lastMonthRevenue = $lastMonthInvoices->sum('total') / 100;
        //         }
        //     } catch (\Exception $e)
        //     {
        //         \Log::error('Error fetching Stripe data: '.$e->getMessage());
        //     }
        //         }

        $recentContactActivities = ContactInteraction::query()
            ->whereHas('contact', function ($query) use ($activeTeam, $authUser): void
            {
                $query->where('team_id', $activeTeam->id);
                if ($authUser->hasRole('collaborator'))
                {
                    $query->where('responsible_id', $authUser->id);
                }
            })
            ->with([
                'contact:id,name,surname,team_id,responsible_id',
                'user:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->limit(15)
            ->get();

        // Google Analytics: fetch chart data only when team has GA4 configured
        $analyticsChartData = null;
        if ($activeTeam
            && $activeTeam->getSetting('analytics_property_id')
            && $activeTeam->getSetting('analytics_credentials_json')
        ) {
            $credentialsJson = $activeTeam->getSetting('analytics_credentials_json');
            $credentials = is_string($credentialsJson) ? json_decode($credentialsJson, true) : $credentialsJson;
            if (is_array($credentials))
            {
                config([
                    'analytics.property_id' => $activeTeam->getSetting('analytics_property_id'),
                    'analytics.service_account_credentials_json' => $credentials,
                ]);
                try
                {
                    $collection = Analytics::fetchTotalVisitorsAndPageViews(Period::days(7), 7);
                    $analyticsChartData = [
                        'dates' => $collection->pluck('date')->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->format('Y-m-d') : $d)->values()->all(),
                        'visitors' => $collection->pluck('activeUsers')->values()->all(),
                        'pageViews' => $collection->pluck('screenPageViews')->values()->all(),
                    ];
                } catch (\Throwable $e)
                {
                    \Log::warning('Dashboard Google Analytics fetch failed: '.$e->getMessage());
                }
            }
        }

        $dailyPerformanceInsight = null;
        if (auth()->user()->hasAnyRole(['admin', 'root']) && $activeTeam->hasModule('performance_insights'))
        {
            $dailyPerformanceInsight = app(UserDailyPerformanceInsightService::class)
                ->findTodayInsight(auth()->user(), $activeTeam);
        }

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
            'recentContactActivities',
            'totalContactsCount',
            'totalClientsCount',
            'contactsWithRecentActivityCount',
            'dashboardContactsCreatedTrend',
            'dailyPerformanceInsight',
        ));
    }
}
