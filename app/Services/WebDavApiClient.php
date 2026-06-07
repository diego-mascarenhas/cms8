<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WebDavApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function createUser(string $email, string $name, ?string $davUsername = null, ?string $password = null): array
    {
        $response = $this->client()->post('/api/users', array_filter([
            'email' => $email,
            'name' => $name,
            'dav_username' => $davUsername,
            'password' => $password,
        ], fn ($value) => $value !== null && $value !== ''));

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function linkUser(string $email, string $password): array
    {
        $response = $this->client()->post('/api/users/link', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(string $email): array
    {
        $response = $this->client()->get('/api/users', [
            'email' => $email,
        ]);

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listContacts(string $email): array
    {
        $response = $this->client()->get('/api/contacts', [
            'email' => $email,
        ]);

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEvents(string $email): array
    {
        $response = $this->client()->get('/api/events', [
            'email' => $email,
        ]);

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTasks(string $email): array
    {
        $response = $this->client()->get('/api/tasks', [
            'email' => $email,
        ]);

        $response->throw();

        return (array) $response->json('data', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertContact(string $email, array $payload, ?string $uid = null): array
    {
        $url = $uid === null ? '/api/contacts' : '/api/contacts/'.$uid;
        $method = $uid === null ? 'post' : 'put';

        $response = $this->client()->{$method}($url.'?email='.urlencode($email), $payload);
        $response->throw();

        return (array) $response->json('data', []);
    }

    public function deleteContact(string $email, string $uid): void
    {
        $this->client()
            ->delete('/api/contacts/'.$uid.'?email='.urlencode($email))
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertEvent(string $email, array $payload, ?string $uid = null): array
    {
        $url = $uid === null ? '/api/events' : '/api/events/'.$uid;
        $method = $uid === null ? 'post' : 'put';

        $response = $this->client()->{$method}($url.'?email='.urlencode($email), $payload);
        $response->throw();

        return (array) $response->json('data', []);
    }

    public function deleteEvent(string $email, string $uid): void
    {
        $this->client()
            ->delete('/api/events/'.$uid.'?email='.urlencode($email))
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertTask(string $email, array $payload, ?string $uid = null): array
    {
        $url = $uid === null ? '/api/tasks' : '/api/tasks/'.$uid;
        $method = $uid === null ? 'post' : 'put';

        $response = $this->client()->{$method}($url.'?email='.urlencode($email), $payload);
        $response->throw();

        return (array) $response->json('data', []);
    }

    public function deleteTask(string $email, string $uid): void
    {
        $this->client()
            ->delete('/api/tasks/'.$uid.'?email='.urlencode($email))
            ->throw();
    }

    public function isConfigured(): bool
    {
        $baseUrl = (string) config('services.webdav.base_url');
        $token = (string) config('services.webdav.api_token');

        return $baseUrl !== '' && $token !== '';
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.webdav.base_url'), '/');
        $token = (string) config('services.webdav.api_token');

        if ($baseUrl === '' || $token === '')
        {
            throw new \RuntimeException('WebDAV API is not configured. Set WEBDAV_BASE_URL and WEBDAV_API_TOKEN.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->timeout(30);
    }
}
