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
     *     qrImageUrl: string|null,
     *     onboardingQrScanTargetsChatOnly: bool
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
        $onboardingQrScanTargetsChatOnly = false;

        $baseUrl = $team?->getWhatsAppServiceBaseUrl();
        $baseUrl = is_string($baseUrl) ? rtrim($baseUrl, '/') : '';

        if ($whatsappDriver === 'local' && app()->bound(WhatsAppGateway::class))
        {
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

            if ($baseUrl !== '')
            {
                $qrImageUrl = route('chat.whatsapp-qr-image');
                $onboardingQrScanTargetsChatOnly = false;
            }

            if ($team)
            {
                TeamWhatsAppConnectionSync::syncLinkedNumberFromGatewayStatus($team, $whatsappStatus);

                $teamWhatsAppNumber = $team->getWhatsAppFrom();
                $teamWhatsAppNumberFormatted = $teamWhatsAppNumber
                    ? PhoneHelper::formatForDisplayReadable($teamWhatsAppNumber)
                    : null;
                $gatewayNumber = is_array($whatsappStatus) ? ($whatsappStatus['number'] ?? null) : null;
                $teamWhatsAppIsConnected = ($whatsappStatus['status'] ?? '') === 'connected'
                    && PhoneHelper::digitsBelongToSameLine(
                        $teamWhatsAppNumber !== null && $teamWhatsAppNumber !== '' ? (string) $teamWhatsAppNumber : null,
                        $gatewayNumber !== null && $gatewayNumber !== '' ? (string) $gatewayNumber : null,
                    );
            }
        }

        if ($qrImageUrl === null && $team)
        {
            $qrImageUrl = route('registration.onboarding.chat-link-qr-image');
            $onboardingQrScanTargetsChatOnly = true;
        }

        return compact(
            'whatsappDriver',
            'whatsappStatus',
            'teamWhatsAppNumber',
            'teamWhatsAppNumberFormatted',
            'teamWhatsAppIsConnected',
            'qrImageUrl',
            'onboardingQrScanTargetsChatOnly',
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
