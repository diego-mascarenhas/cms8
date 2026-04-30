<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CampaignsController extends Controller
{
    public function index(): View
    {
        return view('campaigns.index');
    }

    public function edit(string $campaign): View
    {
        return view('campaigns.edit', ['campaign' => $campaign]);
    }
}
