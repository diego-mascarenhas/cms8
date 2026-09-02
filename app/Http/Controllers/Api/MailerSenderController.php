<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DnsHelper;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMailerSenderApiRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailerSenderController extends Controller
{
    use ChecksTeamModule;

    public function show(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => $this->senderPayload($team, $request),
        ]);
    }

    public function update(UpdateMailerSenderApiRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if (! $request->user()?->can('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés permiso para configurar el remitente.'),
            ], 403);
        }

        $validated = $request->validated();

        $team->setSetting('mailer_from_name', $validated['mail_from_name'], [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mailer_from_address', $validated['mail_from_address'], [
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
        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $sender = $team->getMailerEmailSender();
        $configured = $team->hasOutgoingEmailSenderConfigured();
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
        ];
    }
}
