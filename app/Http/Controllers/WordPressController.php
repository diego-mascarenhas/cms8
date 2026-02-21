<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWordPressPageRequest;
use App\Http\Requests\UpdateWordPressPostRequest;
use App\Jobs\SyncWordPressContentJob;
use App\Models\WordPressSyncPage;
use App\Models\WordPressSyncPost;
use App\Models\WordPressSyncProduct;
use App\Services\WordPressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'lastSyncedAt' => $this->getLastSyncedAt($team->id),
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
            'lastSyncedAt' => $this->getLastSyncedAt($team->id),
        ]);
    }

    /**
     * Edit a single post locally (form loads from WordPress API).
     */
    public function editPost(Request $request, int $id): View|RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return view('wordpress.configure-first', ['type' => 'posts', 'team' => $team]);
        }

        $service = new WordPressService($team);
        $post = $service->getPost($id);
        if ($post === null)
        {
            return redirect()->route('wordpress.posts')->with('error', __('Post not found.'));
        }

        return view('wordpress.post-edit', [
            'team' => $team,
            'storeUrl' => $service->getSiteUrl(),
            'post' => $post,
        ]);
    }

    /**
     * Update a post in WordPress via API.
     */
    public function updatePost(UpdateWordPressPostRequest $request, int $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return redirect()->route('wordpress.posts')->with('error', __('WordPress not configured.'));
        }

        $service = new WordPressService($team);
        $data = array_filter([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'excerpt' => $request->input('excerpt'),
            'status' => $request->input('status'),
        ], fn ($v) => $v !== null && $v !== '');

        $updated = $service->updatePost($id, $data);
        if ($updated === null)
        {
            return redirect()->back()->withInput()->with('error', __('Failed to update post in WordPress.'));
        }

        return redirect()->route('wordpress.posts')->with('success', __('Post updated successfully.'));
    }

    /**
     * Edit a single page locally (form loads from WordPress API).
     */
    public function editPage(Request $request, int $id): View|RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return view('wordpress.configure-first', ['type' => 'pages', 'team' => $team]);
        }

        $service = new WordPressService($team);
        $page = $service->getPage($id);
        if ($page === null)
        {
            return redirect()->route('wordpress.pages')->with('error', __('Page not found.'));
        }

        return view('wordpress.page-edit', [
            'team' => $team,
            'storeUrl' => $service->getSiteUrl(),
            'page' => $page,
        ]);
    }

    /**
     * Update a page in WordPress via API.
     */
    public function updatePage(UpdateWordPressPageRequest $request, int $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }
        if (! $this->isConfigured())
        {
            return redirect()->route('wordpress.pages')->with('error', __('WordPress not configured.'));
        }

        $service = new WordPressService($team);
        $data = array_filter([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'status' => $request->input('status'),
        ], fn ($v) => $v !== null && $v !== '');

        $updated = $service->updatePage($id, $data);
        if ($updated === null)
        {
            return redirect()->back()->withInput()->with('error', __('Failed to update page in WordPress.'));
        }

        return redirect()->route('wordpress.pages')->with('success', __('Page updated successfully.'));
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

    protected function getLastSyncedAt(int $teamId): ?\Carbon\Carbon
    {
        $pages = WordPressSyncPage::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');
        $posts = WordPressSyncPost::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');
        $products = WordPressSyncProduct::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');

        $max = collect([$pages, $posts, $products])->filter()->max();

        return $max ? \Carbon\Carbon::parse($max) : null;
    }
}
