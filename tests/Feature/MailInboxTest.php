<?php

namespace Tests\Feature;

use App\Enums\EmailFolder;
use App\Livewire\MailInbox;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\MailInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailInboxTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

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

    public function test_mail_list_page_loads_livewire_inbox(): void
    {
        $user = $this->userWithTeam();
        $this->createEmail($user->currentTeam);

        $response = $this->actingAs($user)->get(route('mail-list'));

        $response->assertOk();
        $response->assertSeeLivewire(MailInbox::class);
    }

    public function test_inbox_filters_by_folder(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $inbox = $this->createEmail($team, ['subject' => 'Inbox message', 'folder' => EmailFolder::Inbox]);
        $sent = $this->createEmail($team, ['subject' => 'Sent message', 'folder' => EmailFolder::Sent]);

        Livewire::actingAs($user)
            ->test(MailInbox::class, ['sources' => collect()])
            ->assertSee('Inbox message')
            ->assertDontSee('Sent message')
            ->call('setFolder', 'sent')
            ->assertDontSee('Inbox message')
            ->assertSee('Sent message');

        $this->assertDatabaseHas('emails', ['id' => $inbox->id, 'folder' => EmailFolder::Inbox->value]);
        $this->assertDatabaseHas('emails', ['id' => $sent->id, 'folder' => EmailFolder::Sent->value]);
    }

    public function test_search_filters_emails(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $this->createEmail($team, ['subject' => 'Invoice April']);
        $this->createEmail($team, ['subject' => 'Meeting notes']);

        Livewire::actingAs($user)
            ->test(MailInbox::class, ['sources' => collect()])
            ->set('search', 'Invoice')
            ->assertSee('Invoice April')
            ->assertDontSee('Meeting notes');
    }

    public function test_mark_selected_as_read(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['seen' => false]);

        Livewire::actingAs($user)
            ->test(MailInbox::class, ['sources' => collect()])
            ->set('selectedIds', [$email->id])
            ->call('markSelectedRead');

        $this->assertTrue($email->fresh()->seen);
    }

    public function test_delete_moves_to_trash(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam);

        Livewire::actingAs($user)
            ->test(MailInbox::class, ['sources' => collect()])
            ->set('selectedIds', [$email->id])
            ->call('deleteSelected');

        $this->assertSame(EmailFolder::Trash->value, $email->fresh()->folder->value);
    }

    public function test_pagination_label_reflects_total(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        for ($i = 0; $i < 12; $i++)
        {
            $this->createEmail($team, ['subject' => 'Mail '.$i]);
        }

        $service = app(MailInboxService::class);
        $paginator = $service->paginate($team, 'inbox', '', 1);

        $this->assertSame('1-10 of 12', $service->paginationLabel($paginator));
    }

    public function test_select_email_marks_as_read(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['seen' => false]);

        Livewire::actingAs($user)
            ->test(MailInbox::class, ['sources' => collect()])
            ->call('selectEmail', $email->id);

        $this->assertTrue($email->fresh()->seen);
    }
}
