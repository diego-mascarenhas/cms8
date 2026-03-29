<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    public function show(string $slug): View
    {
        $team = Team::findForPublicCatalog($slug);
        if (! $team)
        {
            abort(404);
        }

        return view('public-shop.show', [
            'team' => $team,
            'slug' => $slug,
        ]);
    }
}
