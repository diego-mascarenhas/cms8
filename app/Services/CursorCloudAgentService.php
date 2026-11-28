<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CursorCloudAgentService
{
    public function branchName(Project $project): string
    {
        $slug = Str::slug($project->name);
        $slug = $slug !== '' ? $slug : 'proyecto';

        return 'idoneo/'.Str::limit($slug, 60, '');
    }

    public function normalizeRepositoryUrl(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, '/');

        if (str_ends_with(strtolower($url), '.git'))
        {
            $url = substr($url, 0, -4);
        }

        return $url;
    }

    public function failureMessage(mixed $body): string
    {
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $message = is_array($error) ? ($error['message'] ?? null) : (is_string($error) ? $error : null);

        if ($code === 'key_not_supported')
        {
            return 'Esa clave no sirve para los agentes de Cursor. Crea una clave de usuario en cursor.com/dashboard, en API Keys. Las que empiezan por grok_ no están admitidas.';
        }

        if ($code === 'repository_access')
        {
            return 'Cursor no puede leer ese repositorio. Revisa la URL y que la cuenta de la clave tenga GitHub conectado con acceso a él.';
        }

        if (is_string($message) && $message !== '')
        {
            return $message;
        }

        return 'Cursor no pudo crear el agente.';
    }

    /**
     * @return array{id: string, url: string, branch: string}
     */
    public function launch(Project $project, string $prompt, string $repositoryUrl): array
    {
        $key = trim((string) config('services.cursor.api_key'));
        if ($key === '')
        {
            throw new RuntimeException('Falta la clave de Cursor en el servidor.');
        }

        $repositoryUrl = $this->normalizeRepositoryUrl($repositoryUrl);
        $branch = $this->branchName($project);
        $response = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.cursor.com/v1/agents', [
                'name' => Str::limit('IDONEO '.$project->name, 100, ''),
                'prompt' => [
                    'text' => "Trabaja en la rama `{$branch}`. Créala desde la rama base y haz ahí todos los commits. El nombre tiene que empezar por idoneo/.\n\n".$prompt,
                ],
                'repos' => [
                    ['url' => $repositoryUrl],
                ],
                'autoCreatePR' => true,
            ]);

        if (! $response->successful())
        {
            throw new RuntimeException($this->failureMessage($response->json()));
        }

        $agent = $response->json('agent');
        if (! is_array($agent) || ! filled($agent['id'] ?? null))
        {
            throw new RuntimeException('Cursor no devolvió el agente.');
        }

        return [
            'id' => (string) $agent['id'],
            'url' => (string) ($agent['url'] ?? 'https://cursor.com/agents/'.$agent['id']),
            'branch' => $branch,
        ];
    }

    /**
     * @return array{id: string, url: string, branch: string, phase: string, result: string|null}|null
     */
    public function status(Project $project): ?array
    {
        $stored = data_get($project->data, 'cursor_agent');
        if (! is_array($stored) || ! filled($stored['id'] ?? null))
        {
            return null;
        }

        $phase = (string) ($stored['phase'] ?? 'processing');
        $result = is_string($stored['result'] ?? null) ? $stored['result'] : null;
        if ($phase === 'processing' || ! array_key_exists('result', $stored))
        {
            $live = $this->liveOutcome((string) $stored['id']);
            $phase = $live['phase'];
            $result = $live['result'];
            if ($phase !== 'processing')
            {
                $data = is_array($project->data) ? $project->data : [];
                $agent = is_array($data['cursor_agent'] ?? null) ? $data['cursor_agent'] : [];
                $agent['phase'] = $phase;
                $agent['result'] = $result ?? '';
                $data['cursor_agent'] = $agent;
                $project->update(['data' => $data]);
            }
        }

        $result = is_string($result) ? trim($result) : '';

        return [
            'id' => (string) $stored['id'],
            'url' => (string) ($stored['url'] ?? 'https://cursor.com/agents/'.$stored['id']),
            'branch' => (string) ($stored['branch'] ?? $this->branchName($project)),
            'phase' => $phase,
            'result' => $result !== '' ? $result : null,
        ];
    }

    /**
     * @return array{phase: string, result: string|null}
     */
    private function liveOutcome(string $agentId): array
    {
        $pending = ['phase' => 'processing', 'result' => null];
        $key = trim((string) config('services.cursor.api_key'));
        if ($key === '' || preg_match('/\A[A-Za-z0-9_-]+\z/', $agentId) !== 1)
        {
            return $pending;
        }

        $response = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.cursor.com/v1/agents/'.$agentId);

        if (! $response->successful())
        {
            return $pending;
        }

        $agentStatus = (string) $response->json('status');
        if ($agentStatus === 'ACTIVE')
        {
            return $pending;
        }

        if ($agentStatus !== 'IDLE')
        {
            return ['phase' => 'done', 'result' => null];
        }

        $runId = $response->json('latestRunId');
        if (! is_string($runId) || preg_match('/\A[A-Za-z0-9_-]+\z/', $runId) !== 1)
        {
            return ['phase' => 'done', 'result' => null];
        }

        $run = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.cursor.com/v1/agents/'.$agentId.'/runs/'.$runId);

        if (! $run->successful())
        {
            return ['phase' => 'done', 'result' => null];
        }

        $runStatus = (string) $run->json('status');
        $result = $run->json('result');
        $text = is_string($result) ? Str::limit(trim($result), 20000, '') : null;
        if (in_array($runStatus, ['CREATING', 'RUNNING', 'WAITING_FOR_BACKGROUND_WORK'], true))
        {
            return $pending;
        }

        if (in_array($runStatus, ['ERROR', 'CANCELLED', 'EXPIRED'], true))
        {
            return ['phase' => 'error', 'result' => $text !== '' ? $text : null];
        }

        return ['phase' => 'done', 'result' => $text !== '' ? $text : null];
    }
}
