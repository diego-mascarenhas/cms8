<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WordPressController extends Controller
{
    protected function isConfigured(): bool
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return false;
        }
        $url = $team->getSetting('wordpress_url');
        $user = $team->getSetting('wordpress_username');
        $password = $team->getSetting('wordpress_application_password');

        return ! empty($url) && ! empty($user) && ! empty($password);
    }

    /**
     * List WordPress posts (placeholder until WordPressService is implemented).
     */
    public function posts(): View|RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return view('wordpress.configure-first', [
                'type' => 'posts',
                'team' => $team,
            ]);
        }

        return view('wordpress.posts-list', [
            'team' => $team,
            'storeUrl' => rtrim($team->getSetting('wordpress_url'), '/'),
        ]);
    }

    /**
     * List WordPress pages (placeholder until WordPressService is implemented).
     */
    public function pages(): View|RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return view('wordpress.configure-first', [
                'type' => 'pages',
                'team' => $team,
            ]);
        }

        return view('wordpress.pages-list', [
            'team' => $team,
            'storeUrl' => rtrim($team->getSetting('wordpress_url'), '/'),
        ]);
    }
}
