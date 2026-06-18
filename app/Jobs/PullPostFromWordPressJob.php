<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Team;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pulls (or trashes) a single WordPress item into Humano, triggered by a WordPress webhook.
 */
class PullPostFromWordPressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $teamId,
        public int $wpId,
        public string $type,
        public string $action = 'updated',
    ) {}

    public function handle(): void
    {
        $team = Team::find($this->teamId);
        if (! $team)
        {
            return;
        }

        $service = WordPressContentSyncService::make($team);
        if (! $service->isEnabled())
        {
            return;
        }

        $normalizedType = $this->type === 'page' ? 'page' : 'post';

        if (in_array($this->action, ['deleted', 'trashed'], true))
        {
            WordPressContentSyncService::withoutPush(function () use ($team, $normalizedType)
            {
                Post::withoutGlobalScopes()
                    ->where('team_id', $team->id)
                    ->where('post_type', $normalizedType)
                    ->where('wp_id', $this->wpId)
                    ->get()
                    ->each
                    ->delete();
            });

            return;
        }

        $service->pullById($this->wpId, $normalizedType);
    }
}
