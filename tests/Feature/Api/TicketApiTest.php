<?php

namespace Tests\Feature\Api;

use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'client', 'collaborator'] as $role)
        {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function userWithToken(string $role = 'admin'): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);
        $this->enableTeamModules($team, ['tickets']);

        $token = $user->createToken('idoneo-tickets-test')->plainTextToken;

        return [$user, $team, $token];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/tickets')->assertUnauthorized();
    }

    public function test_admin_can_create_list_and_show_a_ticket(): void
    {
        [$user, $team, $token] = $this->userWithToken();

        $created = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets', [
                'subject' => 'No carga el panel',
                'description' => 'El panel queda en blanco después del login.',
                'priority' => 'high',
            ]);

        $created->assertCreated()
            ->assertJsonPath('data.subject', 'No carga el panel')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('tickets', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'No carga el panel',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tickets?search=panel&status=open&priority=high')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'No carga el panel')
            ->assertJsonPath('pagination.total', 1);

        $id = $created->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tickets/'.$id)
            ->assertOk()
            ->assertJsonPath('data.description', 'El panel queda en blanco después del login.')
            ->assertJsonPath('data.permissions.update', true);
    }

    public function test_reply_moves_open_ticket_to_in_progress_and_hides_internal_notes_from_clients(): void
    {
        [$admin, $team, $adminToken] = $this->userWithToken();
        $client = User::factory()->create();
        $client->assignRole('client');
        $team->users()->attach($client, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();
        $clientToken = $client->createToken('idoneo-tickets-client')->plainTextToken;

        $ticket = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'priority' => 'medium',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/tickets/'.$ticket->id.'/response', [
                'message' => 'Nota interna',
                'is_internal_note' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.responses.0.is_internal_note', true);

        $this->withHeader('Authorization', 'Bearer '.$clientToken)
            ->getJson('/api/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('data.responses', []);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/tickets/'.$ticket->id.'/response', [
                'message' => 'Ya lo estamos mirando.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertTrue(
            TicketResponse::query()->where('ticket_id', $ticket->id)->where('is_internal_note', true)->exists(),
        );
        $this->assertNotNull($ticket->fresh()->last_response_at);
        $this->assertSame($admin->id, TicketResponse::query()->where('ticket_id', $ticket->id)->where('is_internal_note', false)->value('user_id'));
    }

    public function test_client_only_lists_own_tickets_and_cannot_open_another(): void
    {
        [, $team] = $this->userWithToken();
        $client = User::factory()->create();
        $client->assignRole('client');
        $team->users()->attach($client, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();
        $token = $client->createToken('idoneo-tickets-client')->plainTextToken;

        $own = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'subject' => 'El mío',
        ]);
        $other = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => User::factory(),
            'subject' => 'Ajeno',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $own->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tickets/'.$other->id)
            ->assertForbidden();
    }

    public function test_assign_status_close_and_rate(): void
    {
        [$admin, $team, $token] = $this->userWithToken();
        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator, ['role' => 'collaborator']);

        $ticket = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/assign', [
                'assigned_to' => $collaborator->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.id', $collaborator->id)
            ->assertJsonPath('data.status', 'in_progress');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/priority', [
                'priority' => 'urgent',
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'urgent');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/rate', [
                'tiempo_respuesta' => 5,
                'atencion' => 4,
                'solucion' => 5,
                'comentarios' => 'Resuelto',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating.promedio', 4.7);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/rate', [
                'tiempo_respuesta' => 1,
                'atencion' => 1,
                'solucion' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_update_subject_and_attach_a_file_to_a_reply(): void
    {
        [, , $token] = $this->userWithToken();
        $created = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets', [
                'subject' => 'Original',
                'description' => 'Texto',
                'priority' => 'low',
            ])
            ->assertCreated();

        $id = $created->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/tickets/'.$id, [
                'subject' => 'Corregido',
                'description' => 'Texto nuevo',
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Corregido')
            ->assertJsonPath('data.description', 'Texto nuevo');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/tickets/'.$id.'/response', [
                'message' => 'Adjunto el log',
                'attachments' => [
                    \Illuminate\Http\UploadedFile::fake()->createWithContent('log.txt', 'hello log'),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.responses.0.attachments.0.file_name', 'log.txt');
    }

    public function test_stats_counts_open_tickets(): void
    {
        [$user, $team, $token] = $this->userWithToken();

        Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
        ]);
        Ticket::factory()->closed()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tickets/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.open', 1)
            ->assertJsonPath('data.closed', 1)
            ->assertJsonPath('data.mine', 1);
    }
}
