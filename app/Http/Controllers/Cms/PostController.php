<?php

namespace App\Http\Controllers\Cms;

use App\DataTables\PostDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePostRequest;
use App\Http\Requests\Cms\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostType;
use App\Models\TermTaxonomy;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index(PostDataTable $dataTable, Request $request, ?string $type = null)
    {
        $this->authorize('viewAny', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->orderBy('label')->get();
        $currentType = $this->resolveCurrentType($type, $postTypes);
        $wordpressSyncEnabled = WordPressContentSyncService::make($request->user()->currentTeam)->isEnabled();

        return $dataTable
            ->forPostType($currentType?->name)
            ->render('cms.posts.index', compact('postTypes', 'currentType', 'wordpressSyncEnabled'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->get();
        $currentType = $this->resolveCurrentType($request->query('type'), $postTypes);
        $availableTerms = $this->termsForType($currentType);
        $selectedTermIds = [];

        return view('cms.posts.form', [
            'post' => null,
            'postTypes' => $postTypes,
            'currentType' => $currentType,
            'availableTerms' => $availableTerms,
            'selectedTermIds' => $selectedTermIds,
            'listingUrl' => $this->listingUrl($currentType?->name),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $post = new Post;
        $this->persist($post, $request->validated());

        return redirect()
            ->to($this->listingUrl($post->post_type))
            ->with('success', __('app.Post created successfully.'));
    }

    public function show(Post $post): View
    {
        $this->authorize('view', $post);

        $post->load(['meta', 'termTaxonomies.term', 'author']);

        return view('cms.posts.show', [
            'post' => $post,
            'listingUrl' => $this->listingUrl($post->post_type),
        ]);
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        $postTypes = PostType::query()->orderBy('menu_order')->get();
        $currentType = $postTypes->firstWhere('name', $post->post_type);
        $availableTerms = $this->termsForType($currentType);
        $selectedTermIds = $post->termTaxonomies()->pluck('term_taxonomy.id')->all();

        return view('cms.posts.form', [
            'post' => $post,
            'postTypes' => $postTypes,
            'currentType' => $currentType,
            'availableTerms' => $availableTerms,
            'selectedTermIds' => $selectedTermIds,
            'listingUrl' => $this->listingUrl($post->post_type),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->persist($post, $request->validated());

        return redirect()
            ->to($this->listingUrl($post->post_type))
            ->with('success', __('app.Post updated successfully.'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $type = $post->post_type;
        $post->delete();

        return redirect()
            ->to($this->listingUrl($type))
            ->with('success', __('app.Post deleted successfully.'));
    }

    /**
     * Clean listing URL for a given post type (dedicated routes for page/post, generic otherwise).
     */
    private function listingUrl(?string $type): string
    {
        return match ($type)
        {
            'page' => route('cms.pages.index'),
            'post', null => route('cms.posts.index'),
            default => route('cms.type.index', ['type' => $type]),
        };
    }

    public function syncWordPress(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $team = $request->user()->currentTeam;
        $service = WordPressContentSyncService::make($team);

        if (! $service->isEnabled())
        {
            return back()->with('error', __('app.WordPress sync is not configured for this team.'));
        }

        $result = $service->syncAll();

        return back()->with('success', __('app.WordPress sync completed: :pulled pulled, :pushed pushed.', [
            'pulled' => $result['pulled'],
            'pushed' => $result['pushed'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Post $post, array $data): void
    {
        $post->post_type = $data['post_type'];
        $post->post_title = $data['post_title'] ?? null;
        $post->post_content = $data['post_content'] ?? null;
        $post->post_excerpt = $data['post_excerpt'] ?? null;
        $post->post_status = $data['post_status'];
        $post->post_parent = $data['post_parent'] ?? 0;
        $post->menu_order = $data['menu_order'] ?? 0;
        $post->post_name = ! empty($data['post_name'])
            ? Str::slug($data['post_name'])
            : Str::slug((string) ($data['post_title'] ?? ''));
        $post->save();

        if (isset($data['meta']) && is_array($data['meta']))
        {
            foreach ($data['meta'] as $key => $value)
            {
                $post->setMeta((string) $key, is_array($value) ? json_encode($value) : $value);
            }
        }

        $termIds = collect($data['terms'] ?? [])->map(fn ($id) => (int) $id)->all();
        $validTermTaxonomyIds = TermTaxonomy::query()->whereIn('id', $termIds)->pluck('id')->all();
        $syncData = [];
        foreach ($validTermTaxonomyIds as $termTaxonomyId)
        {
            $syncData[$termTaxonomyId] = ['team_id' => $post->team_id];
        }
        $post->termTaxonomies()->sync($syncData);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PostType>  $postTypes
     */
    private function resolveCurrentType(?string $type, $postTypes): ?PostType
    {
        if ($type !== null && $type !== '')
        {
            $match = $postTypes->firstWhere('name', $type);
            if ($match)
            {
                return $match;
            }
        }

        return $postTypes->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, TermTaxonomy>
     */
    private function termsForType(?PostType $type)
    {
        if (! $type || empty($type->taxonomies))
        {
            return collect();
        }

        return TermTaxonomy::query()
            ->with('term')
            ->whereIn('taxonomy', $type->taxonomies)
            ->get();
    }
}
