<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\WhatsAppOutboundText;
use App\Models\Prospect;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use Illuminate\Support\Facades\Log;

class SystemOnboardingWhatsAppService
{
    protected const ONBOARDING_TEXT = 'Hola, soy del equipo Humano 👋 Te comparto el onboarding para dar de alta tu cuenta paso a paso. Primero te envio un resumen breve y luego las capturas de cada paso.';

    protected const STEP_PAUSE_MIN_MS = 2200;

    protected const STEP_PAUSE_MAX_MS = 5200;

    /**
     * @return array<int, array{image: string, message: string}>
     */
    protected function onboardingSteps(): array
    {
        $registerUrl = rtrim((string) config('app.url'), '/').'/register';

        return [
            [
                'image' => '/images/system-onboarding/step-1.png',
                'message' => "Paso 1/6: Crea tu cuenta desde este enlace: {$registerUrl}.",
            ],
            [
                'image' => '/images/system-onboarding/step-2.png',
                'message' => 'Paso 2/6: Completa los datos de facturacion para avanzar al checkout.',
            ],
            [
                'image' => '/images/system-onboarding/step-3.png',
                'message' => 'Paso 3/6: En la pantalla de pago requerido, presiona "Pagar con Stripe".',
            ],
            [
                'image' => '/images/system-onboarding/step-4.png',
                'message' => 'Paso 4/6: Completa el pago en Stripe.',
            ],
            [
                'image' => '/images/system-onboarding/step-5.png',
                'message' => 'Paso 5/6: Vincula tu WhatsApp escaneando el QR.',
            ],
            [
                'image' => '/images/system-onboarding/step-6.png',
                'message' => 'Paso 6/6: Completa la configuracion del negocio. Es esencial para que el bot responda con tu tono, contexto y enfoque comercial.',
            ],
        ];
    }

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

        $prospect = Prospect::captureFromWhatsApp($digits, (int) $team->id);
        $steps = $this->onboardingSteps();
        $sentMedia = 0;
        $missingMedia = [];

        foreach ($steps as $index => $step)
        {
            $result = $this->sendStep($gateway, $team, $digits, $index + 1, $actor->id);
            $sentMedia += $result['sent_media_count'];
            if ($result['missing_media'] !== [])
            {
                $missingMedia = array_merge($missingMedia, $result['missing_media']);
            }
            $this->sleepBetweenSteps($index + 1, count($steps));
        }

        $gateway->sendMessage(
            $digits,
            'Listo, onboarding finalizado. Si necesitas ayuda, podemos derivarte con un representante para acompanarte.',
            ['source' => 'system_onboarding_finish'],
            $actor->id,
        );

        $data = is_array($prospect->data) ? $prospect->data : [];
        $data['system_onboarding'] = [
            'active' => true,
            'step' => count($steps),
            'awaiting_rep_message' => false,
            'source' => $source ?? 'unknown',
            'started_at' => $data['system_onboarding']['started_at'] ?? now()->toIso8601String(),
            'last_message_at' => now()->toIso8601String(),
            'sequence_mode' => 'auto_with_pause',
        ];
        $prospect->forceFill([
            'onboarding_step' => 'system_onboarding_sent',
            'data' => $data,
        ])->save();

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

