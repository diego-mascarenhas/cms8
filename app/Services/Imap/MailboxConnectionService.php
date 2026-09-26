<?php

namespace App\Services\Imap;

use App\Enums\EmailFolder;
use App\Jobs\ClassifyEmailSpamJob;
use App\Models\Email;
use App\Models\Mailbox;
use App\Services\Mail\MailInboxService;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Message;

class MailboxConnectionService
{
    public function __construct() {}

    /**
     * Test connection to the mailbox without persisting messages.
     *
     * @throws ConnectionFailedException
     */
    public function testConnection(Mailbox $mailbox): bool
    {
        $client = $this->createClient($mailbox);
        $client->connect();
        $folder = $client->getFolder($mailbox->folder ?? 'INBOX');
        $client->disconnect();

        return $folder !== null;
    }

    /**
     * Sync messages from the mailbox into the database.
     * Creates or updates Email records; avoids duplicates by mailbox_id + message_id.
     *
     * @return int Number of emails created or updated
     *
     * @throws ConnectionFailedException
     */
    public function syncMessages(Mailbox $mailbox, ?int $limit = null): int
    {
        $client = $this->createClient($mailbox);
        $client->connect();

        $imapFolderName = $mailbox->folder ?? 'INBOX';
        $folder = $client->getFolder($imapFolderName);
        if ($folder === null)
        {
            $client->disconnect();

            return 0;
        }

        $status = $folder->examine();
        $exists = (int) (is_array($status) ? ($status['exists'] ?? 0) : ($status->exists ?? 0));

        // Newest first so limited refreshes still pick up recent mail + Seen flags from other clients.
        $query = $folder->query()->all()->leaveUnread()->setFetchOrderDesc();
        if ($limit !== null)
        {
            $query->limit($limit);
        }
        $messages = $query->get();

        $count = 0;
        $syncedMessageIds = [];
        foreach ($messages as $message)
        {
            if (! $message instanceof Message)
            {
                continue;
            }
            try
            {
                $email = $this->persistMessage($mailbox, $message);
                $syncedMessageIds[] = $email->message_id;
                $count++;
            } catch (\Throwable $e)
            {
                Log::warning('Mail sync: skip message due to error', [
                    'mailbox_id' => $mailbox->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $client->disconnect();

        $fetchedCompleteFolder = $limit === null
            || $exists <= $count
            || ($limit !== null && $exists <= $limit);

        if ($fetchedCompleteFolder)
        {
            $localFolder = $this->localFolderForImapName($imapFolderName);
            $pruned = $this->pruneLocalFolderEmailsNotOnServer($mailbox, $localFolder, $syncedMessageIds);
            if ($pruned > 0)
            {
                Log::info('Mail sync pruned local emails missing from IMAP', [
                    'mailbox_id' => $mailbox->id,
                    'folder' => $localFolder->value,
                    'pruned' => $pruned,
                    'imap_exists' => $exists,
                ]);
            }
        }

        return $count;
    }

    /**
     * Remove local copies that no longer exist in the IMAP folder (stale DB / dump leftovers).
     *
     * @param  list<string>  $serverMessageIds
     */
    public function pruneLocalFolderEmailsNotOnServer(Mailbox $mailbox, EmailFolder $folder, array $serverMessageIds): int
    {
        $serverMessageIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id, " \t\n\r\0\x0B<>"),
            $serverMessageIds,
        ))));

        $query = Email::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('folder', $folder->value);

        if ($serverMessageIds !== [])
        {
            $query->whereNotIn('message_id', $serverMessageIds);
        }

