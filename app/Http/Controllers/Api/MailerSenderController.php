<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMailerSenderApiRequest;
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

        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $config = $team->getOutgoingEmailConfig();

        return response()->json([
            'success' => true,
            'data' => [
                'from_name' => trim((string) ($config['from_name'] ?? '')),
                'from_address' => trim((string) ($config['from_address'] ?? '')),
                'configured' => $team->hasOutgoingEmailSenderConfigured(),
                'can_update' => (bool) $request->user()?->can('update', $team),
            ],
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
            'data' => [
                'from_name' => $validated['mail_from_name'],
                'from_address' => $validated['mail_from_address'],
                'configured' => true,
                'can_update' => true,
            ],
        ]);
    }
}
