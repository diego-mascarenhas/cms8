<?php

namespace App\Http\Controllers\Cms;

use App\DataTables\PostDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePostRequest;
use App\Http\Requests\Cms\UpdatePostRequest;
use App\Models\Post;
use App\Models\PostType;
use App\Models\TermTaxonomy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index(PostDataTable $dataTable, Request $request)
    {
        $this->authorize('viewAny', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->orderBy('label')->get();
        $currentType = $this->resolveCurrentType($request, $postTypes);

        return $dataTable
            ->forPostType($currentType?->name)
            ->render('cms.posts.index', compact('postTypes', 'currentType'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        $postTypes = PostType::query()->orderBy('menu_order')->get();
        $currentType = $this->resolveCurrentType($request, $postTypes);
        $availableTerms = $this->termsForType($currentType);
        $selectedTermIds = [];

        return view('cms.posts.form', [
            'post' => null,
            'postTypes' => $postTypes,
            'currentType' => $currentType,
            'availableTerms' => $availableTerms,
            'selectedTermIds' => $selectedTermIds,
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $post = new Post;
        $this->persist($post, $request->validated());

        return redirect()
            ->route('cms.posts.index', ['post_type' => $post->post_type])
            ->with('success', __('app.Post created successfully.'));
    }

    public function show(Post $post): View
    {
        $this->authorize('view', $post);

        $post->load(['meta', 'termTaxonomies.term', 'author']);

        return view('cms.posts.show', compact('post'));
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
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->persist($post, $request->validated());

        return redirect()
            ->route('cms.posts.index', ['post_type' => $post->post_type])
            ->with('success', __('app.Post updated successfully.'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $type = $post->post_type;
        $post->delete();

        return redirect()
            ->route('cms.posts.index', ['post_type' => $type])
            ->with('success', __('app.Post deleted successfully.'));
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
    private function resolveCurrentType(Request $request, $postTypes): ?PostType
    {
        if ($request->filled('post_type'))
        {
            $match = $postTypes->firstWhere('name', $request->string('post_type'));
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
