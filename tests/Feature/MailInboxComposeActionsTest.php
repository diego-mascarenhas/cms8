<?php

namespace Tests\Feature;

use App\Livewire\MailInbox;
use App\Models\Email;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailInboxComposeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_reply_to_selected_dispatches_compose_payload(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, [
            'from_address' => 'Partner <partner@example.com>',
            'subject' => 'Hola',
            'body_text' => 'Mensaje de prueba',
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('selectEmail', $email->id)
            ->call('replyToSelected')
            ->assertDispatched('open-mail-compose', recipients: ['partner@example.com'], subject: 'Re: Hola');
    }

    private function userWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmail(Team $team, array $attributes = []): Email
    {
        return Email::factory()->create(array_merge([
            'team_id' => $team->id,
        ], $attributes));
    }
}
