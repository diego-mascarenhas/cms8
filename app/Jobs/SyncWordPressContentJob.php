<?php

namespace App\Jobs;

use App\Models\Team;
use App\Models\WordPressSyncPage;
use App\Models\WordPressSyncPost;
use App\Models\WordPressSyncProduct;
use App\Services\WordPressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncWordPressContentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Team $team,
    ) {}

    public function handle(): void
    {
        $wp = new WordPressService($this->team);

        if (! $wp->isConfigured())
        {
            return;
        }

        $teamId = $this->team->id;
        $now = Carbon::now()->toDateTimeString();

        // Sync pages (paginate until empty)
        $page = 1;
        $wpPageIds = [];
        do
        {
            $pages = $wp->getPages($page, 100);
            if (empty($pages))
            {
                break;
            }
            $rows = [];
            foreach ($pages as $item)
            {
                $wpId = (int) ($item['id'] ?? 0);
                $wpPageIds[] = $wpId;
                $rows[] = [
                    'team_id' => $teamId,
                    'wp_id' => $wpId,
                    'title' => strip_tags($item['title']['rendered'] ?? ''),
                    'content' => $item['content']['rendered'] ?? null,
                    'status' => $item['status'] ?? null,
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }
            if (! empty($rows))
            {
                WordPressSyncPage::withoutGlobalScope('team')->upsert(
                    $rows,
                    ['team_id', 'wp_id'],
                    ['title', 'content', 'status', 'synced_at', 'updated_at'],
                );
            }
            $page++;
        } while (count($pages) === 100);

        // Remove pages that no longer exist in WP
        if (! empty($wpPageIds))
        {
            WordPressSyncPage::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereNotIn('wp_id', $wpPageIds)
                ->delete();
        } else
        {
            WordPressSyncPage::withoutGlobalScope('team')->where('team_id', $teamId)->delete();
        }

        // Sync posts
        $page = 1;
        $wpPostIds = [];
        do
        {
            $posts = $wp->getPosts($page, 100);
            if (empty($posts))
            {
                break;
            }
            $rows = [];
            foreach ($posts as $item)
            {
                $wpId = (int) ($item['id'] ?? 0);
                $wpPostIds[] = $wpId;
                $rows[] = [
                    'team_id' => $teamId,
                    'wp_id' => $wpId,
                    'title' => strip_tags($item['title']['rendered'] ?? ''),
                    'content' => $item['content']['rendered'] ?? null,
                    'status' => $item['status'] ?? null,
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }
            if (! empty($rows))
            {
                WordPressSyncPost::withoutGlobalScope('team')->upsert(
                    $rows,
                    ['team_id', 'wp_id'],
                    ['title', 'content', 'status', 'synced_at', 'updated_at'],
                );
            }
            $page++;
        } while (count($posts) === 100);

        if (! empty($wpPostIds))
        {
            WordPressSyncPost::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereNotIn('wp_id', $wpPostIds)
                ->delete();
        } else
        {
            WordPressSyncPost::withoutGlobalScope('team')->where('team_id', $teamId)->delete();
        }

        // Sync products (WooCommerce)
        $page = 1;
        $wpProductIds = [];
        do
        {
            $products = $wp->getProducts($page, 100);
            if (empty($products))
            {
                break;
            }
            $rows = [];
            foreach ($products as $item)
            {
                $wpId = (int) ($item['id'] ?? 0);
                $wpProductIds[] = $wpId;
                $rows[] = [
                    'team_id' => $teamId,
                    'wp_id' => $wpId,
                    'name' => strip_tags($item['name'] ?? ''),
                    'description' => $item['description'] ?? null,
                    'price' => $item['price'] ?? null,
                    'currency' => $item['currency'] ?? null,
                    'status' => $item['status'] ?? null,
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }
            if (! empty($rows))
            {
                WordPressSyncProduct::withoutGlobalScope('team')->upsert(
                    $rows,
                    ['team_id', 'wp_id'],
                    ['name', 'description', 'price', 'currency', 'status', 'synced_at', 'updated_at'],
                );
            }
            $page++;
        } while (count($products) === 100);

        if (! empty($wpProductIds))
        {
            WordPressSyncProduct::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereNotIn('wp_id', $wpProductIds)
                ->delete();
        } else
        {
            WordPressSyncProduct::withoutGlobalScope('team')->where('team_id', $teamId)->delete();
        }
    }
}
