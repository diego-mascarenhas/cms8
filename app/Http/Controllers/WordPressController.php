<?php

namespace App\Http\Controllers;

use App\Jobs\SyncWordPressContentJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * WordPress integration. Content editing now lives in the CMS module (/cms/posts) with
 * real-time bidirectional sync; this controller only triggers the content sync that
 * feeds the AI assistant (pages, posts and WooCommerce products into wordpress_sync_*).
 */
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
     * Trigger sync of WordPress content for the assistant (queued).
     */
    public function sync(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return redirect()->back()->with('error', __('WordPress not configured.'));
        }

        SyncWordPressContentJob::dispatch($team);

        return redirect()->back()->with('success', __('Sincronización iniciada en segundo plano. El contenido estará disponible para el asistente en unos momentos.'));
    }
}
