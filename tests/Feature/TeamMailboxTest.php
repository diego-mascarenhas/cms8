<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMailboxTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_mailbox_index_loads_for_team_member(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $this->actingAs($user);

        $response = $this->get(route('team.mailboxes.index', $team));

        $response->assertStatus(200);
        $response->assertSee(__('Casillas del equipo'), false);
        $response->assertSee(__('Añadir casilla'), false);
    }

    public function test_mailbox_create_form_loads(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $this->actingAs($user);

        $response = $this->get(route('team.mailboxes.create', $team));

        $response->assertStatus(200);
        $response->assertSee(__('Añadir casilla'), false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="host"', false);
    }

    public function test_mailbox_can_be_stored(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $this->actingAs($user);

        $response = $this->post(route('team.mailboxes.store', $team), [
            '_token' => csrf_token(),
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'protocol' => 'imap',
            'folder' => 'INBOX',
        ]);

        $response->assertRedirect(route('team.mailboxes.index', $team));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mailboxes', [
            'team_id' => $team->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'username' => 'support@example.com',
        ]);
    }

    public function test_mailbox_edit_form_loads(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $mailbox = $team->mailboxes()->create([
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('team.mailboxes.edit', [$team, $mailbox]));

        $response->assertStatus(200);
        $response->assertSee('Support', false);
        $response->assertSee('imap.example.com', false);
    }

    public function test_mailbox_can_be_updated(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $mailbox = $team->mailboxes()->create([
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $response = $this->put(route('team.mailboxes.update', [$team, $mailbox]), [
            '_token' => csrf_token(),
            '_method' => 'PUT',
            'name' => 'Support Updated',
            'host' => 'imap2.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => '',
            'protocol' => 'imap',
            'folder' => 'INBOX',
        ]);

        $response->assertRedirect(route('team.mailboxes.index', $team));
        $response->assertSessionHas('success');

        $mailbox->refresh();
        $this->assertSame('Support Updated', $mailbox->name);
        $this->assertSame('imap2.example.com', $mailbox->host);
    }

    public function test_mailbox_can_be_deleted(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $mailbox = $team->mailboxes()->create([
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $response = $this->delete(route('team.mailboxes.destroy', [$team, $mailbox]));

        $response->assertRedirect(route('team.mailboxes.index', $team));
        $response->assertSessionHas('success');
        $this->assertModelMissing($mailbox);
    }

    public function test_test_connection_returns_json(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $mailbox = $team->mailboxes()->create([
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $response = $this->postJson(route('team.mailboxes.test-connection', [$team, $mailbox]), [], [
            'X-CSRF-TOKEN' => csrf_token(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_mailbox_edit_returns_404_when_mailbox_belongs_to_another_team(): void
    {
        $user = $this->createUserWithTeam();
        $team = $user->currentTeam;
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->ownedTeams()->first();
        $mailbox = $otherTeam->mailboxes()->create([
            'name' => 'Other',
            'host' => 'imap.other.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'other@example.com',
            'password' => 'secret',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('team.mailboxes.edit', [$team, $mailbox]));

        $response->assertStatus(404);
    }
}
