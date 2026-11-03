<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMailboxEmails;
use App\Mail\ComposeMessage;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Prompt;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\Imap\MailboxConnectionService;
use App\Services\UserResolverService;
use App\Traits\ConfiguresTeamMail;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class MailController extends Controller
{
    use ConfiguresTeamMail;

    public function index(): View
    {
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

        $mailComposePrefill = null;
        if (request()->boolean('compose'))
        {
            $to = (string) request()->query('to', '');
            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL))
            {
                $mailComposePrefill = [
                    'email' => $to,
                    'name' => (string) request()->query('name', ''),
                ];
            }
        }

        $mailComposeContactId = null;
        if (auth()->check() && auth()->user()->currentTeam && request()->filled('contact_id'))
        {
            $cid = (int) request()->query('contact_id');
            if ($cid > 0 && Contact::withoutGlobalScopes()
                ->where('team_id', auth()->user()->current_team_id)
                ->whereKey($cid)
                ->exists())
            {
                $mailComposeContactId = $cid;
            }
        }

        $assistantFlowPrompts = collect();
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $assistantFlowPrompts = Prompt::forTeam((int) auth()->user()->current_team_id)
                ->active()
                ->with('module')
                ->where('section_key', '!=', 'general')
                ->orderBy('order')
                ->get()
                ->map(fn (Prompt $p) => [
                    'routing_key' => $p->module
                        ? $p->module->key.':'.$p->section_key
                        : $p->section_key,
                    'section_label' => $p->section_label,
                ]);
        }

        return view('mail.index', compact('mailComposePrefill', 'mailComposeContactId', 'assistantFlowPrompts'));
    }

    /**
     * Suggest email body for the compose modal using the same assistant + flow prompts as chat (does not persist to agent_conversations).
     */
    public function suggestComposeBody(
        Request $request,
        UserResolverService $userResolver,
        AgentConversationContextService $contextService,
        ChatAssistantReplyService $replyService,
    ): JsonResponse {
        if (! auth()->check())
        {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => __('No hay equipo seleccionado.')], 403);
        }

        $request->validate([
            'flow_routing_key' => 'nullable|string|max:512',
            'hint' => 'nullable|string|max:4000',
            'contact_id' => 'nullable|integer',
            'recipient_summary' => 'nullable|string|max:2000',
        ]);

        $teamId = (int) $team->id;
        $flowKeyRaw = $request->input('flow_routing_key');
        $flowKey = is_string($flowKeyRaw) ? trim($flowKeyRaw) : '';
        $flowKey = $flowKey !== '' ? $flowKey : null;

        $hint = trim((string) $request->input('hint', ''));
        $recipientSummary = trim((string) $request->input('recipient_summary', ''));

        $contextUser = null;
        $customerPhone = null;

        if ($request->filled('contact_id'))
        {
            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->whereKey((int) $request->input('contact_id'))
                ->first();

            if (! $contact)
            {
                return response()->json(['success' => false, 'message' => __('Invalid contact.')], 404);
            }

            $contextUser = $userResolver->resolveUserForConversation(null, (int) $contact->id);
            if ($contact->phone)
            {
                $customerPhone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
                if ($customerPhone === '')
                {
                    $customerPhone = null;
                }
            }
        }

        if ($contextUser === null)
        {
            $contextUser = auth()->user();
        }

        $history = $contextService->getHistoryForPrompt($contextUser->id, AgentConversationContextService::DEFAULT_HISTORY_LIMIT);
        $instruction = $this->buildMailComposeAssistantInstruction($hint, $recipientSummary);

        $replyResponse = $replyService->getReply(
            $instruction,
            $history,
            $teamId,
            true,
            $contextUser->id,
            $customerPhone,
            $flowKey,
            $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
            false,
        );

        if (! $replyResponse['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $replyResponse['message'] ?? __('Error'),
            ], 500);
        }

        $suggestion = $this->parseMailComposeSuggestion((string) ($replyResponse['text'] ?? ''));

        return response()->json([
            'success' => true,
            'subject' => $suggestion['subject'],
            'body' => $suggestion['body'],
            'response' => $suggestion['body'],
        ]);
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function parseMailComposeSuggestion(string $text): array
    {
        $raw = trim($text);
        if ($raw === '')
        {
            return ['subject' => '', 'body' => ''];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded))
        {
            $subject = isset($decoded['subject']) && is_string($decoded['subject']) ? trim($decoded['subject']) : '';
            $body = isset($decoded['body']) && is_string($decoded['body']) ? trim($decoded['body']) : '';

            if ($subject !== '' || $body !== '')
            {
                return ['subject' => $subject, 'body' => $body];
            }
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/m', $raw, $matches))
        {
            $raw = trim($matches[1]);
            $decoded = json_decode($raw, true);
            if (is_array($decoded))
            {
                $subject = isset($decoded['subject']) && is_string($decoded['subject']) ? trim($decoded['subject']) : '';
                $body = isset($decoded['body']) && is_string($decoded['body']) ? trim($decoded['body']) : '';

                if ($subject !== '' || $body !== '')
                {
                    return ['subject' => $subject, 'body' => $body];
                }
            }
        }

        return ['subject' => '', 'body' => $raw];
    }

    private function buildMailComposeAssistantInstruction(string $hint, string $recipientSummary): string
    {
        $parts = [
            'You are helping the operator reply to an email already open in the composer.',
            'Reply with a single JSON object only (no markdown fences, no commentary). Keys: "subject" (short reply subject) and "body" (the new reply only: greeting, paragraphs, closing).',
            'The body must be a new message. Do not repeat the quoted original, the separator, or the headers From/Date/Subject.',
            'If the message is in Spanish, write the reply in Argentine voseo. Otherwise answer in the language of the message.',
        ];

        if ($recipientSummary !== '')
        {
            $parts[] = 'Recipient(s) / To field: '.$recipientSummary;
        }

        if ($hint !== '')
        {
            $parts[] = 'Message to answer (write a reply, do not copy it): '.$hint;
        } else
        {
            $parts[] = 'The message body is empty; infer subject and body from the selected flow (if any) and conversation context.';
        }

        return implode("\n\n", $parts);
    }

    public function sendCompose(Request $request): JsonResponse
    {
        if (! auth()->check())
        {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => __('No hay equipo seleccionado.')], 403);
        }

        $data = $request->validate([
            'to' => 'required|array|min:1|max:20',
            'to.*' => 'required|email',
            'cc' => 'nullable|array|max:20',
            'cc.*' => 'email',
            'bcc' => 'nullable|array|max:20',
            'bcc.*' => 'email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $this->configureMailForTeam($team);

        $body = trim($data['body']);
        $pending = Mail::to($data['to']);
        $cc = array_values(array_filter($data['cc'] ?? []));
        $bcc = array_values(array_filter($data['bcc'] ?? []));
        if ($cc !== [])
        {
            $pending->cc($cc);
        }
        if ($bcc !== [])
        {
            $pending->bcc($bcc);
        }
        $pending->send(new ComposeMessage($data['subject'], $body));

        return response()->json(['success' => true]);
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

        $mailboxes = $team->mailboxes()->visibleTo(auth()->user())->get();
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
