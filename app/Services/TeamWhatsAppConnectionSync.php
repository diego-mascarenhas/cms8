<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

final class TeamWhatsAppConnectionSync
{
    /**
     * When the local gateway is connected, persist its number on the team if missing or mismatched.
     */
    public static function syncLinkedNumberFromGatewayStatus(Team $team, ?array $whatsappStatus): void
    {
        if (! is_array($whatsappStatus) || ($whatsappStatus['status'] ?? '') !== 'connected')
        {
            return;
        }

        $gatewayNumber = preg_replace('/[^0-9]/', '', (string) ($whatsappStatus['number'] ?? ''));
        if ($gatewayNumber === '')
        {
            return;
        }

        $teamNumber = $team->getWhatsAppFrom();
        $teamDigits = $teamNumber !== null && $teamNumber !== ''
            ? preg_replace('/[^0-9]/', '', (string) $teamNumber)
            : '';

        if ($teamDigits !== '' && PhoneHelper::digitsBelongToSameLine($teamDigits, $gatewayNumber))
        {
            return;
        }

        Team::ensureOnlyTeamHasWhatsAppNumber($team->id, $gatewayNumber);
        $team->setSetting('whatsapp_from', $gatewayNumber);
        $team->setSetting('assistant_auto_respond', '1');
        $team->unsetRelation('settings');

        Log::info('WhatsApp number auto-linked to team from gateway status', [
            'team_id' => $team->id,
            'number' => $gatewayNumber,
            'previous_number' => $teamDigits !== '' ? $teamDigits : null,
        ]);
    }
}
