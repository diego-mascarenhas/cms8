<?php

namespace App\Http\Controllers\Cms;

use App\DataTables\PostDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePostRequest;
use App\Http\Requests\Cms\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostType;
use App\Models\Term;
use App\Models\TermTaxonomy;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index(PostDataTable $dataTable, Request $request, ?string $type = null)
    {
        $this->authorize('viewAny', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->orderBy('label')->get();
        $currentType = $this->resolveCurrentType($type, $postTypes);
        $wordpressSyncEnabled = WordPressContentSyncService::make($request->user()->currentTeam)->isConfigured();

        return $dataTable
            ->forPostType($currentType?->name)
            ->render('cms.posts.index', compact('postTypes', 'currentType', 'wordpressSyncEnabled'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->get();
        $currentType = $this->resolveCurrentType($request->query('type'), $postTypes);

        return view('cms.posts.form', array_merge(
            [
                'post' => null,
                'postTypes' => $postTypes,
                'currentType' => $currentType,
                'listingUrl' => $this->listingUrl($currentType?->name),
            ],
            $this->formTaxonomyState($currentType),
        ));
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

        return view('cms.posts.form', array_merge(
            [
                'post' => $post,
                'postTypes' => $postTypes,
                'currentType' => $currentType,
                'listingUrl' => $this->listingUrl($post->post_type),
            ],
            $this->formTaxonomyState($currentType, $post),
        ));
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

        if (! $service->isConfigured())
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
        DB::transaction(function () use ($post, $data)
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

            $termIds = $this->resolveTermTaxonomyIds($post->team_id, $data);
            $validTermTaxonomyIds = TermTaxonomy::query()->whereIn('id', $termIds)->pluck('id')->all();
            $syncData = [];
            foreach ($validTermTaxonomyIds as $termTaxonomyId)
            {
                $syncData[$termTaxonomyId] = ['team_id' => $post->team_id];
            }
            $post->termTaxonomies()->sync($syncData);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function resolveTermTaxonomyIds(int $teamId, array $data): array
    {
        $termIds = collect($data['terms'] ?? [])
            ->merge($data['category_terms'] ?? [])
            ->merge($data['tag_terms'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        $newCategoryName = trim((string) ($data['new_category']['name'] ?? ''));
        if ($newCategoryName !== '')
        {
            $termIds[] = $this->ensureTermTaxonomy(
                $teamId,
                $newCategoryName,
                TermTaxonomy::TAXONOMY_CATEGORY,
                (int) ($data['new_category']['parent'] ?? 0),
            );
        }

        $newTags = trim((string) ($data['new_tags'] ?? ''));
        if ($newTags !== '')
        {
            foreach (array_filter(array_map('trim', explode(',', $newTags))) as $tagName)
            {
                $termIds[] = $this->ensureTermTaxonomy($teamId, $tagName, TermTaxonomy::TAXONOMY_TAG);
            }
        }

        return array_values(array_unique($termIds));
    }

    private function ensureTermTaxonomy(int $teamId, string $name, string $taxonomy, int $parentTaxonomyId = 0): int
    {
        $slug = Str::slug($name) ?: Str::random(8);

        $term = Term::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $teamId, 'slug' => $slug],
            ['name' => $name],
        );

        if ($term->name !== $name)
        {
            $term->name = $name;
            $term->save();
        }

        $termTaxonomy = TermTaxonomy::withoutGlobalScopes()->firstOrCreate(
            ['term_id' => $term->id, 'taxonomy' => $taxonomy],
            ['team_id' => $teamId, 'parent' => $parentTaxonomyId],
        );

        if ($parentTaxonomyId > 0 && (int) $termTaxonomy->parent !== $parentTaxonomyId)
        {
            $termTaxonomy->parent = $parentTaxonomyId;
            $termTaxonomy->save();
        }

        return $termTaxonomy->id;
    }

    /**
     * @return array{
     *     supportsCategory: bool,
     *     supportsTags: bool,
     *     categories: \Illuminate\Support\Collection<int, TermTaxonomy>,
     *     tags: \Illuminate\Support\Collection<int, TermTaxonomy>,
     *     selectedCategoryIds: array<int, int>,
     *     selectedTagIds: array<int, int>
     * }
     */
    private function formTaxonomyState(?PostType $type, ?Post $post = null): array
    {
        $taxonomies = $type?->taxonomies ?? [];
        $supportsCategory = in_array(TermTaxonomy::TAXONOMY_CATEGORY, $taxonomies, true);
        $supportsTags = in_array(TermTaxonomy::TAXONOMY_TAG, $taxonomies, true);

        $selectedIds = $post
            ? $post->termTaxonomies()->pluck('term_taxonomy.id')->all()
            : [];

        $categories = $supportsCategory
            ? TermTaxonomy::query()
                ->with('term')
                ->taxonomy(TermTaxonomy::TAXONOMY_CATEGORY)
                ->orderBy('parent')
                ->orderBy('id')
                ->get()
            : collect();

        $tags = $supportsTags
            ? TermTaxonomy::query()
                ->with('term')
                ->taxonomy(TermTaxonomy::TAXONOMY_TAG)
                ->orderBy('id')
                ->get()
            : collect();

        return [
            'supportsCategory' => $supportsCategory,
            'supportsTags' => $supportsTags,
            'categories' => $categories,
            'tags' => $tags,
            'selectedCategoryIds' => $categories->whereIn('id', $selectedIds)->pluck('id')->all(),
            'selectedTagIds' => $tags->whereIn('id', $selectedIds)->pluck('id')->all(),
        ];
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
}
