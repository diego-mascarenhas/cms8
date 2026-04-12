<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\PhoneHelper;
use App\Models\Team;
use App\Services\WhatsApp\LocalWhatsAppGateway;

final class TeamWhatsAppChatPresentation
{
    /**
     * WhatsApp connection + QR URL state for chat UI and registration onboarding (local gateway).
     *
     * @return array{
     *     whatsappDriver: string,
     *     whatsappStatus: array<string, mixed>|null,
     *     teamWhatsAppNumber: string|null,
     *     teamWhatsAppNumberFormatted: string|null,
     *     teamWhatsAppIsConnected: bool,
     *     qrImageUrl: string|null
     * }
     */
    public static function resolveForTeam(?Team $team): array
    {
        $whatsappDriver = (string) config('whatsapp.driver', 'twilio');
        $whatsappStatus = null;
        $teamWhatsAppNumber = null;
        $teamWhatsAppNumberFormatted = null;
        $teamWhatsAppIsConnected = false;
        $qrImageUrl = null;

        if ($whatsappDriver !== 'local' || ! app()->bound(WhatsAppGateway::class))
        {
            return compact(
                'whatsappDriver',
                'whatsappStatus',
                'teamWhatsAppNumber',
                'teamWhatsAppNumberFormatted',
                'teamWhatsAppIsConnected',
                'qrImageUrl',
            );
        }

        $gateway = self::localGatewayForTeam($team) ?? app(WhatsAppGateway::class);

        try
        {
            $whatsappStatus = $gateway->getConnectionStatus();
        } catch (\Throwable $e)
        {
            $whatsappStatus = [
                'status' => 'disconnected',
                'number' => null,
            ];
        }

        $baseUrl = $team?->getWhatsAppServiceBaseUrl();
        $baseUrl = is_string($baseUrl) ? rtrim($baseUrl, '/') : '';
        if ($baseUrl !== '')
        {
            $qrImageUrl = route('chat.whatsapp-qr-image');
        }

        if ($team)
        {
            $teamWhatsAppNumber = $team->getWhatsAppFrom();
            $teamWhatsAppNumberFormatted = $teamWhatsAppNumber
                ? PhoneHelper::formatForDisplayReadable($teamWhatsAppNumber)
                : null;
            $gatewayNumber = is_array($whatsappStatus) ? ($whatsappStatus['number'] ?? null) : null;
            $teamNumNorm = $teamWhatsAppNumber ? preg_replace('/[^0-9]/', '', (string) $teamWhatsAppNumber) : '';
            $gatewayNumNorm = $gatewayNumber ? preg_replace('/[^0-9]/', '', (string) $gatewayNumber) : '';
            $teamWhatsAppIsConnected = ($whatsappStatus['status'] ?? '') === 'connected'
                && $teamNumNorm !== ''
                && $gatewayNumNorm !== ''
                && $teamNumNorm === $gatewayNumNorm;
        }

        return compact(
            'whatsappDriver',
            'whatsappStatus',
            'teamWhatsAppNumber',
            'teamWhatsAppNumberFormatted',
            'teamWhatsAppIsConnected',
            'qrImageUrl',
        );
    }

    private static function localGatewayForTeam(?Team $team): ?WhatsAppGateway
    {
        if ($team === null)
        {
            return null;
        }

        $baseUrl = $team->getWhatsAppServiceBaseUrl();
        if (! is_string($baseUrl) || $baseUrl === '')
        {
            return null;
        }

        return new LocalWhatsAppGateway($baseUrl, (string) config('whatsapp.local.webhook_secret'), (int) $team->id);
    }
}
