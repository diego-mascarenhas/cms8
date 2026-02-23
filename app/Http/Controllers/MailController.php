<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\Source;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MailController extends Controller
{
    public function index()
    {
        $sources = Source::all();

        try
        {
            $emails = $this->mailbox();
        } catch (\Exception $e)
        {
            Log::error('Error getting emails: '.$e->getMessage());
            $emails = collect();
        }

        return view('mail.index', compact('sources', 'emails'));
    }

    /**
     * Get IMAP config: prefer first team mailbox (same as "Probar conexión"), then team settings.
     *
     * @return array{host: string, port: int|string, encryption: string, username: string, password: string}|null
     */
    private function getImapConfig(): ?array
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return null;
        }

        $mailbox = $team->mailboxes()->orderBy('name')->first();
        if ($mailbox)
        {
            return [
                'host' => $mailbox->host,
                'port' => $mailbox->port,
                'encryption' => $mailbox->encryption ?? 'ssl',
                'username' => $mailbox->username,
                'password' => $mailbox->password ?? '',
            ];
        }

        $imapHost = $team->getSetting('imap_host') ?: env('MAILBOX_HOST');
        $imapUsername = $team->getSetting('imap_username') ?: env('MAILBOX_USERNAME');
        $imapPassword = $team->getSetting('imap_password') ?: env('MAILBOX_PASSWORD');
        $imapPort = $team->getSetting('imap_port') ?: env('MAILBOX_PORT', 993);
        $imapEncryption = $team->getSetting('imap_encryption') ?: env('MAILBOX_ENCRYPTION', 'ssl');

        if (! empty($imapHost) && ! empty($imapUsername) && $imapPassword !== null && $imapPassword !== '')
        {
            return [
                'host' => $imapHost,
                'port' => $imapPort,
                'encryption' => $imapEncryption ?? 'ssl',
                'username' => $imapUsername,
                'password' => $imapPassword,
            ];
        }

        return null;
    }

    /**
     * Fetch INBOX emails. Uses webklex/php-imap when available, else native imap_*.
     *
     * @return Collection<int, array{message_id: string, subject: string, from: string, date: string, body: string, attachments: array}>
     */
    public function mailbox(): Collection
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            Log::warning('No current team found for user');

            return collect();
        }

        $config = $this->getImapConfig();
        if (! $config)
        {
            Log::warning('IMAP configuration incomplete: no team settings and no mailbox', ['team_id' => $team->id]);

            return collect();
        }

        if (class_exists(\Webklex\PHPIMAP\ClientManager::class))
        {
            return $this->mailboxViaWebklex($config, $team);
        }

        return $this->mailboxViaNativeImap($config, $team);
    }

    /**
     * Fetch INBOX using webklex/php-imap. MIME decoding and body handled by the package.
     */
    private function mailboxViaWebklex(array $config, $team): Collection
    {
        try
        {
            $clientConfig = [
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'] ?? 'ssl',
                'validate_cert' => env('MAILBOX_VALIDATE_CERT', false),
                'username' => $config['username'],
                'password' => $config['password'],
                'protocol' => 'imap',
            ];

            $cm = new \Webklex\PHPIMAP\ClientManager;
            $client = $cm->make($clientConfig);
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $messages = $folder->messages()->all()->get();

            $emailCollection = collect();
            foreach ($messages as $message)
            {
                $from = $message->getFrom();
                $fromStr = is_object($from) ? (string) $from : (string) $from;

                $emailCollection->push([
                    'message_id' => $message->getMessageId() ?? '',
                    'subject' => $message->getSubject() ?? '',
                    'from' => $fromStr,
                    'date' => $message->getDate() ? $message->getDate()->format('r') : '',
                    'body' => $message->getHTMLBody() ?: $message->getTextBody() ?: '',
                    'attachments' => [],
                ]);
            }

            $emailCollection = $emailCollection->reverse()->values();

            Log::info('Successfully retrieved emails (Webklex)', ['team_id' => $team->id, 'count' => $emailCollection->count()]);

            return $emailCollection;
        } catch (\Exception $e)
        {
            Log::error('IMAP (Webklex) failed: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'AUTHENTICATE') || str_contains($e->getMessage(), 'authentication'))
            {
                session()->flash('mail_error', __('Error de autenticación IMAP. Comprueba usuario y contraseña en Team Settings → Email o en Gestionar casillas.'));
            } else
            {
                session()->flash('mail_error', __('No se pudo conectar al servidor IMAP.').' '.$e->getMessage());
            }

            return collect();
        }
    }

    /**
     * Fetch INBOX using native PHP imap_* (fallback).
     */
    private function mailboxViaNativeImap(array $config, $team): Collection
    {
        $imapHost = $config['host'];
        $imapPort = $config['port'];
        $imapEncryption = $config['encryption'];
        $imapUsername = $config['username'];
        $imapPassword = $config['password'];

        $connectionString = "{{$imapHost}:{$imapPort}/imap";
        if ($imapEncryption === 'ssl')
        {
            $connectionString .= '/ssl';
        } elseif ($imapEncryption === 'tls')
        {
            $connectionString .= '/tls';
        }
        $connectionString .= '/novalidate-cert}INBOX';

        $connection = @imap_open($connectionString, $imapUsername, $imapPassword);

        if (! $connection)
        {
            $err = imap_last_error();
            Log::error('IMAP connection failed: '.$err);
            if (str_contains((string) $err, 'AUTHENTICATE failed') || str_contains((string) $err, 'authentication'))
            {
                session()->flash('mail_error', __('Error de autenticación IMAP. Comprueba usuario y contraseña en Team Settings → Email o en Gestionar casillas.'));
            } else
            {
                session()->flash('mail_error', __('No se pudo conectar al servidor IMAP.').' '.$err);
            }

            return collect();
        }

        $emailCollection = collect();
        $numMessages = (int) imap_num_msg($connection);

        if ($numMessages < 1)
        {
            imap_close($connection);

            return $emailCollection;
        }

        $overview = imap_fetch_overview($connection, "1:{$numMessages}", 0);

        if (! is_array($overview))
        {
            imap_close($connection);

            return $emailCollection;
        }

        foreach ($overview as $i => $msg)
        {
            $msgNo = $msg->msgno ?? ($i + 1);
            $body = $this->fetchMessageBody($connection, $msgNo);

            $dateStr = $msg->date ?? '';
            if ($dateStr === '')
            {
                $dateStr = date('r', time());
            }

            $fromStr = $msg->from ?? '';
            if (is_object($fromStr))
            {
                $fromStr = (string) $fromStr;
            }

            $subjectStr = $msg->subject ?? '';

            $emailCollection->push([
                'message_id' => $msg->message_id ?? '',
                'subject' => $this->decodeMimeHeader($subjectStr),
                'from' => $this->decodeMimeHeader($fromStr),
                'date' => $dateStr,
                'body' => $body,
                'attachments' => [],
            ]);
        }

        imap_close($connection);

        $emailCollection = $emailCollection->reverse()->values();

        Log::info('Successfully retrieved emails (native IMAP)', [
            'team_id' => $team->id,
            'count' => $emailCollection->count(),
        ]);

        return $emailCollection;
    }

    /**
     * Fetch readable body (text/plain or text/html) from a message using its structure.
     */
    private function fetchMessageBody($connection, int $msgNo): string
    {
        $structure = @imap_fetchstructure($connection, $msgNo);
        if (! is_object($structure))
        {
            $raw = (string) imap_body($connection, $msgNo);

            return $this->decodeImapPart($raw, 0);
        }

        return $this->fetchBodyFromStructure($connection, $msgNo, $structure, '1');
    }

    /**
     * Recursively get text/plain or text/html from MIME structure.
     *
     * @param  object  $structure  imap_fetchstructure result
     * @param  string  $prefix  IMAP section prefix (e.g. "1", "1.1", "2")
     */
    private function fetchBodyFromStructure($connection, int $msgNo, object $structure, string $prefix): string
    {
        $encoding = $structure->encoding ?? 0;
        $subtype = strtoupper($structure->subtype ?? '');
        $type = strtoupper($structure->type ?? '');

        if (isset($structure->parts) && count($structure->parts) > 0)
        {
            $html = '';
            $plain = '';
            foreach ($structure->parts as $idx => $part)
            {
                $section = $prefix === '1' ? (string) ($idx + 1) : $prefix.'.'.($idx + 1);
                $partType = strtoupper($part->subtype ?? '');
                $partMain = (int) ($part->type ?? 0);
                if ($partMain === 0 && $partType === 'HTML')
                {
                    $html = $this->fetchBodyFromStructure($connection, $msgNo, $part, $section);
                } elseif ($partMain === 0 && ($partType === 'PLAIN' || $partType === ''))
                {
                    $plain = $this->fetchBodyFromStructure($connection, $msgNo, $part, $section);
                } elseif (isset($part->parts))
                {
                    $inner = $this->fetchBodyFromStructure($connection, $msgNo, $part, $section);
                    if (str_contains(strtoupper($part->subtype ?? ''), 'HTML'))
                    {
                        $html = $inner;
                    } else
                    {
                        $plain = $inner ?: $plain;
                    }
                }
            }

            return $html !== '' ? $html : $plain;
        }

        $raw = (string) imap_fetchbody($connection, $msgNo, $prefix);

        return $this->decodeImapPart($raw, $encoding);
    }

    /**
     * Decode IMAP body string according to encoding.
     *
     * @param  int  $encoding  IMAP encoding (0=7bit, 1=8bit, 2=binary, 3=base64, 4=quoted-printable, 5=other)
     */
    private function decodeImapString(string $str, int $encoding): string
    {
        return $this->decodeImapPart($str, $encoding);
    }

    private function decodeImapPart(string $str, int $encoding): string
    {
        $decoded = match ($encoding)
        {
            3 => (string) base64_decode($str, true),
            4 => (string) quoted_printable_decode($str),
            default => $str,
        };

        return $decoded !== '' ? $decoded : $str;
    }

    /**
     * Decode MIME encoded-words (RFC 2047) in headers, e.g. =?UTF-8?Q?Gast=C3=B3n?= → Gastón.
     * Uses imap_utf8() when available, then iconv_mime_decode() as fallback.
     */
    private function decodeMimeHeader(string $str): string
    {
        if ($str === '' || ! str_contains($str, '=?'))
        {
            return $str;
        }

        if (function_exists('imap_utf8'))
        {
            $decoded = @imap_utf8($str);
            if ($decoded !== false && $decoded !== '')
            {
                return $decoded;
            }
        }

        if (function_exists('iconv_mime_decode'))
        {
            $decoded = @iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false)
            {
                return $decoded;
            }
        }

        return $str;
    }

    public function handleIncomingEmail(InboundEmail $email)
    {
        try
        {
            Log::info('Correo recibido:', [
                'asunto' => $email->subject(),
                'de' => $email->from(),
                'contenido' => $email->text(),
            ]);

            $inboundEmail = InboundEmail::fromMessage($email->message);
            $inboundEmail->save();

            Log::info('Email guardado correctamente en la base de datos con ID: '.$inboundEmail->id);

            return true;
        } catch (\Exception $e)
        {
            Log::error('Error al procesar el email: '.$e->getMessage());

            return false;
        }
    }
}
