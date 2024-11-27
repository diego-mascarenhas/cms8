<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\UserContactAction;
use App\Models\Client;
use App\Models\List60;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Total team minutes
        $totalTeamSeconds = UserContactAction::sum('duration_seconds');
        $totalTeamMinutes = round($totalTeamSeconds / 60);

        $dangerousContacts = Contact::whereHas('sentimentHistories', function ($query) {
            $query->whereIn('sentiment_id', [1, 2])
                  ->whereIn('id', function ($subQuery) {
                      $subQuery->selectRaw('MAX(id)')
                               ->from('contact_sentiment_histories')
                               ->groupBy('contact_id');
                  });
        })->where('status_id', 5)
        ->with(['currentSentiment' => function ($query) {
            $query->whereIn('sentiment_id', [1, 2]);
        }])->get();

        // Clients to contact today
        $today = Carbon::today();
        $clientsToContactToday = List60::whereDate('date_next', $today)->count();

        // Get latest sentiment for each contact
        $contacts = Contact::with(['currentSentiment' => function($query) {
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
        foreach ($sentiments as $id => $name) {
            if (!isset($sentimentCounts[$id])) {
                $sentimentCounts[$id] = 0;
            }
        }

        // Prepare data for view
        $sentimentData = [];
        foreach ($sentiments as $id => $name) {
            $sentimentData[] = [
                'label' => $name,
                'count' => $sentimentCounts[$id],
            ];
        }

        // Count leads from last 7 days
        $recentLeadsCount = Contact::where('created_at', '>=', now()->subDays(7))->count();

        // Get contacts to follow up today
        $todayContacts = List60::with(['contact.enterprise', 'contact.currentSentiment.sentiment'])
            ->whereDate('date_next', Carbon::today())
            ->get();

        return view('dashboard', compact(
            'totalTeamMinutes', 
            'dangerousContacts', 
            'clientsToContactToday', 
            'sentimentData',
            'recentLeadsCount',
            'todayContacts'
        ));
    }
}
