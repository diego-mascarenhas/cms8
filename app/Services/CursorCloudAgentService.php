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
            $message = $response->json('error') ?? $response->json('message') ?? 'Cursor no pudo crear el agente.';

            throw new RuntimeException(is_string($message) ? $message : 'Cursor no pudo crear el agente.');
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
}
