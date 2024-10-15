<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\UserContactAction;

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
    
        return view('dashboard', compact('totalTeamMinutes', 'dangerousContacts'));
    }
}
