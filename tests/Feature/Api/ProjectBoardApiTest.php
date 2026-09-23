<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskCommunication;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectBoardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: string, 2: Project, 3: Task}
     */
    private function projectWithTask(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Board Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Project board',
            'description' => 'Test board',
            'is_default' => false,
            'order' => 0,
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Board Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $todo = TaskStatus::where('name', 'TO_DO')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'First task',
            'responsible_id' => $user->id,
            'status_id' => $todo->id,
            'order' => 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $token = $user->createToken('board-test')->plainTextToken;

        return [$user, $token, $project, $task];
    }

    public function test_board_returns_columns_and_tasks(): void
    {
        [, $token, $project, $task] = $this->projectWithTask();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonStructure([
                'data' => [
                    'project' => ['id', 'name', 'board_id'],
                    'board' => ['id', 'name'],
                    'columns' => [
                        '*' => ['id', 'name', 'translated_name', 'tasks'],
                    ],
                ],
            ]);

        $allTaskIds = collect($response->json('data.columns'))
            ->flatMap(fn ($column) => collect($column['tasks'])->pluck('id'))
            ->all();

        $this->assertContains($task->id, $allTaskIds);
    }

    public function test_can_reorder_task_on_board(): void
    {
        [, $token, $project, $task] = $this->projectWithTask();
        $inProgress = TaskStatus::where('name', 'IN_PROGRESS')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id.'/board/reorder', [
                'task_id' => $task->id,
                'status_id' => $inProgress->id,
                'order' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.status_id', $inProgress->id)
            ->assertJsonPath('data.order', 0);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status_id' => $inProgress->id,
            'order' => 0,
        ]);
    }

    public function test_can_update_and_delete_task_via_api(): void
    {
        [$user, $token, $project, $task] = $this->projectWithTask();

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/tasks/'.$task->id, [
                'title' => 'Renamed task',
                'description' => 'Updated from SPA',
                'estimated_hours' => 2.5,
                'responsible_id' => $user->id,
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Renamed task')
            ->assertJsonPath('data.responsible.id', $user->id);

        $this->assertEquals(2.5, (float) $update->json('data.estimated_hours'));

        $board = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $board->assertOk();
        $boardTask = collect($board->json('data.columns'))
            ->flatMap(fn ($column) => $column['tasks'])
            ->firstWhere('id', $task->id);

        $this->assertNotNull($boardTask);
        $this->assertSame($user->id, $boardTask['responsible_id']);
        $this->assertEquals(2.5, (float) $boardTask['estimated_hours']);

        $createOnBoard = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks', [
                'title' => 'Board task',
                'project_id' => $project->id,
                'responsible_id' => $user->id,
            ]);

        $createOnBoard->assertCreated()
            ->assertJsonPath('success', true);

        $newTaskId = $createOnBoard->json('data.task_id');
        $this->assertDatabaseHas('tasks', [
            'id' => $newTaskId,
            'board_id' => $project->board_id,
        ]);

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/tasks/'.$task->id);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_can_attach_and_remove_task_image(): void
    {
        Storage::fake('public');
        [, $token, $project, $task] = $this->projectWithTask();

        $upload = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/tasks/'.$task->id.'/attachment', [
                'attachment' => UploadedFile::fake()->image('brief.jpg', 40, 40),
            ]);

        $upload->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $task->id);

        $url = $upload->json('data.attachment');
        $this->assertIsString($url);
        $this->assertNotSame('', $url);
        $this->assertCount(1, $task->fresh()->getMedia('attachments'));

        $replaced = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/tasks/'.$task->id.'/attachment', [
                'attachment' => UploadedFile::fake()->image('brief-2.jpg', 40, 40),
            ]);

        $replaced->assertOk();
        $this->assertCount(1, $task->fresh()->getMedia('attachments'));

        $board = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $boardTask = collect($board->json('data.columns'))
            ->flatMap(fn ($column) => $column['tasks'])
            ->firstWhere('id', $task->id);

        $this->assertNotNull($boardTask);
        $this->assertSame($replaced->json('data.attachment'), $boardTask['attachment']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/tasks/'.$task->id.'/attachment')
            ->assertOk()
            ->assertJsonPath('data.attachment', null);

        $this->assertCount(0, $task->fresh()->getMedia('attachments'));
    }

    public function test_task_attachment_rejects_non_images(): void
    {
        Storage::fake('public');
        [, $token, , $task] = $this->projectWithTask();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/api/tasks/'.$task->id.'/attachment', [
                'attachment' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertCount(0, $task->fresh()->getMedia('attachments'));
    }

    public function test_can_consult_the_client_about_a_task(): void
    {
        [, $token, $project, $task] = $this->projectWithTask();

        Enterprise::withoutGlobalScopes()
            ->where('id', $project->enterprise_id)
            ->update(['email' => 'cliente@example.com']);

        $send = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/communications', [
                'recipients' => ['responsible', 'client'],
                'message' => '¿Podéis confirmar el texto del botón?',
            ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', 'Consulta sobre tarea')
            ->assertJsonPath('data.recipients_display', 'Responsable, Cliente')
            ->assertJsonPath('data.message', '¿Podéis confirmar el texto del botón?')
            ->assertJsonPath('data.has_response', false);

        $communication = TaskCommunication::where('task_id', $task->id)->first();
        $this->assertNotNull($communication);
        $this->assertSame(['responsible', 'client'], $communication->recipients);
        $this->assertNotNull($communication->response_token);

        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $addresses = $transport->messages()
            ->flatMap(fn ($message) => collect($message->getEnvelope()->getRecipients())->map->getAddress())
            ->all();
        $this->assertContains($task->responsible->email, $addresses);
        $this->assertContains('cliente@example.com', $addresses);

        $clientMessage = $transport->messages()->first(function ($message)
        {
            return collect($message->getEnvelope()->getRecipients())
                ->contains(fn ($address) => $address->getAddress() === 'cliente@example.com');
        });
        $this->assertNotNull($clientMessage);
        $this->assertStringContainsString(
            '/consulta/'.$communication->response_token,
            (string) $clientMessage->getOriginalMessage()->getHtmlBody(),
        );

        $landing = $this->getJson('/api/task-communication/'.$communication->response_token);
        $landing->assertOk()
            ->assertJsonPath('data.project.name', 'Board Project')
            ->assertJsonPath('data.task.title', 'First task')
            ->assertJsonPath('data.tasks.0.title', 'First task')
            ->assertJsonPath('data.tasks.0.status_label', 'Por hacer')
            ->assertJsonMissingPath('data.tasks.0.estimated_hours');

        $reply = $this->postJson('/api/task-communication/'.$communication->response_token, [
            'response' => 'El texto está bien.',
            'action' => 'mark_complete',
        ]);

        $reply->assertOk()
            ->assertJsonPath('data.has_response', true)
            ->assertJsonPath('data.response', 'El texto está bien.')
            ->assertJsonPath('data.tasks.0.status', 'DONE');

        $this->assertSame(
            TaskStatus::where('name', 'DONE')->value('id'),
            $task->fresh()->status_id,
        );

        $this->postJson('/api/task-communication/'.$communication->response_token, [
            'response' => 'Otra vez',
            'action' => 'respond_todo',
        ])->assertStatus(422);

        $history = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks/'.$task->id.'/communications');

        $history->assertOk()
            ->assertJsonPath('data.0.id', $communication->id)
            ->assertJsonPath('data.0.recipients_display', 'Responsable, Cliente');
    }

    public function test_can_save_due_date_category_and_read_activity(): void
    {
        [$user, $token, , $task] = $this->projectWithTask();

        $category = Category::query()->create([
            'name' => 'Cobranza',
            'team_id' => $user->currentTeam->id,
            'status' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/tasks/'.$task->id, [
                'due_date' => '2026-10-01',
                'category_id' => $category->id,
                'description' => 'Detalle de la tarea',
            ])
            ->assertOk()
            ->assertJsonPath('data.due_date', '2026-10-01')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.name', 'Cobranza')
            ->assertJsonPath('data.description', 'Detalle de la tarea');

        Time::query()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'task_id' => $task->id,
            'description' => 'Revisión',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'duration_seconds' => 3600,
            'is_billable' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks/'.$task->id.'/activities')
            ->assertOk()
            ->assertJsonPath('data.times.0.description', 'Revisión')
            ->assertJsonPath('data.times.0.is_running', false);
    }

    public function test_task_consultation_requires_a_recipient_and_a_message(): void
    {
        [, $token, , $task] = $this->projectWithTask();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/communications', [
                'recipients' => [],
                'message' => 'Hola',
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/communications', [
                'recipients' => ['client'],
                'message' => '',
            ])
            ->assertStatus(422);
    }

    public function test_start_timer_keeps_running_and_the_board_exposes_it(): void
    {
        [$user, $token, $project, $task] = $this->projectWithTask();

        $start = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/start');

        $start->assertOk()
            ->assertJsonPath('data.status.name', 'IN_PROGRESS')
            ->assertJsonPath('data.task_id', $task->id);

        $this->assertNotEmpty($start->json('data.started_at'));

        $again = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/start');

        $again->assertOk()
            ->assertJsonPath('data.time_id', $start->json('data.time_id'));

        $this->assertSame(
            1,
            Time::withoutGlobalScopes()->where('task_id', $task->id)->whereNull('end_time')->count(),
        );

        $board = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $boardTask = collect($board->json('data.columns'))
            ->flatMap(fn ($column) => $column['tasks'])
            ->firstWhere('id', $task->id);

        $this->assertNotNull($boardTask);
        $this->assertSame('IN_PROGRESS', $boardTask['status']['name']);
        $this->assertSame($start->json('data.time_id'), $boardTask['active_time']['id']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/stop')
            ->assertOk()
            ->assertJsonStructure(['data' => ['duration_formatted', 'status']]);

        $stopped = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $stoppedTask = collect($stopped->json('data.columns'))
            ->flatMap(fn ($column) => $column['tasks'])
            ->firstWhere('id', $task->id);

        $this->assertNull($stoppedTask['active_time']);
        $this->assertSame($user->id, $task->fresh()->responsible_id);
    }
}
