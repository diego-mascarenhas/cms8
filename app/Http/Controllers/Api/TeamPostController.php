<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Models\TermTaxonomy;
use App\Support\TeamPostsApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Team-token authenticated CMS API. Response shape mirrors WordPress wp/v2 where practical.
 */
class TeamPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $ttlSeconds = (int) config('cache.team_posts_index_ttl', 0);
        if ($ttlSeconds === 0)
        {
            return $this->executeIndex($request, $team);
        }

        $generation = TeamPostsApiCache::currentGeneration((int) $team->id);
        $cacheKey = TeamPostsApiCache::indexCacheKey((int) $team->id, $generation, $request);

        $resolver = fn () => $this->executeIndex($request, $team)->getData(true);

        $payload = $ttlSeconds < 0
            ? Cache::rememberForever($cacheKey, $resolver)
            : Cache::remember($cacheKey, $ttlSeconds, $resolver);

        return response()->json($payload);
    }

    private function executeIndex(Request $request, Team $team): JsonResponse
    {
        $query = Post::withoutGlobalScope('team')->where('posts.team_id', $team->id);

        if ($request->filled('post_type'))
        {
            $query->where('post_type', $request->string('post_type'));
        }

        if ($request->filled('post_status'))
        {
            $query->where('post_status', $request->string('post_status'));
        } else
        {
            $query->where('post_status', Post::STATUS_PUBLISH);
        }

        if ($request->filled('slug'))
        {
            $query->where('post_name', $request->string('slug'));
        }

        if ($request->filled('parent'))
        {
            $query->where('post_parent', (int) $request->input('parent'));
        }

        if ($request->filled('term'))
        {
            $termTaxonomyId = (int) $request->input('term');
            $query->whereHas('termTaxonomies', fn ($q) => $q->where('term_taxonomy.id', $termTaxonomyId));
        }

        if ($request->filled('search'))
        {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search)
            {
                $q->where('post_title', 'like', "%{$search}%")
                    ->orWhere('post_content', 'like', "%{$search}%");
            });
        }

        $query->orderBy('menu_order')->orderByDesc('post_date');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $posts = $query->with(['meta', 'termTaxonomies.term', 'author:id,name'])->paginate($perPage);

        $posts->getCollection()->transform(fn (Post $post) => $this->transform($post));

        return response()->json([
            'success' => true,
            'data' => $posts,
            'team' => ['id' => $team->id, 'name' => $team->name],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $post = Post::withoutGlobalScope('team')
            ->where('posts.team_id', $team->id)
            ->with(['meta', 'termTaxonomies.term', 'author:id,name'])
            ->find($id);

        if (! $post)
        {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->transform($post)]);
    }

    public function store(Request $request): JsonResponse
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $validated = $this->validatePayload($request, $team);
        $post = $this->persist($team, new Post, $validated);

        return response()->json(['success' => true, 'data' => $this->transform($post)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $post = Post::withoutGlobalScope('team')->where('posts.team_id', $team->id)->find($id);
        if (! $post)
        {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $validated = $this->validatePayload($request, $team);
        $post = $this->persist($team, $post, $validated);

        return response()->json(['success' => true, 'data' => $this->transform($post)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $post = Post::withoutGlobalScope('team')->where('posts.team_id', $team->id)->find($id);
        if (! $post)
        {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $post->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Team $team): array
    {
        return $request->validate([
            'post_type' => ['required', 'string', 'max:50',
                \Illuminate\Validation\Rule::exists('post_types', 'name')->where(fn ($q) => $q->where('team_id', $team->id))],
            'post_title' => ['nullable', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255'],
            'post_content' => ['nullable', 'string'],
            'post_excerpt' => ['nullable', 'string'],
            'post_status' => ['nullable', \Illuminate\Validation\Rule::in([
                Post::STATUS_PUBLISH, Post::STATUS_DRAFT, Post::STATUS_PENDING,
                Post::STATUS_FUTURE, Post::STATUS_PRIVATE,
            ])],
            'post_parent' => ['nullable', 'integer', 'min:0'],
            'menu_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['integer'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Team $team, Post $post, array $data): Post
    {
        $post->team_id = $team->id;
        $post->post_type = $data['post_type'];
        $post->post_title = $data['post_title'] ?? $post->post_title;
        $post->post_content = $data['post_content'] ?? $post->post_content;
        $post->post_excerpt = $data['post_excerpt'] ?? $post->post_excerpt;
        $post->post_status = $data['post_status'] ?? ($post->post_status ?: Post::STATUS_PUBLISH);
        $post->post_parent = $data['post_parent'] ?? $post->post_parent ?? 0;
        $post->menu_order = $data['menu_order'] ?? $post->menu_order ?? 0;
        $post->post_name = $data['post_name'] ?? ($post->post_name ?: Str::slug((string) ($data['post_title'] ?? '')));
        $post->post_author = $post->post_author ?? null;
        $post->save();

        if (array_key_exists('meta', $data) && is_array($data['meta']))
        {
            foreach ($data['meta'] as $key => $value)
            {
                $post->setMeta((string) $key, is_array($value) ? json_encode($value) : $value);
            }
        }

        if (array_key_exists('terms', $data) && is_array($data['terms']))
        {
            $validTermTaxonomyIds = TermTaxonomy::withoutGlobalScope('team')
                ->where('term_taxonomy.team_id', $team->id)
                ->whereIn('id', $data['terms'])
                ->pluck('id')
                ->all();
            $syncData = [];
            foreach ($validTermTaxonomyIds as $termTaxonomyId)
            {
                $syncData[$termTaxonomyId] = ['team_id' => $team->id];
            }
            $post->termTaxonomies()->sync($syncData);
        }

        return $post->fresh(['meta', 'termTaxonomies.term', 'author:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Post $post): array
    {
        return [
            'id' => $post->id,
            'wp_id' => $post->wp_id,
            'post_type' => $post->post_type,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'title' => ['rendered' => $post->post_title],
            'content' => ['rendered' => $post->post_content],
            'excerpt' => ['rendered' => $post->post_excerpt],
            'parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            'date' => optional($post->post_date)->toIso8601String(),
            'modified' => optional($post->post_modified)->toIso8601String(),
            'meta' => $post->metaAsArray(),
            'terms' => $post->termTaxonomies->map(fn (TermTaxonomy $tt) => [
                'id' => $tt->id,
                'taxonomy' => $tt->taxonomy,
                'name' => $tt->term?->name,
                'slug' => $tt->term?->slug,
            ])->values(),
        ];
    }
}
