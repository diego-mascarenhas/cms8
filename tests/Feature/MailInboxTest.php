<?php

namespace Tests\Feature;

use App\Enums\EmailFolder;
use App\Livewire\MailInbox;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\MailInboxService;
use Carbon\Carbon;
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
            ->test(MailInbox::class)
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
            ->test(MailInbox::class)
            ->set('search', 'Invoice')
            ->assertSee('Invoice April')
            ->assertDontSee('Meeting notes');
    }

    public function test_mark_selected_as_read(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['seen' => false]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->set('selectedIds', [$email->id])
            ->call('markSelectedRead');

        $this->assertTrue($email->fresh()->seen);
    }

    public function test_delete_moves_to_trash(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
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
            $this->createEmail($team, [
                'subject' => 'Mail '.$i,
                'from_address' => 'sender'.$i.'@example.com',
            ]);
        }

        $service = app(MailInboxService::class);
        $paginator = $service->paginateGrouped($team, 'inbox', '', 1);

        $this->assertSame('1-10 of 12', $service->paginationLabel($paginator));
    }

    public function test_inbox_groups_emails_by_sender(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->createEmail($team, [
            'subject' => 'First from IDONEO',
            'from_address' => 'IDONEO <contabilidad@idoneo.es>',
            'message_date' => now()->subHours(2),
        ]);
        $this->createEmail($team, [
            'subject' => 'Second from IDONEO',
            'from_address' => 'IDONEO Contabilidad <contabilidad@idoneo.es>',
            'message_date' => now()->subHour(),
        ]);
        $this->createEmail($team, [
            'subject' => 'From another sender',
            'from_address' => 'Other <other@example.com>',
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->assertSee('IDONEO')
            ->assertSee('Second from IDONEO')
            ->assertSee('From another sender')
            ->assertSee('badge rounded-pill bg-label-primary', false)
            ->assertDontSee('First from IDONEO');

        $service = app(MailInboxService::class);
        $groups = $service->senderGroups($team, 'inbox', '');

        $this->assertSame(2, $groups->count());
        $idoneoGroup = $groups->firstWhere('sender_key', 'contabilidad@idoneo.es');
        $this->assertNotNull($idoneoGroup);
        $this->assertSame(2, $idoneoGroup['count']);
        $this->assertSame('Second from IDONEO', $idoneoGroup['subject']);
    }

    public function test_expand_sender_shows_thread_messages(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $first = $this->createEmail($team, [
            'subject' => 'Older message',
            'from_address' => 'Partner <partner@example.com>',
            'message_date' => now()->subDays(2),
        ]);
        $latest = $this->createEmail($team, [
            'subject' => 'Latest message',
            'from_address' => 'Partner <partner@example.com>',
            'message_date' => now()->subHour(),
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('toggleSenderExpand', 'partner@example.com')
            ->assertSet('expandedSenderKey', 'partner@example.com')
            ->assertSee('Older message')
            ->assertSee('Latest message')
            ->call('selectEmail', $latest->id)
            ->assertSet('selectedEmailId', $latest->id)
            ->assertSee('Conversación con Partner', false);
    }

    public function test_format_for_list_uses_system_datetime_format(): void
    {
        app()->setLocale('es_ES');
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00', config('app.timezone')));

        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, [
            'message_date' => Carbon::parse('2026-06-17 17:58:00', config('app.timezone')),
        ]);

        $formatted = app(MailInboxService::class)->formatForList($email->fresh());

        $this->assertSame('17 jun. 2026, 17:58', $formatted['date_display']);
        $this->assertSame('17:58', $formatted['date_list']);
        $this->assertSame('17 jun., 17:58', $formatted['date_short']);

        Carbon::setTestNow();
    }

    public function test_mark_group_unread_updates_all_emails_in_sender_group(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $first = $this->createEmail($team, [
            'from_address' => 'Partner <partner@example.com>',
            'seen' => true,
        ]);
        $second = $this->createEmail($team, [
            'from_address' => 'Partner <partner@example.com>',
            'seen' => true,
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('markGroupUnread', [$first->id, $second->id])
            ->assertSet('statusMessage', __('Marcados como no leídos.'));

        $this->assertFalse($first->fresh()->seen);
        $this->assertFalse($second->fresh()->seen);
    }

    public function test_archive_folder_lists_archived_emails(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $archived = $this->createEmail($team, [
            'subject' => 'Archived message',
            'folder' => EmailFolder::Archive,
        ]);
        $this->createEmail($team, [
            'subject' => 'Inbox message',
            'folder' => EmailFolder::Inbox,
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('setFolder', 'archive')
            ->assertSee('Archived message')
            ->assertDontSee('Inbox message');

        $this->assertSame($archived->id, Email::query()->where('team_id', $team->id)->where('folder', EmailFolder::Archive->value)->value('id'));
    }

    public function test_mark_selected_read_without_selection_marks_all_unread_in_folder(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->createEmail($team, ['seen' => false, 'from_address' => 'a@example.com']);
        $this->createEmail($team, ['seen' => false, 'from_address' => 'b@example.com']);
        $this->createEmail($team, ['seen' => true, 'from_address' => 'c@example.com']);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('markSelectedRead')
            ->assertSet('statusMessage', __('Marcados como leídos.'));

        $this->assertSame(
            0,
            Email::query()
                ->where('team_id', $team->id)
                ->where('folder', EmailFolder::Inbox->value)
                ->where('seen', false)
                ->count(),
        );
    }

    public function test_select_email_marks_as_read(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['seen' => false]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('selectEmail', $email->id);

        $this->assertTrue($email->fresh()->seen);
    }

    public function test_move_selected_to_inbox_from_trash(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['folder' => EmailFolder::Trash]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->set('folder', 'trash')
            ->set('selectedIds', [$email->id])
            ->call('moveSelectedToInbox');

        $this->assertSame(EmailFolder::Inbox->value, $email->fresh()->folder->value);
    }

    public function test_move_selected_to_spam_and_back_to_inbox(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, ['folder' => EmailFolder::Inbox]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->set('selectedIds', [$email->id])
            ->call('moveSelectedToSpam');

        $this->assertSame(EmailFolder::Spam->value, $email->fresh()->folder->value);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->set('folder', 'spam')
            ->set('selectedIds', [$email->id])
            ->call('moveSelectedFromSpam');

        $this->assertSame(EmailFolder::Inbox->value, $email->fresh()->folder->value);
    }

    public function test_spam_folder_shows_not_spam_toolbar_icon(): void
    {
        $user = $this->userWithTeam();

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->set('folder', 'spam')
            ->assertSee('ti-inbox', false)
            ->assertDontSee('wire:click="moveSelectedToSpam"', false);
    }

    public function test_inbox_shows_mark_as_spam_toolbar_icon(): void
    {
        $user = $this->userWithTeam();

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->assertSee('ti-shield-x', false)
            ->assertSee('wire:click="moveSelectedToSpam"', false);
    }
}
