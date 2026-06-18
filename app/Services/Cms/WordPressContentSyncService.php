<?php

namespace App\Services\Cms;

use App\Models\Post;
use App\Models\Team;
use App\Models\Term;
use App\Models\TermTaxonomy;
use App\Services\WordPressService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Bidirectional content sync between Humano CMS posts and WordPress.
 *
 * Strategy: last-write-wins by comparing WordPress `modified_gmt` against the value Humano
 * last stored ({@see Post::$wp_modified_gmt}) and the local edit time ({@see Post::$post_modified_gmt}).
 * A static suppression flag prevents the save-triggered push from firing while we apply
 * incoming WordPress changes, and timestamp comparison breaks the echo loop on the way back.
 */
class WordPressContentSyncService
{
    /** WordPress post types Humano mirrors as content. */
    public const SYNCED_TYPES = ['post', 'page'];

    /** Post types whose save triggers an automatic push (content + media). */
    public const PUSHABLE_TYPES = ['post', 'page', 'attachment'];

    private static bool $suppressPush = false;

    public function __construct(
        private readonly Team $team,
        private readonly WordPressService $wp,
    ) {}

    public static function make(Team $team): self
    {
        return new self($team, new WordPressService($team));
    }

    public static function isPushSuppressed(): bool
    {
        return self::$suppressPush;
    }

    /**
     * Run the given callback with push-on-save suppressed (used while applying WP changes).
     */
    public static function withoutPush(callable $callback): mixed
    {
        $previous = self::$suppressPush;
        self::$suppressPush = true;

        try
        {
            return $callback();
        } finally
        {
            self::$suppressPush = $previous;
        }
    }

    /**
     * Whether WordPress credentials are present (enough for a manual sync).
     */
    public function isConfigured(): bool
    {
        return $this->wp->isConfigured();
    }

