<?php

namespace App\Services\Hosting;

use App\Mail\CpanelAccessCredentialsMail;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\User;
use App\Services\ContactOutreachService;
use App\Services\ControlPanel\ControlPanelManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainCpanelPasswordService
{
    public const NOTIFY_NONE = 'none';

    public const NOTIFY_WHATSAPP = 'whatsapp';

    public const NOTIFY_EMAIL = 'email';

    public const NOTIFY_TO_HOSTING = 'hosting';

    public function __construct(
        private readonly ControlPanelManager $controlPanelManager,
        private readonly ContactOutreachService $contactOutreachService,
    ) {}

    public function hostingPlanEmail(Domain $domain): ?string
    {
        $email = trim((string) data_get($domain->data, 'email', ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     whatsapp: string|null,
     *     can_whatsapp: bool,
     *     can_email: bool,
     * }>
     */
    public function notifiableContactsForDomain(Domain $domain): Collection
    {
        $domain->loadMissing('service.enterprise.contacts');

        $contacts = $domain->service?->enterprise?->contacts ?? collect();

        return $contacts
            ->map(function (Contact $contact): ?array
            {
                $whatsapp = $contact->getWhatsAppNumber();
                $email = is_string($contact->email) && filter_var($contact->email, FILTER_VALIDATE_EMAIL)
                    ? $contact->email
                    : null;

                $canWhatsapp = filled($whatsapp);
                $canEmail = filled($email);

                if (! $canWhatsapp && ! $canEmail)
                {
                    return null;
                }

                $name = trim(($contact->name ?? '').' '.($contact->surname ?? ''));

                return [
                    'id' => (int) $contact->id,
                    'name' => $name !== '' ? $name : '#'.$contact->id,
                    'email' => $email,
                    'whatsapp' => $whatsapp,
                    'can_whatsapp' => $canWhatsapp,
                    'can_email' => $canEmail,
                ];
            })
            ->filter()
            ->values();
    }

    public function generatePassword(): string
    {
        return Str::password(16, letters: true, numbers: true, symbols: true);
    }

    /**
     * @return array{
     *     success: bool,
     *     password?: string,
     *     access_message?: string,
     *     notified?: bool,
     *     channel?: string|null,
     *     recipient?: string|null,
     *     recipient_source?: string|null,
     *     error?: string,
     *     warning?: string,
     * }
     */
    public function resetAndOptionallyNotify(
        User $user,
        Domain $domain,
        ?string $notifyTo = null,
        string $notifyChannel = self::NOTIFY_WHATSAPP,
        ?string $password = null,
    ): array {
        $domain->loadMissing('server', 'service.enterprise.contacts');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'El servidor no tiene credenciales configuradas para cambiar la contraseña de cPanel.',
            ];
        }

        if ($domain->suspended)
        {
            return [
                'success' => false,
                'error' => 'No se puede cambiar la contraseña de una cuenta suspendida.',
            ];
        }

        $notifyChannel = in_array($notifyChannel, [self::NOTIFY_NONE, self::NOTIFY_WHATSAPP, self::NOTIFY_EMAIL], true)
            ? $notifyChannel
            : self::NOTIFY_NONE;

        $password = filled($password) ? (string) $password : $this->generatePassword();
        $accessMessage = $this->buildAccessMessage($domain, $password);

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changeAccountPassword($domain->server, $domain, $password);

        if (! ($result['success'] ?? false))
        {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'No se pudo actualizar la contraseña de cPanel.',
            ];
        }

        $response = [
            'success' => true,
            'password' => $password,
            'access_message' => $accessMessage,
            'notified' => false,
            'channel' => null,
            'recipient' => null,
            'recipient_source' => null,
        ];

        return array_merge($response, $this->sendAccessNotification(
            $user,
            $domain,
            $accessMessage,
            'Acceso cPanel '.$domain->domain,
            $notifyTo,
            $notifyChannel,
        ));
    }

    /**
     * @return array{
     *     notified: bool,
     *     channel: string|null,
     *     recipient: string|null,
     *     recipient_source: string|null,
     *     warning?: string,
     * }
     */
    public function sendAccessNotification(
        User $user,
        Domain $domain,
        string $accessMessage,
        string $subject,
        ?string $notifyTo,
        string $notifyChannel,
    ): array {
        $domain->loadMissing('service.enterprise.contacts');

        $notifyChannel = in_array($notifyChannel, [self::NOTIFY_NONE, self::NOTIFY_WHATSAPP, self::NOTIFY_EMAIL], true)
            ? $notifyChannel
            : self::NOTIFY_NONE;

        $empty = [
            'notified' => false,
            'channel' => null,
            'recipient' => null,
            'recipient_source' => null,
        ];

        if ($notifyChannel === self::NOTIFY_NONE)
        {
            return $empty;
        }

        $notifyTo = $notifyTo !== null ? trim($notifyTo) : null;
        if ($notifyTo === null || $notifyTo === '')
        {
            $notifyTo = $this->defaultNotifyTo($domain, $notifyChannel);
        }

        try
        {
            if ($notifyChannel === self::NOTIFY_EMAIL && $notifyTo === self::NOTIFY_TO_HOSTING)
            {
                $hostingEmail = $this->hostingPlanEmail($domain);
                if ($hostingEmail === null)
                {
                    return array_merge($empty, [
                        'warning' => 'Contraseña actualizada, pero el plan de hosting no tiene un email válido.',
                    ]);
                }

                $this->sendHostingPlanEmail($user, $hostingEmail, $subject, $accessMessage);

                return [
                    'notified' => true,
                    'channel' => self::NOTIFY_EMAIL,
                    'recipient' => $hostingEmail,
                    'recipient_source' => self::NOTIFY_TO_HOSTING,
                ];
            }

            $contactId = is_numeric($notifyTo) ? (int) $notifyTo : null;
            $contact = $this->resolveContact($domain, $contactId, $notifyChannel);
            if (! $contact instanceof Contact)
            {
                $channelLabel = $notifyChannel === self::NOTIFY_EMAIL ? 'email' : 'WhatsApp';

                return array_merge($empty, [
                    'warning' => 'Contraseña actualizada, pero no hay un contacto con '.$channelLabel.' para enviar los datos.',
                ]);
            }

            $recipient = $notifyChannel === self::NOTIFY_EMAIL
                ? (string) $contact->email
                : (string) $contact->getWhatsAppNumber();

            $this->contactOutreachService->send(
                $user,
                $contact,
                $notifyChannel,
                $accessMessage,
                $subject,
            );

            return [
                'notified' => true,
                'channel' => $notifyChannel,
                'recipient' => $recipient,
                'recipient_source' => 'contact',
            ];
        } catch (ValidationException $exception)
        {
            $message = collect($exception->errors())->flatten()->filter()->first();
            $channelLabel = $notifyChannel === self::NOTIFY_EMAIL ? 'email' : 'WhatsApp';

            return array_merge($empty, [
                'warning' => 'Contraseña actualizada, pero no se pudo enviar por '.$channelLabel
                    .($message ? ': '.$message : '.'),
            ]);
        } catch (\Throwable $exception)
        {
            return array_merge($empty, [
                'warning' => 'Contraseña actualizada, pero no se pudo enviar el email: '.$exception->getMessage(),
            ]);
        }
    }

    public function buildAccessMessage(Domain $domain, string $password): string
    {
        $cpanelUrl = $domain->server?->getCpanelUrl() ?? 'https://'.$domain->domain.':2083/';

        return implode("\n", [
            'Hola, te enviamos los datos de acceso a cPanel:',
            '',
            'Dominio: '.$domain->domain,
            'URL: '.$cpanelUrl,
            'Usuario: '.$domain->username,
            'Contraseña: '.$password,
            '',
            'Te recomendamos guardar esta contraseña en un lugar seguro y cambiarla si lo preferís.',
        ]);
    }

    public function buildMailboxAccessMessage(Domain $domain, string $mailboxEmail, string $password): string
    {
        $webmailUrl = $domain->server?->getWebmailUrl($mailboxEmail)
            ?? ('https://'.($domain->server?->hostname ?: $domain->domain).':2096/');

        return implode("\n", [
            'Hola, te enviamos los datos de acceso al correo:',
            '',
            'Cuenta: '.$mailboxEmail,
            'Webmail: '.$webmailUrl,
            'Contraseña: '.$password,
            '',
            'También podés configurar la cuenta en tu cliente de correo (Outlook, Thunderbird, Mail, etc.).',
            'Te recomendamos guardar esta contraseña en un lugar seguro.',
        ]);
    }

    private function defaultNotifyTo(Domain $domain, string $notifyChannel): ?string
    {
        if ($notifyChannel === self::NOTIFY_EMAIL)
        {
            $contact = $this->notifiableContactsForDomain($domain)->firstWhere('can_email', true);
            if ($contact !== null)
            {
                return (string) $contact['id'];
            }

            return $this->hostingPlanEmail($domain) !== null ? self::NOTIFY_TO_HOSTING : null;
        }

        $contact = $this->notifiableContactsForDomain($domain)->firstWhere('can_whatsapp', true);

        return $contact !== null ? (string) $contact['id'] : null;
    }

    private function sendHostingPlanEmail(User $user, string $email, string $subject, string $message): void
    {
        $mailable = new CpanelAccessCredentialsMail($subject, $message);

        if (filled($user->email))
        {
            $mailable->replyTo((string) $user->email, (string) ($user->name ?? $user->email));
        }

        Mail::to($email)->send($mailable);
    }

    private function resolveContact(Domain $domain, ?int $contactId, string $notifyChannel): ?Contact
    {
        $notifiable = $this->notifiableContactsForDomain($domain)
            ->filter(function (array $row) use ($notifyChannel): bool
            {
                return $notifyChannel === self::NOTIFY_EMAIL
                    ? $row['can_email']
                    : $row['can_whatsapp'];
            });

        if ($contactId !== null)
        {
            $match = $notifiable->firstWhere('id', $contactId);
            if ($match === null)
            {
                return null;
            }

            return ($domain->service?->enterprise?->contacts ?? collect())
                ->firstWhere('id', $contactId);
        }

        $first = $notifiable->first();
        if ($first === null)
        {
            return null;
        }

        return ($domain->service?->enterprise?->contacts ?? collect())
            ->firstWhere('id', $first['id']);
    }
}
