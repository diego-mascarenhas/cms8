<?php

namespace App\Services\Cms;

use App\Models\Post;
use App\Models\Team;
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

        self::withoutPush(function () use ($existing, $attributes)
        {
            if ($existing)
            {
                $existing->fill($attributes)->save();
            } else
            {
                Post::withoutGlobalScopes()->create($attributes);
            }
        });

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
}
