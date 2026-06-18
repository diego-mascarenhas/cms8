<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a single Humano CMS post to WordPress when the team has sync enabled.
 * Dispatched from the Post saved hook (outside of incoming-sync suppression).
 */
class PushPostToWordPressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        $post = Post::withoutGlobalScopes()->find($this->postId);
        if (! $post || ! $post->team)
        {
            return;
        }

        $service = WordPressContentSyncService::make($post->team);
        if (! $service->isEnabled())
        {
            return;
        }

        $service->pushPost($post);
    }
}
