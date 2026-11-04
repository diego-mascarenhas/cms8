<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\Assistant\AssistantActorContextService;
use App\Services\AssistantToolIntentPromptService;
use App\Services\AssistantToolsService;
use App\Services\ChatAssistantReplyService;
use App\Services\Hosting\HostingCustomerAssistantService;
use App\Services\Hosting\RehearsalChatOutbox;
use App\Services\InboxProductOnboardingService;
use App\Services\UserResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RehearseHostingAssistantCommand extends Command
{
    /** @var list<array{direction: string, body: string}> */
    private array $history = [];

    protected $signature = 'hosting:rehearse
        {message? : Optional first WhatsApp message. Omit it to open a chat}
        {--chat : Keep the conversation open. "chau" leaves the flow}
        {--team= : Team id}
        {--phone= : Customer phone}
        {--contact= : Contact id}
        {--domain= : Domain when the contact has several}
        {--plan= : Plan name to rehearse a change}
        {--apply : Change Stripe and cPanel. Refused when APP_ENV is production}
        {--yes : Skip the confirmation for --apply}
        {--allow-production : Allow a dry run when APP_ENV is production}';

    protected $description = 'Rehearse a customer hosting WhatsApp chat. Outside production, passwords change and the access message is printed here';

    public function handle(
        UserResolverService $users,
        InboxProductOnboardingService $onboarding,
        HostingCustomerAssistantService $hosting,
    ): int {
        if ($this->blockedByEnvironment())
        {
            return self::FAILURE;
        }

        $contact = $this->contact($users);
        if (! $contact instanceof Contact)
        {
            return self::FAILURE;
        }

        $team = Team::withoutGlobalScopes()->find((int) $contact->team_id);
        if (! $team instanceof Team)
        {
            $this->error('No encontré el equipo del contacto.');

            return self::FAILURE;
        }

        $message = trim((string) ($this->argument('message') ?? ''));
        if ($this->option('chat') || $message === '')
        {
            return $this->chat($contact, $team, $onboarding, $hosting, $message);
        }

        $flow = data_get($contact->data, InboxProductOnboardingService::DATA_KEY);
        $active = (bool) data_get($flow, 'active');
        $step = (string) (data_get($flow, 'step') ?: '—');
        $asksAccount = $onboarding->asksAboutOwnAccount($message);

        $this->line('Entorno: '.app()->environment());
        $this->line('URL: '.config('app.url'));
        $this->line('Equipo: '.$team->id.' '.$team->name);
        $this->line('Contacto: '.trim($contact->name.' '.$contact->surname).' (#'.$contact->id.')');
        $this->newLine();
        $this->line('Mensaje: '.$message);
        $this->line('Embudo de recomendación: '.($active ? 'activo ('.$step.')' : 'inactivo').'.');
        $this->line($asksAccount
            ? 'Este mensaje no lo contesta el embudo. El asistente usa Hosting cPanel.'
            : 'Este mensaje puede seguir en el embudo si está activo.');
        $this->newLine();
        $this->line($hosting->listForContact((int) $team->id, (int) $contact->id));

        $plan = trim((string) $this->option('plan'));
        if ($plan === '')
        {
            $this->newLine();
            $this->comment('No se envió WhatsApp ni se cambió Stripe ni el servidor.');

            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->option('apply'))
        {
            $this->line($hosting->rehearseUpgrade((int) $team->id, (int) $contact->id, $this->option('domain') ? (string) $this->option('domain') : null, $plan));
            $this->comment('Para aplicarlo en este entorno, repetí el comando con --apply.');

            return self::SUCCESS;
        }

        if (app()->environment('production'))
        {
            $this->error('--apply no corre en producción.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Esto cambia la suscripción en Stripe y el paquete en cPanel. ¿Seguimos?'))
        {
            $this->comment('No cambié nada.');

            return self::SUCCESS;
        }

        $actor = User::query()->find($team->user_id);
        if (! $actor instanceof User)
        {
            $this->error('El equipo no tiene un usuario para registrar el cambio.');

            return self::FAILURE;
        }

        $this->line($hosting->requestUpgrade(
            (int) $team->id,
            (int) $contact->id,
            $actor,
            $this->option('domain') ? (string) $this->option('domain') : null,
            $plan,
        ));

        return self::SUCCESS;
    }

    private function chat(
        Contact $contact,
        Team $team,
        InboxProductOnboardingService $onboarding,
        HostingCustomerAssistantService $hosting,
        string $firstMessage,
    ): int {
        $who = trim($contact->name.' '.$contact->surname) ?: 'Cliente';
        $deliverToChat = ! app()->environment('production');
        $outbox = app(RehearsalChatOutbox::class);
        $tools = app(AssistantToolsService::class);
        $outbox->capture($deliverToChat);
        $tools->setRehearsalChatDelivery($deliverToChat);
        $tools->setRehearsalDryRun(! $this->option('apply'));

        $this->line('WhatsApp · '.$who.' · '.$team->name);
        $this->line($deliverToChat
            ? 'Escribí como el cliente. La clave sale en este chat. "chau" sale del flujo.'
            : 'Escribí como el cliente. "chau" sale del flujo.');
        $this->newLine();

        $pending = $firstMessage;
        while (true)
        {
            $message = $pending !== '' ? $pending : trim((string) $this->ask('Cliente'));
            $pending = '';
            if ($message === '')
            {
                continue;
            }

            $this->line($who.': '.$message);
            if ($this->leavesFlow($message))
            {
                $contact->refresh();
                $this->line($team->name.': '.$onboarding->leave($contact));
                $this->newLine();
                $this->comment($deliverToChat
                    ? 'No salió un WhatsApp. Lo enviado quedó en este chat.'
                    : 'No se envió WhatsApp ni se cambió Stripe ni el servidor.');

                return self::SUCCESS;
            }

            $reply = $this->reply($contact, $team, $onboarding, $hosting, $message);
            $this->line($team->name.': '.$reply);
            foreach ($outbox->pull() as $sent)
            {
                $this->newLine();
                $this->line($team->name.': '.$sent);
                $this->history[] = ['direction' => 'outbound', 'body' => $sent];
            }
            $this->newLine();
        }
    }

    private function reply(
        Contact $contact,
        Team $team,
        InboxProductOnboardingService $onboarding,
        HostingCustomerAssistantService $hosting,
        string $message,
    ): string {
        $text = $this->scriptedReply($contact, $team, $onboarding, $hosting, $message);
        if ($text === null && ! app()->runningUnitTests())
        {
            $text = $this->assistantReply($contact, $team, $message);
        }
        if ($text === null || $text === '')
        {
            $text = 'Decime si querés ver las suscripciones, el espacio de un dominio, el correo o un cambio de plan.';
        }

        $this->history[] = ['direction' => 'inbound', 'body' => $message];
        $this->history[] = ['direction' => 'outbound', 'body' => $text];

        return $text;
    }

    private function scriptedReply(
        Contact $contact,
        Team $team,
        InboxProductOnboardingService $onboarding,
        HostingCustomerAssistantService $hosting,
        string $message,
    ): ?string {
        if ($this->asksMailboxDisk($message))
        {
            return $hosting->mailboxDisk(
                (int) $team->id,
                (int) $contact->id,
                $this->mailboxAddress($message) ?? $this->mailboxFromHistory(),
            );
        }

        if ($this->asksDisk($message))
        {
            return $hosting->diskSpace(
                (int) $team->id,
                (int) $contact->id,
                $this->diskDomain($message) ?? trim((string) $this->option('domain')),
            );
        }

        $lines = [];
        if ($this->asksBilling($message))
        {
            $lines[] = $hosting->unpaidInvoices(
                (int) $team->id,
                (int) $contact->id,
                $this->invoiceLimit($message),
            );
        }

        if ($onboarding->asksAboutOwnAccount($message))
        {
            $lines[] = $hosting->listForContact((int) $team->id, (int) $contact->id);
            $plan = trim((string) $this->option('plan'));
            if ($plan !== '')
            {
                $lines[] = $hosting->rehearseUpgrade(
                    (int) $team->id,
                    (int) $contact->id,
                    $this->option('domain') ? (string) $this->option('domain') : null,
                    $plan,
                );
            }
        }

        if ($lines !== [])
        {
            return implode("\n\n", $lines);
        }

        if ($this->isGreeting($message))
        {
            return 'Hola. Soy el asistente de hosting. Puedo decirte las suscripciones, el espacio de un dominio, el correo o un cambio de plan. ¿Qué necesitás?';
        }

        return null;
    }

    private function assistantReply(Contact $contact, Team $team, string $message): string
    {
        $ownerId = (int) $team->user_id;
        $phone = preg_replace('/\D+/', '', (string) ($contact->phone ?? $contact->mobile ?? '')) ?: null;
        $reply = app(ChatAssistantReplyService::class)->getReply(
            $message,
            $this->history,
            (int) $team->id,
            true,
            $ownerId > 0 ? $ownerId : null,
            is_string($phone) && $phone !== '' ? $phone : null,
            'hosting:assistant_hosting',
            (int) $contact->id,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
            false,
            'Ensayo del flujo Hosting cPanel, con las mismas acciones que en producción. La clave se envía a la persona que escribe en este chat, no a otro contacto. El mensaje con la contraseña se publica aquí: no digas que salió por otro WhatsApp. Si la herramienta dice que los datos quedaron en este chat, confirmá en una frase y no repitas la contraseña. Respondé solo a este mensaje. No listes todas las suscripciones salvo que pregunte cuáles tiene. Si pregunta el espacio de un dominio o del plan, usá hosting_disk. Si pregunta el espacio de una casilla o de un correo, usá manage_hosting_mailbox con action disk y esa cuenta; «esta casilla» es la del mensaje anterior, no el disco del plan. Si pregunta el plan, usá list_my_hosting. Si pregunta facturas, saldo, un link de pago, o las facturas aunque estén pagas, usá hosting_unpaid_invoices y pegá el listado, incluidas las cobradas. No digas que solo podés mostrar las impagas, ni que no tenés acceso, ni los mandes a una web. Una suscripción cancelada no impide la clave, el correo, el FTP, el DNS ni la ficha de un producto si la cuenta no está suspendida: llamá la herramienta. Si la cuenta está suspendida, decilo y no cambies nada. No reactives Stripe. No inventes un email ni un teléfono.',
        );

        $text = trim((string) ($reply['text'] ?? ''));

        return $text !== '' ? $text : 'No pude armar la respuesta. Probá de nuevo con el dominio o la pregunta.';
    }

    private function asksBilling(string $message): bool
    {
        $normalized = mb_strtolower(Str::ascii($message));

        return preg_match('/\b(facturas?|saldo|deuda|debo|adeud|impag|link de pago|enlace de pago)\b/u', $normalized) === 1;
    }

    private function invoiceLimit(string $message): int
    {
        $normalized = mb_strtolower(Str::ascii($message));
        if (preg_match('/\b(?:ultimas?|ultimos?)\s+(\d{1,2})\b/u', $normalized, $matches) === 1)
        {
            return max(1, min(20, (int) $matches[1]));
        }

        if (preg_match('/\b(pagas?|pagadas?|cobradas?|todas)\b/u', $normalized) === 1)
        {
            return 20;
        }

        return 5;
    }

    private function asksDisk(string $message): bool
    {
        $normalized = mb_strtolower(Str::ascii($message));

        return preg_match('/\b(espacio|disco|cuota|quota|capacidad|gigas?|megas?)\b/u', $normalized) === 1;
    }

    private function asksMailboxDisk(string $message): bool
    {
        if (! $this->asksDisk($message))
        {
            return false;
        }

        $normalized = mb_strtolower(Str::ascii($message));

        return $this->mailboxAddress($message) !== null
            || preg_match('/\b(casilla|buzon|correo|email|mailbox)\b/u', $normalized) === 1;
    }

    private function mailboxAddress(string $message): ?string
    {
        if (preg_match('/\b([a-z0-9][a-z0-9._-]{0,31}@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z]{2,})+)\b/i', $message, $matches) === 1)
        {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function mailboxFromHistory(): ?string
    {
        for ($index = count($this->history) - 1; $index >= 0; $index--)
        {
            $found = $this->mailboxAddress((string) ($this->history[$index]['body'] ?? ''));
            if ($found !== null)
            {
                return $found;
            }
        }

        return null;
    }

    private function diskDomain(string $message): ?string
    {
        if (preg_match('/\b((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,})\b/i', $message, $matches) === 1)
        {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function isGreeting(string $message): bool
    {
        $normalized = trim(mb_strtolower(Str::ascii($message)));
        $normalized = trim($normalized, " \t\n\r\0\x0B!?.¡¿");

        return preg_match('/^(hola|hey|hi|hello|buenas|buen dia|buenos dias|buenas tardes|buenas noches|que tal|gracias|ok|dale|perfecto)\b/u', $normalized) === 1
            && preg_match('/\b(hosting|suscrip|subscrip|plan|dominio|espacio|disco|correo|ftp|cpanel)\b/u', $normalized) !== 1;
    }

    private function leavesFlow(string $message): bool
    {
        if (app(AssistantToolIntentPromptService::class)->matchesFlowReset($message))
        {
            return true;
        }

        $normalized = mb_strtolower(trim($message));
        $normalized = trim($normalized, " \t\n\r\0\x0B!?.¡¿");

        return in_array($normalized, ['salir', 'exit', 'quit'], true);
    }

    private function blockedByEnvironment(): bool
    {
        if (! app()->environment('production'))
        {
            return false;
        }

        if ($this->option('apply'))
        {
            $this->error('--apply no corre en producción.');

            return true;
        }

        if (! $this->option('allow-production'))
        {
            $this->error('Este ensayo no corre en producción. Usalo en staging, o pasá --allow-production para un ensayo sin cambios.');

            return true;
        }

        return false;
    }

    private function contact(UserResolverService $users): ?Contact
    {
        $contactId = (int) $this->option('contact');
        if ($contactId > 0)
        {
            $contact = Contact::withoutGlobalScopes()->find($contactId);
            if (! $contact instanceof Contact)
            {
                $this->error('No encontré el contacto '.$contactId.'.');

                return null;
            }

            $teamId = (int) $this->option('team');
            if ($teamId > 0 && (int) $contact->team_id !== $teamId)
            {
                $this->error('El contacto '.$contactId.' no está en el equipo '.$teamId.'.');

                return null;
            }

            return $contact;
        }

        $phone = trim((string) $this->option('phone'));
        $teamId = (int) $this->option('team');
        if ($phone === '' || $teamId < 1)
        {
            $this->error('Indicá --contact=ID, o --team=ID y --phone=.');

            return null;
        }

        $matches = $users->findContactsInTeamByPhone($teamId, $phone);
        if ($matches->isEmpty())
        {
            $this->error('No hay un contacto con ese teléfono en el equipo '.$teamId.'.');

            return null;
        }

        if ($matches->count() > 1)
        {
            $this->error('Hay varios contactos con ese teléfono. Elegí uno con --contact=.');
            foreach ($matches as $match)
            {
                $this->line('#'.$match->id.' '.trim($match->name.' '.$match->surname));
            }

            return null;
        }

        return $matches->first();
    }
}
