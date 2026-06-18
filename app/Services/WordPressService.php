<?php

namespace App\Services;

use App\Models\Team;
use App\Models\WordPressSyncPage;
use App\Models\WordPressSyncPost;
use App\Models\WordPressSyncProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressService
{
    public function __construct(
        protected Team $team,
    ) {}

    public function isConfigured(): bool
    {
        $url = $this->team->getSetting('wordpress_url');
        $user = $this->team->getSetting('wordpress_username');
        $password = $this->team->getSetting('wordpress_application_password');

        return ! empty($url) && ! empty($user) && ! empty($password);
    }

    /**
     * Last time any WordPress sync (pages, posts, products) ran for this team.
     */
    public function getLastSyncedAt(): ?\Carbon\Carbon
    {
        $teamId = $this->team->id;
        $pages = WordPressSyncPage::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');
        $posts = WordPressSyncPost::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');
        $products = WordPressSyncProduct::withoutGlobalScope('team')->where('team_id', $teamId)->max('synced_at');
        $max = collect([$pages, $posts, $products])->filter()->max();

        return $max ? \Carbon\Carbon::parse($max) : null;
    }

    protected function baseUrl(): string
    {
        return rtrim($this->team->getSetting('wordpress_url'), '/');
    }

    /**
     * Build Basic Auth header for Application Password (username:app_password).
     * Application passwords may contain spaces; WordPress accepts them without spaces.
     */
    protected function basicAuth(): string
    {
        $user = $this->team->getSetting('wordpress_username');
        $password = (string) $this->team->getSetting('wordpress_application_password');
        $password = str_replace(' ', '', $password);

        return 'Basic '.base64_encode($user.':'.$password);
    }

    protected function request(string $method, string $path, array $query = []): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $url = $this->baseUrl().'/wp-json/wp/v2'.$path;

        try
        {
            $response = Http::withHeaders([
                'Authorization' => $this->basicAuth(),
            ])->get($url, array_merge(['per_page' => 100], $query));

            if ($response->successful())
            {
                return $response->json();
            }

            Log::warning('WordPress API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e)
        {
            Log::error('WordPress API error', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    protected function getOne(string $path): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $url = $this->baseUrl().'/wp-json/wp/v2'.$path;

        try
        {
            $response = Http::withHeaders([
                'Authorization' => $this->basicAuth(),
            ])->get($url);

            if ($response->successful())
            {
                return $response->json();
            }

            Log::warning('WordPress API request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e)
        {
            Log::error('WordPress API error', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    protected function put(string $path, array $body): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $url = $this->baseUrl().'/wp-json/wp/v2'.$path;

        try
        {
            $response = Http::withHeaders([
                'Authorization' => $this->basicAuth(),
            ])->put($url, $body);

            if ($response->successful())
            {
                return $response->json();
            }

            Log::warning('WordPress API PUT failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e)
        {
            Log::error('WordPress API PUT error', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    protected function post(string $path, array $body): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $url = $this->baseUrl().'/wp-json/wp/v2'.$path;

        try
        {
            $response = Http::withHeaders([
                'Authorization' => $this->basicAuth(),
            ])->post($url, $body);

            if ($response->successful())
            {
                return $response->json();
            }

            Log::warning('WordPress API POST failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e)
        {
            Log::error('WordPress API POST error', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPosts(int $page = 1, int $perPage = 100): array
    {
        $data = $this->request('GET', '/posts', ['page' => $page, 'per_page' => $perPage]);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPages(int $page = 1, int $perPage = 100): array
    {
        $data = $this->request('GET', '/pages', ['page' => $page, 'per_page' => $perPage]);

        return is_array($data) ? $data : [];
    }

    public function getSiteUrl(): string
    {
        return $this->baseUrl();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPost(int $id): ?array
    {
        $data = $this->getOne('/posts/'.$id);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPage(int $id): ?array
    {
        $data = $this->getOne('/pages/'.$id);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array{title?: string, content?: string, excerpt?: string, status?: string}  $data
     * @return array<string, mixed>|null
     */
    public function updatePost(int $id, array $data): ?array
    {
        return $this->put('/posts/'.$id, $data);
    }

    /**
     * @param  array{title?: string, content?: string, status?: string}  $data
     * @return array<string, mixed>|null
     */
    public function updatePage(int $id, array $data): ?array
    {
        return $this->put('/pages/'.$id, $data);
    }

    /**
     * Create a post or page in WordPress.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function createContent(string $type, array $data): ?array
    {
        $endpoint = $type === 'page' ? '/pages' : '/posts';

        return $this->post($endpoint, $data);
    }

    /**
     * Update a post or page in WordPress.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateContent(string $type, int $id, array $data): ?array
    {
        $endpoint = $type === 'page' ? '/pages/'.$id : '/posts/'.$id;

        return $this->put($endpoint, $data);
    }

    /**
     * Fetch a single post or page by id and WordPress type.
     *
     * @return array<string, mixed>|null
     */
    public function getContent(string $type, int $id): ?array
    {
        return $type === 'page' ? $this->getPage($id) : $this->getPost($id);
    }

    /**
     * Iterate every post/page across all pages of results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllContent(string $type, int $perPage = 100): array
    {
        $all = [];
        $page = 1;

        do
        {
            $batch = $type === 'page'
                ? $this->getPages($page, $perPage)
                : $this->getPosts($page, $perPage);

            foreach ($batch as $item)
            {
                $all[] = $item;
            }
            $page++;
        } while (count($batch) === $perPage && $page <= 50);

        return $all;
    }

    /**
     * Fetch WooCommerce products via /wp-json/wc/v3/products.
     * Returns an empty array if WooCommerce is not installed or not accessible.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(int $page = 1, int $perPage = 100): array
    {
        if (! $this->isConfigured())
        {
            return [];
        }

        $url = $this->baseUrl().'/wp-json/wc/v3/products';

        try
        {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $this->basicAuth(),
            ])->get($url, ['page' => $page, 'per_page' => $perPage]);

            if ($response->successful())
            {
                $data = $response->json();

                return is_array($data) ? $data : [];
            }

            return [];
        } catch (\Throwable $e)
        {
            \Illuminate\Support\Facades\Log::warning('WordPress getProducts failed', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
