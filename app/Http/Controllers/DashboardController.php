<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\UserContactAction;
use App\Models\Client;
use App\Models\List60;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
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

        $today = Carbon::today();
        $clientsToContactToday = List60::whereDate('date_next', $today)->count();
    
        return view('dashboard', compact('totalTeamMinutes', 'dangerousContacts', 'clientsToContactToday'));
    }
}
