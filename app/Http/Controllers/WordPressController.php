<?php

namespace App\Http\Controllers;

use App\Services\WordPressService;
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
     * List WordPress posts from the REST API.
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

        $service = new WordPressService($team);
        $posts = $service->getPosts(1, 100);
        $storeUrl = $service->getSiteUrl();

        return view('wordpress.posts-list', [
            'team' => $team,
            'storeUrl' => $storeUrl,
            'posts' => $posts,
        ]);
    }

    /**
     * List WordPress pages from the REST API.
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

        $service = new WordPressService($team);
        $pages = $service->getPages(1, 100);
        $storeUrl = $service->getSiteUrl();

        return view('wordpress.pages-list', [
            'team' => $team,
            'storeUrl' => $storeUrl,
            'pages' => $pages,
        ]);
    }
}
