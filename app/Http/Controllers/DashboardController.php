<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\Contact;
use App\Models\List60;
use App\Models\Project;
use App\Models\SubscriptionProduct;
use App\Models\UserContactAction;
use Carbon\Carbon;
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

        // Filter dangerous contacts by team
        $dangerousContacts = Contact::where('team_id', $activeTeam->id)
            ->whereHas('sentimentHistories', function ($query)
            {
                $query
                    ->whereIn('sentiment_id', [1, 2])
                    ->whereIn('id', function ($subQuery)
                    {
                        $subQuery
                            ->selectRaw('MAX(id)')
                            ->from('contact_sentiment_histories')
                            ->groupBy('contact_id');
                    });
            })
            ->where('status_id', 5)
            ->with(['currentSentiment' => function ($query)
            {
                $query->whereIn('sentiment_id', [1, 2]);
            }])
            ->get();

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
                ->orderByRaw('FIELD(status_id, 9, 2, 1)')  // IN_PROGRESS (9) first, then BUDGETED (2), then BUDGET (1)
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

            // Get product from subscription
            $product = SubscriptionProduct::where('stripe_price', $subscription->stripe_price)->first();

            if ($product)
            {
                $type = $product->type ?? $product->category;
                $category = $product->category;

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
        // }

        return view('dashboard', compact(
            'totalTeamMinutes',
            'dangerousContacts',
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
        ));
    }
}
