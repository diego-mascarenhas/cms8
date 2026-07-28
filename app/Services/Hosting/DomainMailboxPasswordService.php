<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;

class DomainMailboxPasswordService
{
    public function __construct(
        private readonly ControlPanelManager $controlPanelManager,
        private readonly DomainCpanelPasswordService $domainCpanelPasswordService,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     password?: string,
     *     access_message?: string,
     *     email?: string,
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
        string $mailboxEmail,
        ?string $notifyTo = null,
        string $notifyChannel = DomainCpanelPasswordService::NOTIFY_WHATSAPP,
        ?string $password = null,
    ): array {
        $domain->loadMissing('server', 'service.enterprise.contacts');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return [
                'success' => false,
                'error' => 'El servidor no tiene credenciales configuradas para cambiar contraseñas de correo.',
            ];
        }

        if ($domain->suspended)
        {
            return [
                'success' => false,
                'error' => 'No se puede cambiar la contraseña de una cuenta suspendida.',
            ];
        }

        $mailboxEmail = strtolower(trim($mailboxEmail));
        if (! filter_var($mailboxEmail, FILTER_VALIDATE_EMAIL))
        {
            return [
                'success' => false,
                'error' => 'La cuenta de correo no es válida.',
            ];
        }

        $password = filled($password)
            ? (string) $password
            : $this->domainCpanelPasswordService->generatePassword();

        $accessMessage = $this->domainCpanelPasswordService->buildMailboxAccessMessage(
            $domain,
            $mailboxEmail,
            $password,
        );

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changeEmailPassword($domain->server, $domain, $mailboxEmail, $password);

        if (! ($result['success'] ?? false))
        {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'No se pudo actualizar la contraseña de correo.',
            ];
        }

        $response = [
            'success' => true,
            'password' => $password,
            'access_message' => $accessMessage,
            'email' => $mailboxEmail,
            'notified' => false,
            'channel' => null,
            'recipient' => null,
            'recipient_source' => null,
        ];

        return array_merge($response, $this->domainCpanelPasswordService->sendAccessNotification(
            $user,
            $domain,
            $accessMessage,
            'Acceso correo '.$mailboxEmail,
            $notifyTo,
            $notifyChannel,
        ));
    }
}
