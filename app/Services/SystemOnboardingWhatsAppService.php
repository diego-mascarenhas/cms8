<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\WhatsAppOutboundText;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use Illuminate\Support\Facades\Log;

class SystemOnboardingWhatsAppService
{
    /**
     * Static reseller/system onboarding media sequence.
     *
     * @var array<int, string>
     */
    protected const ONBOARDING_MEDIA_PATHS = [
        '/images/system-onboarding/step-1.png',
        '/images/system-onboarding/step-2.png',
        '/images/system-onboarding/step-3.png',
        '/images/system-onboarding/step-4.png',
    ];

    protected const ONBOARDING_TEXT = 'Hola, soy del equipo Humano 👋 Te comparto el onboarding del reseller para dar de alta tu cuenta paso a paso. Primero te envio un resumen breve y luego las capturas de cada paso.';

    /**
     * @return array<string, mixed>
     */
    public function execute(User $actor, Team $team, string $phoneDigits, ?string $source = null): array
    {
        if (! $actor->hasAnyRole(['admin', 'root']))
        {
            return [
                'success' => false,
                'message' => 'Only admin or root may send system onboarding.',
                '_http_status' => 403,
            ];
        }

        if (! $actor->belongsToTeam($team))
        {
            return [
                'success' => false,
                'message' => 'The user is not a member of this team.',
                '_http_status' => 403,
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $phoneDigits) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 15)
        {
            return [
                'success' => false,
                'message' => 'Invalid phone number (expected 10–15 digits).',
                '_http_status' => 422,
            ];
        }

        $gateway = $this->gatewayForTeam($team);
        if (! $gateway->isConfigured())
        {
            return [
                'success' => false,
                'message' => 'WhatsApp is not configured for this team.',
                '_http_status' => 422,
            ];
        }

        try
        {
            $gateway->sendMessage(
                $digits,
                WhatsAppOutboundText::sanitize(self::ONBOARDING_TEXT),
                [
                    'source' => 'system_onboarding',
                ],
                $actor->id,
            );
        } catch (\Throwable $e)
        {
            return [
                'success' => false,
                'message' => 'Could not send onboarding opening message: '.$e->getMessage(),
                '_http_status' => 502,
            ];
        }

        $sentMedia = 0;
        $missingMedia = [];

        foreach (self::ONBOARDING_MEDIA_PATHS as $path)
        {
            $normalizedPath = '/'.ltrim($path, '/');
            $absolute = public_path(ltrim($normalizedPath, '/'));

            if (! file_exists($absolute))
            {
                $missingMedia[] = $normalizedPath;

                continue;
            }

            $sent = false;
            try
            {
                $sent = $gateway->sendMedia($digits, $normalizedPath, null);
            } catch (\Throwable $e)
            {
                Log::warning('System onboarding media send failed', [
                    'team_id' => $team->id,
                    'phone' => $digits,
                    'path' => $normalizedPath,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($sent)
            {
                $sentMedia++;
            } else
            {
                $missingMedia[] = $normalizedPath;
            }
        }

        Log::info('System onboarding WhatsApp sent', [
            'team_id' => $team->id,
            'actor_id' => $actor->id,
            'phone' => $digits,
            'sent_media_count' => $sentMedia,
            'missing_media' => $missingMedia,
            'source' => $source ?? 'unknown',
        ]);

        $summary = "System onboarding sent to {$digits}. Media sent: {$sentMedia}.";
        if ($missingMedia !== [])
        {
            $summary .= ' Missing/unavailable: '.implode(', ', $missingMedia).'.';
        }

        return [
            'success' => true,
            'response' => $summary,
            'action_performed' => 'system_onboarding_whatsapp',
            'phone' => $digits,
            'sent_media_count' => $sentMedia,
            'missing_media' => $missingMedia,
            '_http_status' => 200,
        ];
    }

    protected function gatewayForTeam(Team $team): WhatsAppGateway
    {
        if (config('whatsapp.driver') === 'local')
        {
            $baseUrl = $team->getWhatsAppServiceBaseUrl();
            if ($baseUrl !== '')
            {
                return new LocalWhatsAppGateway($baseUrl, (string) config('whatsapp.local.webhook_secret'), $team->id);
            }
        }

        return app(WhatsAppGateway::class);
    }
}
