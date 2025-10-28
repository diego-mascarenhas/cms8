<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\List60;
use App\Models\Project;
use App\Models\UserContactAction;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;
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

        // Calculate total team minutes
        $totalTeamSeconds = UserContactAction::whereHas('contact', function ($query) use ($activeTeam)
        {
            $query->where('team_id', $activeTeam->id);
        })
            ->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        $totalTeamMinutes = round($totalTeamSeconds / 60);

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

        // Get recent activities from team members
        $teamUserIds = $activeTeam->users->pluck('id');
        $recentActivities = Activity::with(['causer', 'subject'])
            ->whereIn('causer_id', $teamUserIds)
            ->latest()
            ->take(8)
            ->get();

        // Format activities for display
        $formattedActivities = $recentActivities->map(function ($activity)
        {
            return [
                'id' => $activity->id,
                'user_name' => $activity->causer ? $activity->causer->name : 'Sistema',
                'user_photo' => $activity->causer ? $activity->causer->profile_photo_url : null,
                'description' => $activity->description,
                'subject_type' => $activity->subject ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'time_ago' => $activity->created_at->diffForHumans(),
                'created_at' => $activity->created_at,
                'properties' => $activity->properties,
            ];
        });

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
        ));
    }
}
