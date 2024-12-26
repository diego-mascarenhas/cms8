<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\UserContactAction;
use App\Models\List60;

use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Balance;

class DashboardController extends Controller
{
    public function index()
    {
        // Get active team
        $activeTeam = auth()->user()->currentTeam ?? auth()->user()->teams->first();

        if (!$activeTeam)
        {
            return redirect()->back()->with('error', 'No team assigned');
        }

        // Calculate total team minutes
        $totalTeamSeconds = UserContactAction::whereHas('contact', function ($query) use ($activeTeam)
        {
            $query->where('team_id', $activeTeam->id);
        })->whereNotNull('duration_seconds')
            ->sum('duration_seconds');

        $totalTeamMinutes = round($totalTeamSeconds / 60);

        // Filter dangerous contacts by team
        $dangerousContacts = Contact::where('team_id', $activeTeam->id)
            ->whereHas('sentimentHistories', function ($query)
            {
                $query->whereIn('sentiment_id', [1, 2])
                    ->whereIn('id', function ($subQuery)
                    {
                        $subQuery->selectRaw('MAX(id)')
                            ->from('contact_sentiment_histories')
                            ->groupBy('contact_id');
                    });
            })->where('status_id', 5)
            ->with(['currentSentiment' => function ($query)
            {
                $query->whereIn('sentiment_id', [1, 2]);
            }])->get();

        // Clients to contact today (filtered by team)
        $today = Carbon::today();
        $clientsToContactToday = List60::whereHas('contact', function ($query) use ($activeTeam)
        {
            $query->where('team_id', $activeTeam->id);
        })->whereDate('date_next', $today)->count();

        // Get latest sentiment for each contact (filtered by team)
        $contacts = Contact::where('team_id', $activeTeam->id)
            ->with(['currentSentiment' => function ($query)
            {
                $query->latest();
            }])->get();

        // Count current sentiments
        $sentimentCounts = $contacts->pluck('currentSentiment.sentiment_id')
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
            if (!isset($sentimentCounts[$id]))
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
        $todayContacts = List60::with(['contact.enterprise', 'contact.currentSentiment.sentiment'])
            ->whereHas('contact', function ($query) use ($activeTeam)
            {
                $query->where('team_id', $activeTeam->id);
            })
            ->whereDate('date_next', Carbon::today())
            ->get();

        // Get Stripe balance and current month revenue
        $totalBalance = 0;
        $currentMonthRevenue = 0;

        if ($activeTeam && $activeTeam->getSetting('stripe_secret'))
        {
            try
            {
                Stripe::setApiKey($activeTeam->getSetting('stripe_secret'));
                
                // Get balance
                $balance = Balance::retrieve();
                \Log::info('Stripe Balance:', ['balance' => $balance]);
                $availableBalance = collect($balance->available)->sum('amount') / 100;
                $pendingBalance = collect($balance->pending)->sum('amount') / 100;
                $totalBalance = $availableBalance + $pendingBalance;

                // Get current and last month revenue
                $startOfCurrentMonth = Carbon::now()->startOfMonth()->timestamp;
                $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth()->timestamp;
                $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth()->timestamp;
                
                \Log::info('Date ranges:', [
                    'current_month_start' => date('Y-m-d H:i:s', $startOfCurrentMonth),
                    'last_month_start' => date('Y-m-d H:i:s', $startOfLastMonth),
                    'last_month_end' => date('Y-m-d H:i:s', $endOfLastMonth)
                ]);
                
                // Get all paid invoices
                $invoices = \Stripe\Invoice::all([
                    'status' => 'paid',
                    'limit' => 100
                ]);
                
                \Log::info('Stripe Invoices:', [
                    'invoices' => collect($invoices->data)->map(function($invoice) {
                        return [
                            'id' => $invoice->id,
                            'amount' => $invoice->amount_paid / 100,
                            'created' => date('Y-m-d H:i:s', $invoice->created),
                            'paid_at' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at ?? $invoice->created),
                            'status' => $invoice->status
                        ];
                    })
                ]);
                
                // Filter invoices by payment date
                $currentMonthInvoices = collect($invoices->data)->filter(function($invoice) use ($startOfCurrentMonth) {
                    $paidAt = $invoice->status_transitions->paid_at ?? $invoice->created;
                    $isCurrentMonth = $paidAt >= $startOfCurrentMonth;
                    
                    \Log::info("Invoice {$invoice->id} paid_at: " . date('Y-m-d H:i:s', $paidAt) . 
                             " amount: " . ($invoice->total / 100) . 
                             " isCurrentMonth: " . ($isCurrentMonth ? 'true' : 'false'));
                    
                    return $isCurrentMonth;
                });
                
                $lastMonthInvoices = collect($invoices->data)->filter(function($invoice) use ($startOfLastMonth, $endOfLastMonth) {
                    $paidAt = $invoice->status_transitions->paid_at ?? $invoice->created;
                    return $paidAt >= $startOfLastMonth && $paidAt <= $endOfLastMonth;
                });
                
                // Use total instead of amount_paid for external payments
                $currentMonthRevenue = $currentMonthInvoices->sum('total') / 100;
                $lastMonthRevenue = $lastMonthInvoices->sum('total') / 100;
                
                \Log::info('Current Month Invoices:', [
                    'invoices' => $currentMonthInvoices->map(function($invoice) {
                        return [
                            'id' => $invoice->id,
                            'amount' => $invoice->total / 100,
                            'paid_at' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at ?? $invoice->created),
                            'payment_type' => $invoice->payment_intent ? 'stripe' : 'external'
                        ];
                    })
                ]);
                
                \Log::info('Last Month Invoices:', [
                    'invoices' => $lastMonthInvoices->map(function($invoice) {
                        return [
                            'id' => $invoice->id,
                            'amount' => $invoice->amount_paid / 100,
                            'paid_at' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at ?? $invoice->created)
                        ];
                    })
                ]);
                
                \Log::info('Calculated Revenue:', [
                    'currentMonthRevenue' => $currentMonthRevenue,
                    'lastMonthRevenue' => $lastMonthRevenue
                ]);
            }
            catch (\Exception $e)
            {
                \Log::error('Error fetching Stripe data: ' . $e->getMessage());
            }
        }

        return view('dashboard', compact(
            'totalTeamMinutes',
            'dangerousContacts',
            'clientsToContactToday',
            'sentimentData',
            'recentLeadsCount',
            'todayContacts',
            'totalBalance',
            'currentMonthRevenue',
            'lastMonthRevenue'
        ));
    }
}
