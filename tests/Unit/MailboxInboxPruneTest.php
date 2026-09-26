<?php

namespace Tests\Unit;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use App\Services\Imap\MailboxConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailboxInboxPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_local_inbox_emails_missing_from_server(): void
    {
        $team = Team::factory()->create();
        $mailbox = Mailbox::factory()->create(['team_id' => $team->id]);

        $keep = Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $mailbox->id,
            'folder' => EmailFolder::Inbox,
            'message_id' => 'keep@example.com',
            'seen' => false,
        ]);
        $stale = Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $mailbox->id,
            'folder' => EmailFolder::Inbox,
            'message_id' => 'stale@example.com',
            'seen' => false,
        ]);
        $otherFolder = Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $mailbox->id,
            'folder' => EmailFolder::Sent,
            'message_id' => 'sent@example.com',
            'seen' => false,
        ]);

        $pruned = app(MailboxConnectionService::class)->pruneLocalFolderEmailsNotOnServer(
            $mailbox,
            EmailFolder::Inbox,
            ['keep@example.com'],
        );

        $this->assertSame(1, $pruned);
        $this->assertDatabaseHas('emails', ['id' => $keep->id]);
        $this->assertDatabaseMissing('emails', ['id' => $stale->id]);
        $this->assertDatabaseHas('emails', ['id' => $otherFolder->id]);
    }

    public function test_prune_with_empty_server_set_clears_local_inbox_for_mailbox(): void
    {
        $team = Team::factory()->create();
        $mailbox = Mailbox::factory()->create(['team_id' => $team->id]);

        Email::factory()->count(3)->create([
            'team_id' => $team->id,
            'mailbox_id' => $mailbox->id,
            'folder' => EmailFolder::Inbox,
            'seen' => false,
        ]);

        $pruned = app(MailboxConnectionService::class)->pruneLocalFolderEmailsNotOnServer(
            $mailbox,
            EmailFolder::Inbox,
            [],
        );

        $this->assertSame(3, $pruned);
        $this->assertSame(0, Email::query()->where('mailbox_id', $mailbox->id)->where('folder', 'inbox')->count());
    }
}
