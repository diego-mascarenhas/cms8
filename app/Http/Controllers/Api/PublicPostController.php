<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\TermTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Anonymous read-only CMS API resolved by team slug. Only published posts are exposed,
 * and only for teams that opted in via the `cms_public_enabled` setting.
 */
class PublicPostController extends Controller
{
    public function index(Request $request, string $teamSlug): JsonResponse
    {
        $team = $this->resolveTeam($teamSlug);
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => 'Site not found'], 404);
        }

        $query = Post::withoutGlobalScope('team')
            ->where('posts.team_id', $team->id)
            ->where('post_status', Post::STATUS_PUBLISH);

        if ($request->filled('post_type'))
        {
            $query->where('post_type', $request->string('post_type'));
        }

        if ($request->filled('term'))
        {
            $termTaxonomyId = (int) $request->input('term');
            $query->whereHas('termTaxonomies', fn ($q) => $q->where('term_taxonomy.id', $termTaxonomyId));
        }

        $query->orderBy('menu_order')->orderByDesc('post_date');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $posts = $query->with(['meta', 'termTaxonomies.term'])->paginate($perPage);
        $posts->getCollection()->transform(fn (Post $post) => $this->transform($post));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function show(string $teamSlug, string $postType, string $slug): JsonResponse
    {
        $team = $this->resolveTeam($teamSlug);
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => 'Site not found'], 404);
        }

        $post = Post::withoutGlobalScope('team')
            ->where('posts.team_id', $team->id)
            ->where('post_status', Post::STATUS_PUBLISH)
            ->where('post_type', $postType)
            ->where('post_name', $slug)
            ->with(['meta', 'termTaxonomies.term'])
            ->first();

        if (! $post)
        {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->transform($post)]);
    }

    private function resolveTeam(string $slug): ?Team
    {
        $requested = Str::slug($slug);
        if ($requested === '')
        {
            return null;
        }

        $enabledTeamIds = TeamSetting::query()
            ->where('key', 'cms_public_enabled')
            ->get()
            ->filter(fn (TeamSetting $row) => filter_var($row->value, FILTER_VALIDATE_BOOLEAN))
            ->pluck('team_id')
            ->all();

        if ($enabledTeamIds === [])
        {
            return null;
        }

        $matches = Team::query()
            ->whereIn('id', $enabledTeamIds)
            ->get()
            ->filter(fn (Team $team) => Str::slug((string) $team->name) === $requested)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Post $post): array
    {
        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'slug' => $post->post_name,
            'title' => ['rendered' => $post->post_title],
            'content' => ['rendered' => $post->post_content],
            'excerpt' => ['rendered' => $post->post_excerpt],
            'parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
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
