<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCommunication;
use App\Models\Team;
use App\Models\Time;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo task communications (creator → responsible → client) and time activities.
 *
 * Seeds the Kanban "Comunicación" and "Actividades" tabs for the Demo team so the
 * whole follow-up circuit can be tested without manually sending emails:
 *   - Internal note to the responsible.
 *   - Client message (with response token) in three states: sent, visited, responded.
 *   - Billable and non-billable {@see Time} entries per task.
 *
 * Fresh install: applied at the end of {@see TeamDemoSeeder} (migrate:fresh --seed).
 * Standalone: php artisan db:seed --class=DemoTaskCommunicationsSeeder
 */
class DemoTaskCommunicationsSeeder extends Seeder
{
    private const int TARGET_PROJECTS = 4;

    private const int TASKS_PER_PROJECT = 3;

    private const int HOURLY_RATE = 65;

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoTaskCommunicationsSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('💬 Seeding demo task communications and activities...');

        foreach (['enterprises', 'projects', 'tasks', 'times'] as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }

        $sender = User::query()->where('email', 'admin@humano.app')->first()
            ?? User::query()->find($team->user_id);

        if ($sender === null)
        {
            $this->command?->warn('DemoTaskCommunicationsSeeder: no sender user found — skip.');

            return;
        }

        $projects = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotNull('enterprise_id')
            ->whereNotNull('board_id')
            ->orderByDesc('id')
            ->get();

        $communications = 0;
        $activities = 0;
        $usedProjects = 0;
        $stateRotation = ['sent', 'visited', 'responded'];
        $globalIndex = 0;

        foreach ($projects as $project)
        {
            if ($usedProjects >= self::TARGET_PROJECTS)
            {
                break;
            }

            $enterprise = Enterprise::withoutGlobalScopes()->find($project->enterprise_id);

            if ($enterprise === null || blank($enterprise->email))
            {
                continue;
            }

            $tasks = Task::withoutGlobalScopes()
                ->where('board_id', $project->board_id)
                ->orderBy('order')
                ->limit(self::TASKS_PER_PROJECT)
                ->get();

            if ($tasks->isEmpty())
            {
                continue;
            }

            $usedProjects++;

            foreach ($tasks as $task)
            {
                $clientState = $stateRotation[$globalIndex % count($stateRotation)];
                $globalIndex++;

                $communications += $this->seedTaskCommunications($task, $sender, $clientState);
                $activities += $this->seedTaskActivities($team->id, $task, $clientState);
            }

            $this->command?->info(sprintf('  ✅ %s (%s): %d tareas con seguimiento.', $project->name, $enterprise->name, $tasks->count()));
        }

        $this->command?->info(sprintf('✅ Demo comunicaciones: %d · actividades (tiempos): %d.', $communications, $activities));
    }

    private function seedTaskCommunications(Task $task, User $sender, string $clientState): int
    {
        $created = 0;

        $internal = TaskCommunication::firstOrCreate(
            [
                'task_id' => $task->id,
                'subject' => 'Seguimiento interno',
            ],
            [
                'user_id' => $sender->id,
                'recipients' => ['responsible'],
                'method' => 'email',
                'message' => 'Hola, ¿podés confirmar el avance de esta tarea y si necesitás algo del cliente para continuar?',
                'sent_at' => now()->subDays(3),
            ],
        );

        if ($internal->wasRecentlyCreated)
        {
            $created++;
        }

        $clientAttributes = [
            'user_id' => $sender->id,
            'recipients' => ['responsible', 'client'],
            'method' => 'email',
            'message' => 'Buenos días, le escribimos para darle seguimiento a esta tarea de su proyecto. ¿Podría confirmarnos si podemos avanzar?',
            'response_token' => Str::random(64),
            'sent_at' => now()->subDays(2),
        ];

        if ($clientState === 'visited')
        {
            $clientAttributes['client_visited_at'] = now()->subDay();
        }

        if ($clientState === 'responded')
        {
            $clientAttributes['client_visited_at'] = now()->subDay();
            $clientAttributes['client_responded_at'] = now()->subDay()->addHours(2);
            $clientAttributes['response'] = 'Perfecto, pueden avanzar. Quedamos atentos a la siguiente entrega.';
            $clientAttributes['response_at'] = now()->subDay()->addHours(2);
        }

        $client = TaskCommunication::firstOrCreate(
            [
                'task_id' => $task->id,
                'subject' => 'Consulta al cliente',
            ],
            $clientAttributes,
        );

        if ($client->wasRecentlyCreated)
        {
            $created++;
        }

        return $created;
    }

    private function seedTaskActivities(int $teamId, Task $task, string $clientState): int
    {
        $responsibleId = $task->responsible_id ?? $task->team?->user_id;

        if ($responsibleId === null)
        {
            return 0;
        }

        $created = 0;

        $entries = [
            [
                'description' => 'Desarrollo y avance de la tarea',
                'start_time' => now()->subDays(2)->setTime(9, 0),
                'end_time' => now()->subDays(2)->setTime(11, 0),
                'duration_seconds' => 2 * 3600,
                'is_billable' => true,
                'hourly_rate' => self::HOURLY_RATE,
            ],
            [
                'description' => 'Revisión y testing',
                'start_time' => now()->subDay()->setTime(15, 0),
                'end_time' => now()->subDay()->setTime(15, 45),
                'duration_seconds' => 45 * 60,
                'is_billable' => true,
                'hourly_rate' => self::HOURLY_RATE,
            ],
        ];

        if ($clientState === 'responded')
        {
            $entries[] = [
                'description' => 'Client view and response (non-billable)',
                'start_time' => now()->subDay()->setTime(12, 0),
                'end_time' => now()->subDay()->setTime(12, 15),
                'duration_seconds' => 15 * 60,
                'is_billable' => false,
                'hourly_rate' => null,
            ];
        }

        foreach ($entries as $entry)
        {
            $time = Time::firstOrCreate(
                [
                    'task_id' => $task->id,
                    'user_id' => $responsibleId,
                    'description' => $entry['description'],
                ],
                [
                    'team_id' => $teamId,
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'duration_seconds' => $entry['duration_seconds'],
                    'is_billable' => $entry['is_billable'],
                    'hourly_rate' => $entry['hourly_rate'],
                ],
            );

            if ($time->wasRecentlyCreated)
            {
                $created++;
            }
        }

        return $created;
    }
}
