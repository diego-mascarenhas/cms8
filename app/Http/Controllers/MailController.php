<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMailboxEmails;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Source;
use App\Services\Imap\MailboxConnectionService;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class MailController extends Controller
{
    public function index(): View
    {
        $sources = Source::all();
        $emails = $this->getEmailsFromDatabase();

        if ($emails->isEmpty() && class_exists(\Webklex\PHPIMAP\ClientManager::class))
        {
            try
            {
                $mailboxService = app(MailboxConnectionService::class);
                $emails = $this->syncFirstMailboxIfAvailable($mailboxService);
            } catch (\Throwable $e)
            {
                Log::warning('Mail list: sync on empty failed: '.$e->getMessage());
            }
        }

        return view('mail.index', compact('sources', 'emails'));
    }

    /**
     * Dispatch sync jobs for current team's mailboxes and redirect to mail list.
     */
    public function sync(): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('mail-list')->with('mail_error', __('No hay equipo seleccionado.'));
        }

        $mailboxes = $team->mailboxes()->get();
        if ($mailboxes->isEmpty())
        {
            return redirect()->route('mail-list')->with('mail_error', __('No hay casillas configuradas. Añade una en Gestionar casillas.'));
        }

        foreach ($mailboxes as $mailbox)
        {
            SyncMailboxEmails::dispatch($mailbox);
        }

        return redirect()->route('mail-list')->with('mail_success', __('Sincronización en segundo plano. La lista se actualizará al recargar.'));
    }

    /**
     * When DB has no emails, run sync once for the first team mailbox (foreground) so the list shows after reload.
     *
     * @return Collection<int, array{message_id: string, subject: string, from: string, date: string, body: string, attachments: array}>
     */
    private function syncFirstMailboxIfAvailable(MailboxConnectionService $mailboxService): Collection
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return collect();
        }

        $mailbox = Mailbox::where('team_id', $team->id)->orderBy('name')->first();
        if (! $mailbox)
        {
            return collect();
        }

        try
        {
            $mailboxService->syncMessages($mailbox);
        } catch (ConnectionFailedException $e)
        {
            Log::warning('Mail list: sync on empty failed: '.$e->getMessage());

            return collect();
        }

        return $this->getEmailsFromDatabase();
    }

    /**
     * Load emails from database (synced via Webklex), formatted for the mail-inbox Livewire component.
     *
     * @return Collection<int, array{message_id: string, subject: string, from: string, date: string, body: string, attachments: array}>
     */
    private function getEmailsFromDatabase(): Collection
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return collect();
        }

        return Email::query()
            ->where('team_id', $team->id)
            ->orderByDesc('message_date')
            ->get()
            ->map(fn (Email $email) => [
                'message_id' => $email->message_id,
                'subject' => $email->subject ?? '',
                'from' => $email->from_address,
                'date' => $email->message_date?->format('r') ?? '',
                'body' => $email->body_html ?: $email->body_text ?: '',
                'attachments' => [],
            ])
            ->values();
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
