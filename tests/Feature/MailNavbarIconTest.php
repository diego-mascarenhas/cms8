<?php

namespace Tests\Feature;

use App\Enums\EmailFolder;
use App\Livewire\MailNavbarIcon;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailNavbarIconTest extends TestCase
{
    use RefreshDatabase;

    private function userWithMailboxModule(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Module::firstOrCreate(
            ['key' => 'mailbox'],
            ['name' => 'Mailbox', 'is_core' => false],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('mailbox');

        return $user;
    }

    private function createEmail(Team $team, array $overrides = []): Email
    {
        $mailbox = Mailbox::factory()->create(['team_id' => $team->id]);

        return Email::factory()->create(array_merge([
            'team_id' => $team->id,
            'mailbox_id' => $mailbox->id,
        ], $overrides));
    }

    public function test_navbar_shows_unread_inbox_count_badge(): void
    {
        $user = $this->userWithMailboxModule();
        $team = $user->currentTeam;

        $this->createEmail($team, ['seen' => false, 'folder' => EmailFolder::Inbox]);
        $this->createEmail($team, ['seen' => false, 'folder' => EmailFolder::Inbox]);
        $this->createEmail($team, ['seen' => true, 'folder' => EmailFolder::Inbox]);

        Livewire::actingAs($user)
            ->test(MailNavbarIcon::class)
            ->assertSet('unreadCount', 2)
            ->assertSee('badge-notifications', false)
            ->assertSee('2', false);
    }

    public function test_navbar_hides_badge_when_inbox_has_no_unread_mail(): void
    {
        $user = $this->userWithMailboxModule();
        $team = $user->currentTeam;

        $this->createEmail($team, ['seen' => true, 'folder' => EmailFolder::Inbox]);

        Livewire::actingAs($user)
            ->test(MailNavbarIcon::class)
            ->assertSet('unreadCount', 0)
            ->assertDontSee('badge-notifications', false);
    }

    public function test_navbar_mail_icon_appears_on_dashboard_when_mailbox_module_enabled(): void
    {
        $user = $this->userWithMailboxModule();
        $team = $user->currentTeam;

        $this->createEmail($team, ['seen' => false, 'folder' => EmailFolder::Inbox]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeLivewire(MailNavbarIcon::class);
        $response->assertSee('badge-notifications', false);
    }
}
