<?php

namespace App\Services\Hosting;

use App\Models\Contact;
use App\Models\Domain;
use App\Models\User;
use App\Services\ContactOutreachService;
use App\Services\ControlPanel\ControlPanelManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainCpanelPasswordService
{
    public const NOTIFY_NONE = 'none';

    public const NOTIFY_WHATSAPP = 'whatsapp';

    public const NOTIFY_EMAIL = 'email';

    public function __construct(
        private readonly ControlPanelManager $controlPanelManager,
        private readonly ContactOutreachService $contactOutreachService,
    ) {}

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

    /**
     * @return Collection<int, Contact>
     */
    public function whatsappContactsForDomain(Domain $domain): Collection
    {
        $ids = $this->notifiableContactsForDomain($domain)
            ->where('can_whatsapp', true)
            ->pluck('id');

        $domain->loadMissing('service.enterprise.contacts');

        return ($domain->service?->enterprise?->contacts ?? collect())
            ->whereIn('id', $ids)
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
     *     notified?: bool,
     *     channel?: string|null,
     *     recipient?: string|null,
     *     error?: string,
     *     warning?: string,
     * }
     */
    public function resetAndOptionallyNotify(
        User $user,
        Domain $domain,
        ?int $contactId = null,
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
            'access_message' => $this->buildAccessMessage($domain, $password),
            'notified' => false,
            'channel' => null,
            'recipient' => null,
        ];

        if ($notifyChannel === self::NOTIFY_NONE)
        {
            return $response;
        }

        $contact = $this->resolveContact($domain, $contactId, $notifyChannel);
        if (! $contact instanceof Contact)
        {
            $channelLabel = $notifyChannel === self::NOTIFY_EMAIL ? 'email' : 'WhatsApp';

            return array_merge($response, [
                'warning' => 'Contraseña actualizada, pero no hay un contacto con '.$channelLabel.' para enviar los datos.',
            ]);
        }

        $recipient = $notifyChannel === self::NOTIFY_EMAIL
            ? (string) $contact->email
            : (string) $contact->getWhatsAppNumber();

        try
        {
            $this->contactOutreachService->send(
                $user,
                $contact,
                $notifyChannel,
                $this->buildAccessMessage($domain, $password),
                'Acceso cPanel '.$domain->domain,
            );

            $response['notified'] = true;
            $response['channel'] = $notifyChannel;
            $response['recipient'] = $recipient;
        } catch (ValidationException $exception)
        {
            $message = collect($exception->errors())->flatten()->filter()->first();
            $channelLabel = $notifyChannel === self::NOTIFY_EMAIL ? 'email' : 'WhatsApp';

            return array_merge($response, [
                'warning' => 'Contraseña actualizada, pero no se pudo enviar por '.$channelLabel
                    .($message ? ': '.$message : '.'),
            ]);
        }

        return $response;
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
