<?php

namespace Tests\Feature\Api;

use App\Mail\TicketActivityMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketActivityMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'client', 'collaborator'] as $role)
        {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        config([
            'services.revisionalpha.url' => 'https://revisionalpha.test',
            'mail.from.address' => 'hola@revisionalpha.test',
            'mail.from.name' => 'REVISION ALPHA',
        ]);

        Mail::fake();
    }

    public function test_a_new_ticket_emails_the_other_team_managers(): void
    {
        [$owner, $team, $token] = $this->userWithToken();
        $manager = User::factory()->create(['email' => 'gerente@example.test']);
        $team->users()->attach($manager, ['role' => 'admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets', [
                'subject' => 'No carga el panel',
                'description' => 'El panel queda en blanco.',
                'priority' => 'high',
            ])
            ->assertCreated();

        Mail::assertSent(TicketActivityMail::class, function (TicketActivityMail $mail) use ($manager, $owner): bool
        {
            return $mail->hasTo($manager->email)
                && ! $mail->hasTo($owner->email)
                && str_contains($mail->subjectLine, 'Nuevo ticket')
                && str_contains($mail->url, '/ticket/');
        });
    }

    public function test_a_client_reply_emails_managers_and_a_staff_reply_emails_the_client(): void
    {
        [$owner, $team, $adminToken] = $this->userWithToken();
        $manager = User::factory()->create(['email' => 'gerente@example.test']);
        $team->users()->attach($manager, ['role' => 'admin']);

        $client = User::factory()->create(['email' => 'ana@example.test']);
        $client->assignRole('client');
        $team->users()->attach($client, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();
        $clientToken = $client->createToken('client')->plainTextToken;

        $ticket = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'subject' => 'La web no abre',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$clientToken)
            ->postJson('/api/tickets/'.$ticket->id.'/response', [
                'message' => 'Sigue caída.',
            ])
            ->assertOk();

        Mail::assertSent(TicketActivityMail::class, function (TicketActivityMail $mail) use ($manager, $owner, $client): bool
        {
            return $mail->hasTo($manager->email)
                && $mail->hasTo($owner->email)
                && ! $mail->hasTo($client->email)
                && str_contains($mail->subjectLine, 'Respuesta');
        });

        Mail::fake();

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/tickets/'.$ticket->id.'/response', [
                'message' => 'Ya volvió.',
            ])
            ->assertOk();

        Mail::assertSent(TicketActivityMail::class, function (TicketActivityMail $mail) use ($client, $owner): bool
        {
            return $mail->hasTo($client->email)
                && ! $mail->hasTo($owner->email)
                && $mail->url === 'https://revisionalpha.test/cms/tickets/'.$ticket->id;
        });
    }

    public function test_an_internal_note_does_not_send_email(): void
    {
        [$owner, $team, $token] = $this->userWithToken();
        $ticket = Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'subject' => 'Nota interna',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tickets/'.$ticket->id.'/response', [
                'message' => 'Solo para el equipo.',
                'is_internal_note' => true,
            ])
            ->assertOk();

        Mail::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function userWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create(['email' => 'dueno@example.test']);
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $this->enableTeamModules($team, ['tickets']);

        return [$user, $team, $user->createToken('tickets-mail')->plainTextToken];
    }
}