    /**
     * @return array{message: string, media_path: ?string}|null
     */
    public function tryHandleInbound(Team $team, string $fromDigits, string $body): ?array
    {
        $prospect = Prospect::query()
            ->where('channel', Prospect::CHANNEL_WHATSAPP)
            ->where('external_id', $fromDigits)
            ->where('team_id', $team->id)
            ->first();

        if (! $prospect)
        {
            return null;
        }

        $data = is_array($prospect->data) ? $prospect->data : [];
        $flow = is_array($data['system_onboarding'] ?? null) ? $data['system_onboarding'] : null;
        if (! $flow || ! (($flow['active'] ?? false) === true))
        {
            return null;
        }

        $normalized = $this->normalizeInboundText($body);
        $steps = $this->onboardingSteps();
        $step = max(1, min((int) ($flow['step'] ?? 1), count($steps)));

        if ($this->containsAny($normalized, ['representante', 'asesor', 'humano', 'agente']))
        {
            $flow['awaiting_rep_message'] = true;
            $flow['last_message_at'] = now()->toIso8601String();
            $data['system_onboarding'] = $flow;
            $prospect->forceFill(['data' => $data])->save();

            return [
                'message' => 'Perfecto. Te conectamos con un representante. Escribe aqui tu consulta o telefono de contacto y te responderemos a la brevedad.',
                'media_path' => null,
            ];
        }

        if (($flow['awaiting_rep_message'] ?? false) === true)
        {
            $requests = is_array($data['representative_requests'] ?? null) ? $data['representative_requests'] : [];
            $requests[] = [
                'message' => trim($body),
                'team_id' => $team->id,
                'phone' => $fromDigits,
                'created_at' => now()->toIso8601String(),
            ];
            $data['representative_requests'] = $requests;
            $flow['awaiting_rep_message'] = false;
            $flow['active'] = false;
            $flow['last_message_at'] = now()->toIso8601String();
            $data['system_onboarding'] = $flow;
            $prospect->forceFill([
                'onboarding_step' => 'system_onboarding_rep_requested',
                'data' => $data,
            ])->save();

            Log::info('System onboarding representative request captured', [
                'team_id' => $team->id,
                'phone' => $fromDigits,
                'message' => trim($body),
            ]);

            return [
                'message' => 'Gracias. Recibimos tu mensaje y un representante te va a contactar en breve.',
                'media_path' => null,
            ];
        }

        if ($this->containsAny($normalized, ['cancelar', 'stop', 'detener', 'salir']))
        {
            $flow['active'] = false;
            $flow['last_message_at'] = now()->toIso8601String();
            $data['system_onboarding'] = $flow;
            $prospect->forceFill([
                'onboarding_step' => 'system_onboarding_cancelled',
                'data' => $data,
            ])->save();

            return [
                'message' => 'Listo, pausamos el onboarding. Si quieres retomarlo, pidelo y te ayudamos.',
                'media_path' => null,
            ];
        }

        if ($this->containsAny($normalized, ['no', 'problema', 'error', 'no pude', 'duda']))
        {
            return [
                'message' => 'Gracias por avisar. Si quieres ayuda directa, escribe "representante".',
                'media_path' => null,
            ];
        }

        if (! $this->containsAny($normalized, ['listo', 'hecho', 'ok', 'ya', 'si', 'pague', 'pague', 'ya pague', 'ya pague correctamente']))
        {
            return [
                'message' => "Seguimos en el paso {$step}/".count($steps).'. Si necesitas ayuda, escribe "representante".',
                'media_path' => null,
            ];
        }

        $nextStep = $step + 1;
        if ($nextStep > count($steps))
        {
            $flow['active'] = false;
            $flow['step'] = count($steps);
            $flow['last_message_at'] = now()->toIso8601String();
            $data['system_onboarding'] = $flow;
            $prospect->forceFill([
                'onboarding_step' => 'system_onboarding_completed',
                'data' => $data,
                'converted_at' => now(),
            ])->save();

            return [
                'message' => 'Excelente, onboarding completado. Tu configuracion ya esta lista para que el bot responda alineado a tu negocio. Si quieres optimizar la estrategia, escribe "representante".',
                'media_path' => null,
            ];
        }

        $flow['step'] = $nextStep;
        $flow['last_message_at'] = now()->toIso8601String();
        $data['system_onboarding'] = $flow;
        $prospect->forceFill([
            'onboarding_step' => 'system_onboarding_'.$nextStep,
            'data' => $data,
        ])->save();

        $next = $steps[$nextStep - 1];

        return [
            'message' => $next['message'],
            'media_path' => $next['image'],
        ];
    }

    private function normalizeInboundText(string $body): string
    {
        $text = mb_strtolower(trim($body));
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle)
        {
            if ($needle !== '' && str_contains($haystack, $needle))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{sent_media_count: int, missing_media: array<int, string>}
     */
    private function sendStep(WhatsAppGateway $gateway, Team $team, string $digits, int $step, ?int $userId = null): array
    {
        $steps = $this->onboardingSteps();
        $payload = $steps[$step - 1] ?? null;
        if (! $payload)
        {
            return [
                'sent_media_count' => 0,
                'missing_media' => [],
            ];
        }

        $missingMedia = [];
        $sentMedia = 0;

        $normalizedPath = '/'.ltrim($payload['image'], '/');
        $absolute = public_path(ltrim($normalizedPath, '/'));

        if (! file_exists($absolute))
        {
            $missingMedia[] = $normalizedPath;
        } else
        {
            try
            {
                if ($gateway->sendMedia($digits, $normalizedPath, WhatsAppOutboundText::sanitize($payload['message'])))
                {
                    $sentMedia = 1;
                } else
                {
                    $missingMedia[] = $normalizedPath;
                }
            } catch (\Throwable $e)
            {
                $missingMedia[] = $normalizedPath;
                Log::warning('System onboarding media send failed', [
                    'team_id' => $team->id,
                    'phone' => $digits,
                    'path' => $normalizedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent_media_count' => $sentMedia,
            'missing_media' => $missingMedia,
        ];
    }

    private function sleepBetweenSteps(int $currentStep, int $totalSteps): void
    {
        if ($currentStep >= $totalSteps)
        {
            return;
        }

        if (app()->environment('testing'))
        {
            return;
        }

        $this->sleepRandomPause();
    }

    private function sleepRandomPause(): void
    {
        if (app()->environment('testing'))
        {
            return;
        }

        $pauseMs = random_int(self::STEP_PAUSE_MIN_MS, self::STEP_PAUSE_MAX_MS);
        usleep($pauseMs * 1000);
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
