<?php

namespace App\Services;

use App\Models\Team;
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
}
