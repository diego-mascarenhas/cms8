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
     * Fetch INBOX emails using PHP imap_* functions (no Webklex dependency).
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
            } else {
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
            $body = '';
            if (function_exists('imap_fetchbody'))
            {
                $rawBody = (string) imap_fetchbody($connection, $msgNo, 0);
                $encoding = 0;
                $structure = @imap_fetchstructure($connection, $msgNo);
                if (is_object($structure) && isset($structure->encoding))
                {
                    $encoding = $structure->encoding;
                }
                $body = $this->decodeImapString($rawBody, $encoding);
            }

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

            $emailCollection->push([
                'message_id' => $msg->message_id ?? '',
                'subject' => $msg->subject ?? '',
                'from' => $fromStr,
                'date' => $dateStr,
                'body' => $body,
                'attachments' => [],
            ]);
        }

        imap_close($connection);

        $emailCollection = $emailCollection->reverse()->values();

        Log::info('Successfully retrieved emails', [
            'team_id' => $team->id,
            'count' => $emailCollection->count(),
        ]);

        return $emailCollection;
    }

    /**
     * Decode IMAP body string according to encoding.
     *
     * @param  int  $encoding  IMAP encoding (0=7bit, 1=8bit, 2=binary, 3=base64, 4=quoted-printable, 5=other)
     */
    private function decodeImapString(string $str, int $encoding): string
    {
        return match ($encoding)
        {
            3 => (string) base64_decode($str, true),
            4 => (string) quoted_printable_decode($str),
            default => $str,
        };
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
