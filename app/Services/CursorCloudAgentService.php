<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CursorCloudAgentService
{
    public const AGENT_MARK = "[[agent]]\n";

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
            if ($phase === 'processing')
            {
                $this->rememberProgress($project, (string) $stored['id'], $live['run_id'] ?? null);
            } else
            {
                $data = is_array($project->data) ? $project->data : [];
                $agent = is_array($data['cursor_agent'] ?? null) ? $data['cursor_agent'] : [];
                $agent['phase'] = $phase;
                $agent['result'] = $result ?? '';
                if (empty($agent['settled']))
                {
                    $this->finishTasks($project, $agent, $phase, $result);
                    $agent['settled'] = true;
                }
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
            'task_ids' => array_values(array_map('intval', is_array($stored['task_ids'] ?? null) ? $stored['task_ids'] : [])),
        ];
    }

    /**
     * @param  list<int>  $taskIds
     * @return array{task_ids: list<int>, time_ids: list<int>}
     */
    public function beginTasks(Project $project, User $user, array $taskIds): array
    {
        $ids = collect($taskIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $project->board_id)
        {
            return ['task_ids' => [], 'time_ids' => []];
        }

        $todo = TaskStatus::query()->where('name', 'TO_DO')->first();
        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->first();
        if (! $todo || ! $inProgress)
        {
            return ['task_ids' => [], 'time_ids' => []];
        }

        $tasks = Task::query()
            ->where('board_id', $project->board_id)
            ->whereIn('id', $ids)
            ->where('status_id', $todo->id)
            ->get();

        $tracked = [];
        $times = [];
        foreach ($tasks as $task)
        {
            $task->update(['status_id' => $inProgress->id]);
            $time = Time::query()->create([
                'team_id' => $project->team_id,
                'user_id' => $user->id,
                'task_id' => $task->id,
                'description' => self::AGENT_MARK,
                'start_time' => now(),
            ]);
            $tracked[] = (int) $task->id;
            $times[] = (int) $time->id;
        }

        return ['task_ids' => $tracked, 'time_ids' => $times];
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function finishTasks(Project $project, array $agent, string $phase, ?string $result): void
    {
        $taskIds = collect($agent['task_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $timeIds = collect($agent['time_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $note = trim((string) ($result ?? ''));
        if ($note === '')
        {
            $note = $phase === 'error' ? 'El agente se detuvo.' : 'El agente terminó.';
        }
        $note = self::AGENT_MARK.$note;

        if ($phase === 'done' && $taskIds !== [] && $project->board_id)
        {
            $review = TaskStatus::query()->where('name', 'REVIEW')->first();
            $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->first();
            if ($review && $inProgress)
            {
                Task::query()
                    ->where('board_id', $project->board_id)
                    ->whereIn('id', $taskIds)
                    ->where('status_id', $inProgress->id)
                    ->update(['status_id' => $review->id]);
            }
        }

        $times = Time::query()->whereIn('id', $timeIds)->whereNull('end_time')->get();
        foreach ($times as $time)
        {
            $time->description = $note;
            $time->end_time = now();
            $time->calculateDuration();
        }
    }

    /**
     * @return array{phase: string, result: string|null, run_id: string|null}
     */
    private function liveOutcome(string $agentId): array
    {
        $pending = ['phase' => 'processing', 'result' => null, 'run_id' => null];
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

        $runId = $this->runId($response->json('latestRunId'));
        $agentStatus = (string) $response->json('status');
        if ($agentStatus === 'ACTIVE')
        {
            return ['phase' => 'processing', 'result' => null, 'run_id' => $runId];
        }

        if ($agentStatus !== 'IDLE')
        {
            return ['phase' => 'done', 'result' => null, 'run_id' => $runId];
        }

        if ($runId === null)
        {
            return ['phase' => 'done', 'result' => null, 'run_id' => null];
        }

        $run = Http::withBasicAuth($key, '')
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.cursor.com/v1/agents/'.$agentId.'/runs/'.$runId);

        if (! $run->successful())
        {
            return ['phase' => 'done', 'result' => null, 'run_id' => $runId];
        }

        $runStatus = (string) $run->json('status');
        $result = $run->json('result');
        $text = is_string($result) ? Str::limit(trim($result), 20000, '') : null;
        if (in_array($runStatus, ['CREATING', 'RUNNING', 'WAITING_FOR_BACKGROUND_WORK'], true))
        {
            return ['phase' => 'processing', 'result' => null, 'run_id' => $runId];
        }

        if (in_array($runStatus, ['ERROR', 'CANCELLED', 'EXPIRED'], true))
        {
            return ['phase' => 'error', 'result' => $text !== '' ? $text : null, 'run_id' => $runId];
        }

        return ['phase' => 'done', 'result' => $text !== '' ? $text : null, 'run_id' => $runId];
    }

    public function syncRunningNote(Project $project): void
    {
        $stored = data_get($project->data, 'cursor_agent');
        if (! is_array($stored) || ($stored['phase'] ?? null) !== 'processing' || ! filled($stored['id'] ?? null))
        {
            return;
        }

        $runId = $this->runId($stored['run_id'] ?? null);
        if ($runId === null)
        {
            $live = $this->liveOutcome((string) $stored['id']);
            if ($live['phase'] !== 'processing')
            {
                return;
            }
            $runId = $live['run_id'] ?? null;
        }

        $this->rememberProgress($project, (string) $stored['id'], $runId);
    }

    public static function isAgentNote(?string $description): bool
    {
        return is_string($description) && str_starts_with($description, self::AGENT_MARK);
    }

    public static function visibleAgentNote(?string $description): ?string
    {
        if (! self::isAgentNote($description))
        {
            return null;
        }

        $text = trim(substr((string) $description, strlen(self::AGENT_MARK)));

        return $text !== '' ? $text : null;
    }

    public function noteFromStream(string $body): ?string
    {
        $assistant = '';
        $tool = null;
        $event = null;
        $data = '';

        foreach (preg_split("/\r\n|\n|\r/", $body) ?: [] as $line)
        {
            if (str_starts_with($line, 'event:'))
            {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:'))
            {
                $data .= ltrim(substr($line, 5));
            } elseif ($line === '')
            {
                $this->consumeStreamEvent($event, $data, $assistant, $tool);
                $event = null;
                $data = '';
            }
        }

        $this->consumeStreamEvent($event, $data, $assistant, $tool);
        $text = trim($assistant);
        if (is_string($tool) && $tool !== '')
        {
            $text = trim($text."\n".$tool);
        }

        $text = trim($text);

        return $text !== '' ? Str::limit($text, 4000, '') : null;
    }

    private function rememberProgress(Project $project, string $agentId, ?string $runId): void
    {
        $data = is_array($project->data) ? $project->data : [];
        $agent = is_array($data['cursor_agent'] ?? null) ? $data['cursor_agent'] : [];
        $runId ??= $this->runId($agent['run_id'] ?? null);
        if ($runId === null)
        {
            return;
        }

        $progressAt = $agent['progress_at'] ?? null;
        if (is_string($progressAt) && now()->parse($progressAt)->gt(now()->subSeconds(3)))
        {
            return;
        }

        $note = $this->pullRunNote($agentId, $runId);
        $agent['run_id'] = $runId;
        $agent['progress_at'] = now()->toIso8601String();
        $data['cursor_agent'] = $agent;
        $project->update(['data' => $data]);

        if ($note === null)
        {
            return;
        }

        Time::query()
            ->whereIn('id', collect($agent['time_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all())
            ->whereNull('end_time')
            ->update(['description' => self::AGENT_MARK.$note]);
    }

    private function pullRunNote(string $agentId, string $runId): ?string
    {
        $key = trim((string) config('services.cursor.api_key'));
        if ($key === '')
        {
            return null;
        }

        try
        {
            $response = Http::withBasicAuth($key, '')
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->timeout(2)
                ->get('https://api.cursor.com/v1/agents/'.$agentId.'/runs/'.$runId.'/stream');
        } catch (\Throwable)
        {
            return null;
        }

        if (! $response->successful())
        {
            return null;
        }

        return $this->noteFromStream($response->body());
    }

    private function runId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9_-]+\z/', $value) === 1 ? $value : null;
    }

    private function consumeStreamEvent(?string $event, string $data, string &$assistant, ?string &$tool): void
    {
        if ($event === null || $data === '')
        {
            return;
        }

        $payload = json_decode($data, true);
        if (! is_array($payload))
        {
            return;
        }

        if ($event === 'assistant' && is_string($payload['text'] ?? null))
        {
            $assistant .= $payload['text'];
            $tool = null;
        }

        if ($event === 'tool_call' && is_string($payload['name'] ?? null))
        {
            $tool = $this->toolLine($payload['name'], $payload['args'] ?? null);
        }
    }

    private function toolLine(string $name, mixed $args): string
    {
        $path = '';
        if (is_array($args))
        {
            $file = $args['path'] ?? $args['file'] ?? $args['target_file'] ?? '';
            $path = is_string($file) && $file !== '' ? ' '.basename($file) : '';
        }

        $normalized = strtolower($name);
        if (str_contains($normalized, 'read') || str_contains($normalized, 'grep') || str_contains($normalized, 'search'))
        {
            return 'Leyendo'.$path;
        }

        if (str_contains($normalized, 'edit') || str_contains($normalized, 'write') || str_contains($normalized, 'replace'))
        {
            return 'Editando'.$path;
        }

        if (str_contains($normalized, 'terminal') || str_contains($normalized, 'shell'))
        {
            return 'Ejecutando un comando';
        }

        return 'Trabajando'.$path;
    }
}
