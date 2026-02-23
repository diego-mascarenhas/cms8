<?php

namespace App\Services\Imap;

use App\Models\Email;
use App\Models\Mailbox;
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

        $folder = $client->getFolder($mailbox->folder ?? 'INBOX');
        if ($folder === null)
        {
            $client->disconnect();

            return 0;
        }

        $query = $folder->query()->all();
        if ($limit !== null)
        {
            $query->limit($limit);
        }
        $messages = $query->get();

        $count = 0;
        foreach ($messages as $message)
        {
            if ($message instanceof Message)
            {
                $this->persistMessage($mailbox, $message);
                $count++;
            }
        }

        $client->disconnect();

        return $count;
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

        return Email::updateOrCreate(
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
                'seen' => $message->hasFlag('Seen'),
                'flagged' => $message->hasFlag('Flagged'),
            ],
        );
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