    /**
     * Whether automatic real-time sync is enabled (credentials + opt-in flag).
     */
    public function isEnabled(): bool
    {
        return $this->wp->isConfigured()
            && filter_var($this->team->getSetting('wordpress_cms_sync_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Full reconcile: pull everything from WordPress, then push local posts not yet linked.
     *
     * @return array{pulled: int, pushed: int}
     */
    public function syncAll(): array
    {
        $this->syncAllTerms();

        $pulled = 0;
        foreach (self::SYNCED_TYPES as $type)
        {
            foreach ($this->wp->getAllContent($type) as $wpItem)
            {
                if ($this->pullItem($wpItem, $type))
                {
                    $pulled++;
                }
            }
        }

        // Media library (attachments).
        foreach ($this->wp->getAllMedia() as $mediaItem)
        {
            if ($this->pullMediaItem($mediaItem))
            {
                $pulled++;
            }
        }

        $pushed = 0;
        $unlinked = Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->whereIn('post_type', self::SYNCED_TYPES)
            ->whereNull('wp_id')
            ->get();

        foreach ($unlinked as $post)
        {
            if ($this->pushPost($post))
            {
                $pushed++;
            }
        }

        $linked = Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->whereIn('post_type', self::SYNCED_TYPES)
            ->whereNotNull('wp_id')
            ->get();

        foreach ($linked as $post)
        {
            if ($this->localWins($post) && $this->pushPost($post))
            {
                $pushed++;
            }
        }

        $unlinkedAttachments = Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('post_type', 'attachment')
            ->whereNull('wp_id')
            ->get();

        foreach ($unlinkedAttachments as $attachment)
        {
            if ($this->pushAttachment($attachment))
            {
                $pushed++;
            }
        }

        return ['pulled' => $pulled, 'pushed' => $pushed];
    }

    /**
     * Pull a single WordPress item (by id + type), e.g. from a webhook.
     */
    public function pullById(int $wpId, string $type): bool
    {
        if ($type === 'attachment')
        {
            $mediaItem = $this->wp->getMediaItem($wpId);

            return is_array($mediaItem) ? $this->pullMediaItem($mediaItem) : false;
        }

        $type = $this->normalizeType($type);
        $wpItem = $this->wp->getContent($type, $wpId);

        if (! is_array($wpItem))
        {
            return false;
        }

        return $this->pullItem($wpItem, $type);
    }

    /**
     * Upsert one WordPress media item into the local posts table as an attachment.
     *
     * @param  array<string, mixed>  $wp
     */
    public function pullMediaItem(array $wp): bool
    {
        $wpId = (int) ($wp['id'] ?? 0);
        if ($wpId === 0)
        {
            return false;
        }

        $wpModifiedGmt = isset($wp['modified_gmt']) ? Carbon::parse($wp['modified_gmt']) : now();

        $existing = Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('wp_id', $wpId)
            ->where('post_type', 'attachment')
            ->first();

        if ($existing && ! $this->wordPressWins($existing, $wpModifiedGmt))
        {
            return false;
        }

        $sourceUrl = (string) ($wp['source_url'] ?? ($wp['guid']['rendered'] ?? ''));
        $thumbUrl = (string) ($wp['media_details']['sizes']['thumbnail']['source_url'] ?? $sourceUrl);

        $attributes = [
            'team_id' => $this->team->id,
            'wp_id' => $wpId,
            'post_type' => 'attachment',
            'post_title' => $this->rendered($wp, 'title') ?: (string) ($wp['slug'] ?? 'media'),
            'post_status' => (string) ($wp['status'] ?? 'inherit'),
            'post_name' => (string) ($wp['slug'] ?? ''),
            'post_mime_type' => (string) ($wp['mime_type'] ?? ''),
            'guid' => $sourceUrl,
            'wp_modified_gmt' => $wpModifiedGmt,
            'synced_at' => now(),
        ];

        self::withoutPush(function () use ($existing, $attributes, $sourceUrl, $thumbUrl)
        {
            $post = $existing ?: new Post;
            $post->fill($attributes)->save();
            $post->setMeta('_wp_source_url', $sourceUrl);
            $post->setMeta('_humano_thumb_url', $thumbUrl);
        });

        return true;
    }

    /**
     * Upload a locally-stored attachment to the WordPress media library.
     */
    public function pushAttachment(Post $post): bool
    {
        if ($post->post_type !== 'attachment' || $post->wp_id)
        {
            return false;
        }

        $relativePath = $post->getMeta('_humano_file_path');
        if (! $relativePath || ! Storage::disk('public')->exists($relativePath))
        {
            return false;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);
        $filename = basename($relativePath);
        $mime = $post->post_mime_type ?: (Storage::disk('public')->mimeType($relativePath) ?: 'application/octet-stream');

        $response = $this->wp->uploadMedia($absolutePath, $filename, $mime);

        if (! is_array($response) || ! isset($response['id']))
        {
            Log::warning('WordPress attachment push failed', ['post_id' => $post->id, 'team_id' => $post->team_id]);

            return false;
        }

        self::withoutPush(function () use ($post, $response)
        {
            $post->forceFill([
                'wp_id' => (int) $response['id'],
                'guid' => $response['source_url'] ?? $post->guid,
                'wp_modified_gmt' => isset($response['modified_gmt']) ? Carbon::parse($response['modified_gmt']) : now(),
                'synced_at' => now(),
            ])->saveQuietly();
            $post->setMeta('_wp_source_url', $response['source_url'] ?? '');
        });

        return true;
    }

    /**
     * Upsert one WordPress payload into the local posts table, honoring last-write-wins.
     *
     * @param  array<string, mixed>  $wp
     */
    public function pullItem(array $wp, string $type): bool
    {
        $type = $this->normalizeType($type);
        $wpId = (int) ($wp['id'] ?? 0);
        if ($wpId === 0)
        {
            return false;
        }

        $wpModifiedGmt = isset($wp['modified_gmt']) ? Carbon::parse($wp['modified_gmt']) : now();

        $existing = Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('wp_id', $wpId)
            ->where('post_type', $type)
            ->first();

        if ($existing && ! $this->wordPressWins($existing, $wpModifiedGmt))
        {
            if ($type === 'post')
            {
                $this->syncPostTerms($existing, $wp);
            }

            return false;
        }

        $attributes = [
            'team_id' => $this->team->id,
            'wp_id' => $wpId,
            'post_type' => $type,
            'post_title' => $this->rendered($wp, 'title'),
            'post_content' => $this->rendered($wp, 'content'),
            'post_excerpt' => $this->rendered($wp, 'excerpt'),
            'post_status' => (string) ($wp['status'] ?? Post::STATUS_PUBLISH),
            'post_name' => (string) ($wp['slug'] ?? ''),
            'menu_order' => (int) ($wp['menu_order'] ?? 0),
            'post_parent' => $this->localParentId((int) ($wp['parent'] ?? 0), $type),
            'wp_modified_gmt' => $wpModifiedGmt,
            'synced_at' => now(),
        ];

        $post = $existing;

        self::withoutPush(function () use ($existing, $attributes, &$post)
        {
            if ($existing)
            {
                $existing->fill($attributes)->save();
                $post = $existing;
            } else
            {
                $post = Post::withoutGlobalScopes()->create($attributes);
            }
        });

        if ($post && $type === 'post')
        {
            $this->syncPostTerms($post, $wp);
        }

        return true;
    }

    /**
     * Push a local post to WordPress (create when unlinked, update otherwise).
     */
    public function pushPost(Post $post): bool
    {
        if (! in_array($post->post_type, self::SYNCED_TYPES, true))
        {
            return false;
        }

        $body = [
            'title' => (string) $post->post_title,
            'content' => (string) $post->post_content,
            'excerpt' => (string) $post->post_excerpt,
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'menu_order' => $post->menu_order,
        ];

        if ($post->post_type === 'page' && $post->post_parent)
        {
            $parentWpId = Post::withoutGlobalScopes()
                ->where('team_id', $post->team_id)
                ->where('id', $post->post_parent)
                ->value('wp_id');
            if ($parentWpId)
            {
                $body['parent'] = (int) $parentWpId;
            }
        }

        if ($post->post_type === 'post')
        {
            $post->loadMissing('termTaxonomies.term');
            $categoryWpIds = $this->wordPressTermIdsForPost($post, TermTaxonomy::TAXONOMY_CATEGORY);
            $tagWpIds = $this->wordPressTermIdsForPost($post, TermTaxonomy::TAXONOMY_TAG);

            if ($categoryWpIds !== [])
            {
                $body['categories'] = $categoryWpIds;
            }

            if ($tagWpIds !== [])
            {
                $body['tags'] = $tagWpIds;
            }
        }

        $response = $post->wp_id
            ? $this->wp->updateContent($post->post_type, (int) $post->wp_id, $body)
            : $this->wp->createContent($post->post_type, $body);

        if (! is_array($response) || ! isset($response['id']))
        {
            Log::warning('WordPress push failed', ['post_id' => $post->id, 'team_id' => $post->team_id]);

            return false;
        }

        self::withoutPush(function () use ($post, $response)
        {
            $post->forceFill([
                'wp_id' => (int) $response['id'],
                'wp_modified_gmt' => isset($response['modified_gmt']) ? Carbon::parse($response['modified_gmt']) : now(),
                'synced_at' => now(),
            ])->saveQuietly();
        });

        return true;
    }

    /**
     * WordPress wins only when its change is strictly newer than what we last stored
     * and not older than the local edit time.
     */
    private function wordPressWins(Post $post, Carbon $wpModifiedGmt): bool
    {
        $lastSeen = $post->wp_modified_gmt;
        if ($lastSeen && $wpModifiedGmt->lessThanOrEqualTo($lastSeen))
        {
            return false;
        }

        $localModified = $post->post_modified_gmt;
        if ($localModified && $wpModifiedGmt->lessThan($localModified))
        {
            return false;
        }

        return true;
    }

    /**
     * Local Humano edit should be pushed when it is newer than the last known WordPress state.
     */
    private function localWins(Post $post): bool
    {
        $localModified = $post->post_modified_gmt;
        if (! $localModified)
        {
            return false;
        }

        $lastSeen = $post->wp_modified_gmt;
        if (! $lastSeen)
        {
            return true;
        }

        return $localModified->greaterThan($lastSeen);
    }

    private function localParentId(int $wpParentId, string $type): int
    {
        if ($wpParentId === 0)
        {
            return 0;
        }

        return (int) Post::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('wp_id', $wpParentId)
            ->where('post_type', $type)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $wp
     */
    private function rendered(array $wp, string $key): string
    {
        $value = $wp[$key] ?? null;

        if (is_array($value))
        {
            return (string) ($value['raw'] ?? $value['rendered'] ?? '');
        }

        return (string) ($value ?? '');
    }

    private function normalizeType(string $type): string
    {
        return $type === 'page' ? 'page' : 'post';
    }

    /**
     * Pull every WordPress category and tag into local terms tables.
     */
    public function syncAllTerms(): void
    {
        $categories = $this->wp->getAllCategories();
        usort($categories, fn (array $a, array $b) => ((int) ($a['parent'] ?? 0)) <=> ((int) ($b['parent'] ?? 0)));

        foreach ($categories as $category)
        {
            $this->pullTermItem($category, TermTaxonomy::TAXONOMY_CATEGORY);
        }

        foreach ($this->wp->getAllTags() as $tag)
        {
            $this->pullTermItem($tag, TermTaxonomy::TAXONOMY_TAG);
        }
    }

    /**
     * Upsert one WordPress taxonomy item (category or tag).
     *
     * @param  array<string, mixed>  $wp
     */
    public function pullTermItem(array $wp, string $taxonomy): ?int
    {
        $wpId = (int) ($wp['id'] ?? 0);
        if ($wpId === 0)
        {
            return null;
        }

        $term = Term::withoutGlobalScopes()->updateOrCreate(
            ['team_id' => $this->team->id, 'wp_id' => $wpId],
            [
                'name' => (string) ($wp['name'] ?? ''),
                'slug' => (string) ($wp['slug'] ?? ''),
            ],
        );

        $parent = 0;
        $wpParentId = (int) ($wp['parent'] ?? 0);
        if ($wpParentId > 0)
        {
            $parent = $this->localTermTaxonomyIdByWpTermId($wpParentId, $taxonomy) ?? 0;
        }

        $termTaxonomy = TermTaxonomy::withoutGlobalScopes()->updateOrCreate(
            ['term_id' => $term->id, 'taxonomy' => $taxonomy],
            [
                'team_id' => $this->team->id,
                'description' => (string) ($wp['description'] ?? ''),
                'parent' => $parent,
                'count' => (int) ($wp['count'] ?? 0),
            ],
        );

        return $termTaxonomy->id;
    }

    /**
     * Attach categories and tags from a WordPress post payload to the local post.
     *
     * @param  array<string, mixed>  $wp
     */
    private function syncPostTerms(Post $post, array $wp): void
    {
        $termTaxonomyIds = [];

        foreach ((array) ($wp['categories'] ?? []) as $wpCategoryId)
        {
            $termTaxonomyId = $this->ensureTermFromWpId((int) $wpCategoryId, TermTaxonomy::TAXONOMY_CATEGORY);
            if ($termTaxonomyId)
            {
                $termTaxonomyIds[] = $termTaxonomyId;
            }
        }

        foreach ((array) ($wp['tags'] ?? []) as $wpTagId)
        {
            $termTaxonomyId = $this->ensureTermFromWpId((int) $wpTagId, TermTaxonomy::TAXONOMY_TAG);
            if ($termTaxonomyId)
            {
                $termTaxonomyIds[] = $termTaxonomyId;
            }
        }

        $syncData = [];
        foreach (array_unique($termTaxonomyIds) as $termTaxonomyId)
        {
            $syncData[$termTaxonomyId] = ['team_id' => $post->team_id];
        }

        self::withoutPush(fn () => $post->termTaxonomies()->sync($syncData));
    }

    private function ensureTermFromWpId(int $wpTermId, string $taxonomy): ?int
    {
        if ($wpTermId === 0)
        {
            return null;
        }

        $existingId = $this->localTermTaxonomyIdByWpTermId($wpTermId, $taxonomy);
        if ($existingId)
        {
            return $existingId;
        }

        $wpItem = $taxonomy === TermTaxonomy::TAXONOMY_CATEGORY
            ? $this->wp->getCategory($wpTermId)
            : $this->wp->getTag($wpTermId);

        if (! is_array($wpItem))
        {
            return null;
        }

        return $this->pullTermItem($wpItem, $taxonomy);
    }

    private function localTermTaxonomyIdByWpTermId(int $wpTermId, string $taxonomy): ?int
    {
        $termId = Term::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('wp_id', $wpTermId)
            ->value('id');

        if (! $termId)
        {
            return null;
        }

        return TermTaxonomy::withoutGlobalScopes()
            ->where('team_id', $this->team->id)
            ->where('term_id', $termId)
            ->where('taxonomy', $taxonomy)
            ->value('id');
    }

    /**
     * @return array<int, int>
     */
    private function wordPressTermIdsForPost(Post $post, string $taxonomy): array
    {
        return $post->termTaxonomies
            ->where('taxonomy', $taxonomy)
            ->map(fn (TermTaxonomy $termTaxonomy) => (int) $termTaxonomy->term?->wp_id)
            ->filter(fn (int $wpId) => $wpId > 0)
            ->values()
            ->all();
    }
}