        return (int) $query->delete();
    }

    private function localFolderForImapName(string $imapFolderName): EmailFolder
    {
        $normalized = strtolower(trim($imapFolderName));

        return match (true)
        {
            $normalized === 'inbox' || str_ends_with($normalized, 'inbox') => EmailFolder::Inbox,
            str_contains($normalized, 'sent') => EmailFolder::Sent,
            str_contains($normalized, 'draft') => EmailFolder::Draft,
            str_contains($normalized, 'junk') || str_contains($normalized, 'spam') => EmailFolder::Spam,
            str_contains($normalized, 'trash') || str_contains($normalized, 'deleted') => EmailFolder::Trash,
            str_contains($normalized, 'archive') => EmailFolder::Archive,
            default => EmailFolder::Inbox,
        };
    }

    /**
     * Push local read/unread state to IMAP so Apple Mail / other clients stay aligned.
     *
     * @param  list<string>  $messageIds  RFC Message-ID values (without requiring angle brackets)
     */
    public function applySeenFlags(Mailbox $mailbox, array $messageIds, bool $seen): int
    {
        $messageIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id, " \t\n\r\0\x0B<>"),
            $messageIds,
        ))));

        if ($messageIds === [])
        {
            return 0;
        }

        try
        {
            $client = $this->createClient($mailbox);
            $client->connect();
            $folder = $client->getFolder($mailbox->folder ?? 'INBOX');
            if ($folder === null)
            {
                $client->disconnect();

                return 0;
            }

            $updated = 0;
            foreach ($messageIds as $messageId)
            {
                try
                {
                    $messages = $folder->query()->leaveUnread()->whereMessageId($messageId)->get();
                    foreach ($messages as $message)
                    {
                        if (! $message instanceof Message)
                        {
                            continue;
                        }

                        if ($seen)
                        {
                            $message->setFlag('Seen');
                        } else
                        {
                            $message->unsetFlag('Seen');
                        }
                        $updated++;
                    }
                } catch (\Throwable $e)
                {
                    Log::warning('Mail IMAP flag update failed for message', [
                        'mailbox_id' => $mailbox->id,
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $client->disconnect();

            return $updated;
        } catch (\Throwable $e)
        {
            Log::warning('Mail IMAP flag sync skipped', [
                'mailbox_id' => $mailbox->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    protected function createClient(Mailbox $mailbox): Client
    {
        $accountConfig = [
            'host' => $mailbox->host,
            'port' => $mailbox->port,
            'protocol' => $mailbox->protocol ?? 'imap',
            'encryption' => $mailbox->encryption ?? 'ssl',
            'validate_cert' => env('MAILBOX_VALIDATE_CERT', false),
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'authentication' => null,
            'proxy' => [
                'socket' => null,
                'request_fulluri' => false,
                'username' => null,
                'password' => null,
            ],
            'timeout' => 30,
            'extensions' => [],
        ];

        $clientManager = new \Webklex\PHPIMAP\ClientManager;

        return $clientManager->make($accountConfig);
    }

    protected function persistMessage(Mailbox $mailbox, Message $message): Email
    {
        $messageId = $this->getMessageIdString($message);
        $from = $this->getAddressString($message->getFrom());
        $to = $this->getAddressString($message->getTo());
        $dateAttr = $message->getDate();
        $carbonDate = $dateAttr && method_exists($dateAttr, 'toDate') ? $dateAttr->toDate() : null;

        $inboxService = app(MailInboxService::class);
        $folder = $inboxService->detectFolderForIncoming($from ?: '', $mailbox->username ?? '');

        $existing = Email::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('message_id', $messageId)
            ->first();

        $folderValue = $existing?->folder instanceof EmailFolder
            ? $existing->folder->value
            : ($existing?->folder ?? $folder->value);

        $imapSeen = $message->hasFlag('Seen');
        $seen = self::resolveSeenForSync($existing, $imapSeen);

        $email = Email::updateOrCreate(
            [
                'mailbox_id' => $mailbox->id,
                'message_id' => $messageId,
            ],
            [
                'team_id' => $mailbox->team_id,
                'subject' => (string) $message->getSubject(),
                'body_text' => $message->getTextBody() ?: null,
                'body_html' => $message->getHTMLBody() ?: null,
                'from_address' => $from ?: 'unknown',
                'to_address' => $to,
                'message_date' => $carbonDate,
                'seen' => $seen,
                'flagged' => $message->hasFlag('Flagged'),
                'folder' => $folderValue,
            ],
        );

        if ($email->wasRecentlyCreated)
        {
            try
            {
                ClassifyEmailSpamJob::dispatch($email->id);
            } catch (\Throwable $e)
            {
                Log::warning('Mail sync: could not dispatch spam classification job', ['email_id' => $email->id, 'error' => $e->getMessage()]);
            }
        }

        return $email;
    }

    /**
     * Local mark-as-read must survive IMAP sync until the server flag is also Seen.
     */
    public static function resolveSeenForSync(?Email $existing, bool $imapSeen): bool
    {
        if ($existing === null)
        {
            return $imapSeen;
        }

        return (bool) $existing->seen || $imapSeen;
    }

    protected function getMessageIdString(Message $message): string
    {
        $attr = $message->getMessageId();
        if ($attr === null)
        {
            return 'uid-'.$message->getUid();
        }
        $raw = is_object($attr) && method_exists($attr, 'getRaw') ? $attr->getRaw() : (string) $attr;

        return trim($raw, '<>') ?: 'uid-'.$message->getUid();
    }

    /**
     * @param  mixed  $attribute  From/To attribute (can be array or single)
     */
    protected function getAddressString(mixed $attribute): ?string
    {
        if ($attribute === null)
        {
            return null;
        }
        if (is_array($attribute))
        {
            $parts = [];
            foreach ($attribute as $addr)
            {
                $parts[] = is_object($addr) && method_exists($addr, 'mail') ? $addr->mail : (string) $addr;
            }

            return implode(', ', $parts);
        }

        return is_object($attribute) && method_exists($attribute, 'mail') ? $attribute->mail : (string) $attribute;
    }
}
