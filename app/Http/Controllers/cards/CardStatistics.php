<?php

namespace App\Http\Controllers\cards;

use App\Http\Controllers\Controller;

class CardStatistics extends Controller
{
    public function index()
    {
        return view('content.cards.cards-statistics');
    }
}
