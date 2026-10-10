<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMailerPreferencesApiRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailerPreferencesController extends Controller
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
            'data' => $this->payload($team, $request),
        ]);
    }

    public function update(UpdateMailerPreferencesApiRequest $request): JsonResponse
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
                'message' => __('No tenés permiso para cambiar la configuración.'),
            ], 403);
        }

        $validated = $request->validated();

        $team->setSetting('mailer_min_hours_between_emails', (int) $validated['min_hours_between_emails'], [
            'group' => 'mailer',
            'type' => 'integer',
            'is_encrypted' => false,
        ]);

        foreach ([
            'enable_open_tracking' => 'mailer_enable_open_tracking',
            'enable_click_tracking' => 'mailer_enable_click_tracking',
            'show_unsubscribe' => 'mailer_show_unsubscribe',
        ] as $input => $key)
        {
            $team->setSetting($key, $request->boolean($input) ? '1' : '0', [
                'group' => 'mailer',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
        }

        $team->unsetRelation('settings');
        $team->load('settings');

        return response()->json([
            'success' => true,
            'message' => __('Configuración de envíos guardada.'),
            'data' => $this->payload($team, $request),
        ]);
    }

    /**
     * @return array{
     *     min_hours_between_emails: int,
     *     enable_open_tracking: bool,
     *     enable_click_tracking: bool,
     *     show_unsubscribe: bool,
     *     can_update: bool
     * }
     */
    private function payload(Team $team, Request $request): array
    {
        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        return [
            'min_hours_between_emails' => $team->mailerMinHoursBetweenEmails(),
            'enable_open_tracking' => $team->mailerTracksOpens(),
            'enable_click_tracking' => $team->mailerTracksClicks(),
            'show_unsubscribe' => $team->mailerShowsUnsubscribe(),
            'can_update' => (bool) $request->user()?->can('update', $team),
        ];
    }
}
