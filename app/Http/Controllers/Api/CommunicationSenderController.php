<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DnsHelper;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMailerSenderApiRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationSenderController extends Controller
{
    use ChecksTeamModule;

    public function show(Request $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->senderPayload($team, $request),
        ]);
    }

    public function update(UpdateMailerSenderApiRequest $request): JsonResponse
    {
        $team = $this->resolveTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $request->user()?->can('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés permiso para configurar el remitente.'),
            ], 403);
        }

        $validated = $request->validated();

        $team->setSetting('mail_from_name', $validated['mail_from_name'], [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mail_from_address', $validated['mail_from_address'], [
            'group' => 'email',
            'type' => 'email',
            'is_encrypted' => false,
        ]);

        $team->unsetRelation('settings');
        $team->load('settings');

        return response()->json([
            'success' => true,
            'message' => __('app.email_sender_config_saved'),
            'data' => $this->senderPayload($team, $request),
        ]);
    }

    private function resolveTeam(Request $request): Team|JsonResponse
    {
        $fromToken = $request->attributes->get('team');
        if ($fromToken instanceof Team)
        {
            $this->ensureTeamModule($fromToken, 'communications');

            return $fromToken;
        }

        return $this->teamOrError($request);
    }

    /**
     * @return array{
     *     from_name: string,
     *     from_address: string,
     *     configured: bool,
     *     can_update: bool,
     *     can_send: bool,
     *     required_include: string,
     *     example_txt: string,
     *     spf: array<string, mixed>|null
     * }
     */
    private function senderPayload(Team $team, Request $request): array
    {
        $team->unsetRelation('settings');
        $team->load('settings');

        $sender = $team->getCommunicationEmailSender();
        $configured = $sender['from_name'] !== '' && $sender['from_address'] !== '';
        $spf = $configured
            ? DnsHelper::checkEmailDomainConfiguration($sender['from_address'])
            : null;

        return [
            'from_name' => $sender['from_name'],
            'from_address' => $sender['from_address'],
            'configured' => $configured,
            'can_update' => (bool) $request->user()?->can('update', $team),
            'can_send' => $configured && DnsHelper::canSendBroadcastFromUi($spf, true),
            'required_include' => DnsHelper::REVISION_ALPHA_SPF_INCLUDE,
            'example_txt' => DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT,
            'spf' => $spf,
            'mb' => is_array($spf) ? ($spf['mb'] ?? null) : null,
        ];
    }
}
