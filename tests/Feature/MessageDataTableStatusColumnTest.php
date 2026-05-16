<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageDataTableStatusColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MessageTypeSeeder::class);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablesQueryParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'category_info', 'name' => 'category_info', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'progress', 'name' => 'progress', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'status_id', 'name' => 'status_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ];
    }

    #[Test]
    public function message_datatable_status_column_shows_scheduled_sending_and_paused_badges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00', 'UTC'));

        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user);
        app()->setLocale('en');

        $teamId = (int) $user->current_team_id;

        Message::create([
            'name' => 'Z Scheduled row',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 1,
            'scheduled_send_at' => Carbon::parse('2026-06-15 10:00:00', 'UTC'),
        ]);

        Message::create([
            'name' => 'Y Sending row',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 1,
            'scheduled_send_at' => null,
        ]);

        Message::create([
            'name' => 'A Paused row',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 0,
            'scheduled_send_at' => null,
        ]);

        $this->assertSame(3, Message::query()->count(), 'Expected messages visible for current team scope');

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->getJson(route('message.index', $this->dataTablesQueryParams()));

        $response->assertOk();
        $response->assertJsonPath('recordsTotal', 3);
        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertCount(3, $rows);

        $this->assertStringContainsString(__('app.message_list_status_paused'), (string) $rows[0]['status_id']);
        $this->assertStringContainsString('bg-label-warning', (string) $rows[0]['status_id']);

        $this->assertStringContainsString(__('app.message_list_status_sending'), (string) $rows[1]['status_id']);
        $this->assertStringContainsString('bg-label-success', (string) $rows[1]['status_id']);

        $this->assertStringContainsString(__('app.message_list_status_scheduled'), (string) $rows[2]['status_id']);
        $this->assertStringContainsString('bg-label-info', (string) $rows[2]['status_id']);

        Carbon::setTestNow();
    }

    #[Test]
    public function message_datatable_progress_no_deliveries_shows_schedule_subline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00', 'UTC'));

        $user = $this->userWithPersonalTeamResolved();
        $this->actingAs($user);
        app()->setLocale('es');

        $teamId = (int) $user->current_team_id;

        Message::create([
            'name' => 'A sin programar',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 0,
            'scheduled_send_at' => null,
        ]);

        Message::create([
            'name' => 'B con hora',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 0,
            'scheduled_send_at' => Carbon::parse('2026-07-20 14:30:00', 'UTC'),
        ]);

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->getJson(route('message.index', $this->dataTablesQueryParams()));

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertCount(2, $rows);

        $this->assertStringContainsString(__('app.message_list_no_deliveries'), (string) $rows[0]['progress']);
        $this->assertStringContainsString(__('app.message_list_not_scheduled'), (string) $rows[0]['progress']);

        $this->assertStringContainsString(__('app.message_list_no_deliveries'), (string) $rows[1]['progress']);
        $this->assertStringContainsString('14:30', (string) $rows[1]['progress']);
        $this->assertStringContainsString('Programado:', (string) $rows[1]['progress']);

        Carbon::setTestNow();
    }

    #[Test]
    public function message_datatable_name_column_links_to_show(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $message = Message::create([
            'name' => '[Demo] Secuencia — Paso 1 bienvenida',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->getJson(route('message.index', $this->dataTablesQueryParams()));

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('DT_RowId', (string) $message->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString(route('message.show', $message->id), $row['name']);
        $this->assertStringContainsString('<a href', $row['name']);
    }
}
