<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Services\Imap\MailboxConnectionService;
use PHPUnit\Framework\TestCase;

class MailboxConnectionSeenSyncTest extends TestCase
{
    public function test_new_email_uses_imap_seen_flag(): void
    {
        $this->assertFalse(MailboxConnectionService::resolveSeenForSync(null, false));
        $this->assertTrue(MailboxConnectionService::resolveSeenForSync(null, true));
    }

    public function test_existing_locally_read_email_stays_read_when_imap_is_unseen(): void
    {
        $email = new Email(['seen' => true]);

        $this->assertTrue(MailboxConnectionService::resolveSeenForSync($email, false));
    }

    public function test_existing_unread_email_becomes_read_when_imap_is_seen(): void
    {
        $email = new Email(['seen' => false]);

        $this->assertTrue(MailboxConnectionService::resolveSeenForSync($email, true));
    }

    public function test_existing_unread_email_stays_unread_when_imap_is_unseen(): void
    {
        $email = new Email(['seen' => false]);

        $this->assertFalse(MailboxConnectionService::resolveSeenForSync($email, false));
    }
}
