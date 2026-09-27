<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTeam(string $roleName = 'admin'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_ticket_index_requires_authentication(): void
    {
        $response = $this->get(route('ticket.index'));

        $response->assertRedirect();
    }

    public function test_ticket_index_loads_for_authorized_user(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);

        $response = $this->get(route('ticket.index'));

        $response->assertStatus(200);
        $response->assertSee(__('Tickets'), false);
        $response->assertSee('ticket-table', false);
        $response->assertDontSee(__('tickets.Actions'), false);
        $response->assertDontSee(__('tickets.Assigned to'), false);
    }

    public function test_ticket_datatable_shows_creator_under_subject(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Creator under subject',
            'description' => 'Desc',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->actingAs($user);

        $columns = [];
        foreach (['id', 'subject', 'status', 'priority'] as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('ticket.index').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => $columns,
        ]));

        $response->assertOk();
        $payload = $response->json();
        $this->assertNotEmpty($payload['data'] ?? []);
        $subjectHtml = $payload['data'][0]['subject'] ?? '';
        $this->assertStringContainsString('Creator under subject', $subjectHtml);
        $this->assertStringContainsString($user->name, $subjectHtml);
        $this->assertArrayNotHasKey('action', $payload['data'][0]);

        $columnKeys = collect(app(\App\DataTables\TicketDataTable::class)->html()->getColumns())
            ->map(fn ($column) => $column['data'] ?? $column['name'] ?? null)
            ->filter()
            ->values()
            ->all();
        $this->assertSame(['id', 'subject', 'status', 'priority'], $columnKeys);
    }

    public function test_ticket_create_form_loads(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);

        $response = $this->get(route('ticket.create'));

        $response->assertStatus(200);
        $response->assertSee(__('Create'), false);
        $response->assertSee('name="subject"', false);
        $response->assertSee('name="priority"', false);
    }

    public function test_ticket_can_be_stored(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $this->actingAs($user);

        $response = $this->post(route('ticket.store'), [
            '_token' => csrf_token(),
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority' => 'medium',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => 'open',
        ]);
    }

    public function test_ticket_show_loads_for_creator(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Show test',
            'description' => 'Desc',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('ticket.show', $ticket->id));

        $response->assertStatus(200);
        $response->assertSee('Show test', false);
        $response->assertSee('Desc', false);
    }

    public function test_ticket_show_uses_avatar_initial_markup(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Avatar test',
            'description' => 'Desc',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $ticket->responses()->create([
            'user_id' => $user->id,
            'message' => 'First reply',
            'is_internal_note' => false,
        ]);
        $this->actingAs($user);

        $response = $this->get(route('ticket.show', $ticket->id));

        $response->assertOk();
        $response->assertSee('avatar-initial', false);
        $response->assertSee('avatar avatar-sm', false);
    }

    public function test_ticket_response_can_be_added(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Reply test',
            'description' => 'Desc',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('ticket.response', $ticket->id), [
            '_token' => csrf_token(),
            'message' => 'A reply message',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_responses', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'A reply message',
            'is_internal_note' => false,
        ]);
    }

    public function test_ticket_can_be_closed(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Close test',
            'description' => 'Desc',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('ticket.close', $ticket->id), ['_token' => csrf_token()]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_ticket_can_be_rated_when_closed(): void
    {
        $user = $this->createUserWithTeam('admin');
        $team = $user->currentTeam;
        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'subject' => 'Rate test',
            'description' => 'Desc',
            'status' => 'closed',
            'priority' => 'medium',
            'closed_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->post(route('ticket.rate', $ticket->id), [
            '_token' => csrf_token(),
            'tiempo_respuesta' => 5,
            'atencion' => 4,
            'solucion' => 5,
            'comentarios' => 'Great support',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_ratings', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'tiempo_respuesta' => 5,
            'atencion' => 4,
            'solucion' => 5,
            'comentarios' => 'Great support',
        ]);
    }

    public function test_ticket_store_validates_required_fields(): void
    {
        $user = $this->createUserWithTeam('admin');
        $this->actingAs($user);

        $response = $this->post(route('ticket.store'), [
            '_token' => csrf_token(),
            'subject' => '',
            'description' => '',
            'priority' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['subject', 'description', 'priority']);
    }
}
