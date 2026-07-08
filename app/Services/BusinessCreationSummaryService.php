<?php

namespace App\Services;

use App\Models\BusinessCreationAiLog;
use App\Models\BusinessCreationSession;
use App\Models\Prompt;
use App\Support\AiTasks;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class BusinessCreationSummaryService
{
    public function __construct(
        protected AssistantChatService $assistantChatService,
        protected AstralChartService $astralChartService,
    ) {}

    public function run(BusinessCreationSession $session): void
    {
        $config = $session->config ?? [];
        $challenge = trim((string) ($config['business_challenge'] ?? ''));
        $hash = $challenge !== '' ? hash('sha256', $challenge) : '';

        $saved = $session->fresh()->config ?? [];
        if ($hash !== '' && ($saved['_summary_challenge_hash'] ?? '') === $hash && isset($saved['_summary']) && $saved['_summary'] !== '')
        {
            return;
        }

        $contextParts = [];
        $contextParts[] = 'Datos del negocio:';
        $contextParts[] = '- Nombre: '.trim((string) ($config['business_name'] ?? ''));
        $contextParts[] = '- Rubro/Sector: '.trim((string) ($config['business_industry'] ?? ''));
        $contextParts[] = '- Ubicación: '.trim((string) ($config['business_location'] ?? ''));
        $contextParts[] = '- Código postal: '.trim((string) ($config['business_postal_code'] ?? ''));
        $contextParts[] = '- Teléfono: '.trim((string) ($config['business_phone'] ?? ''));
        $contextParts[] = '- WhatsApp: '.trim((string) ($config['business_whatsapp'] ?? ''));
        $contextParts[] = '- Página web: '.trim((string) ($config['business_website'] ?? ''));
        $contextParts[] = '- Email: '.trim((string) ($config['business_email'] ?? ''));
        $contextParts[] = '- Eslogan: '.trim((string) ($config['business_tagline'] ?? ''));
        $contextParts[] = '- Descripción: '.trim((string) ($config['business_description'] ?? ''));

        $arquetipoContext = $this->buildArquetipoContext($config);
        if ($arquetipoContext !== '')
        {
            $contextParts[] = '';
            $contextParts[] = 'Arquetipo humano (fecha y hora de nacimiento):';
            $contextParts[] = $arquetipoContext;
        }

        $context = implode("\n", $contextParts);
        $userMessage = $challenge !== ''
            ? "Problemática actual del negocio:\n\n".$challenge."\n\n---\n\n".$context
            : $context;

        try
        {
            $aiStartedAt = now();
            $prompt = Prompt::findByRoutingKey('landing');
            if ($prompt)
            {
                $result = $this->assistantChatService->run($userMessage, null, null, null, false, 'landing');
                $summary = $result['response'] ?? '';
            } else
            {
                $defaultInstruction = 'Eres un consultor de negocio. Con el contexto que te proporcionan (datos del negocio, problemática actual y arquetipo humano por fecha de nacimiento), genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.';
                $agent = agent(instructions: $defaultInstruction, messages: [], tools: []);
                $response = $agent->prompt($userMessage, [], AiTasks::provider('summary'));
                $summary = $response->text ?? '';

                TokenUsageLogService::logFromAiResponse(
                    teamId: (int) $session->team_id,
                    service: 'BusinessCreationSummaryService',
                    usage: $response->usage ?? null,
                    moduleKey: 'landings',
                    inputSize: strlen($userMessage),
                );
            }
            $aiFinishedAt = now();

            $session->refresh();
            $metadata = $session->getStepMetadata();
            $metadata['ai_started_at'] = $aiStartedAt->toIso8601String();
            $metadata['ai_finished_at'] = $aiFinishedAt->toIso8601String();
            $metadata['ai_duration_seconds'] = (int) $aiStartedAt->diffInSeconds($aiFinishedAt);
            $metadata['desafio_prompt'] = $challenge;
            BusinessCreationAiLog::create([
                'business_creation_session_id' => $session->id,
                'type' => 'summary',
                'request_payload' => $userMessage,
                'response_payload' => $summary,
                'metadata' => $metadata,
            ]);
            $current = $session->fresh()->config ?? [];
            $current['_summary'] = $summary;
            $current['_summary_challenge_hash'] = $hash;
            $session->update(['config' => $current]);
        } catch (\Throwable $e)
        {
            Log::error('Business creation summary job failed', ['error' => $e->getMessage(), 'session_id' => $session->id]);
            $current = $session->fresh()->config ?? [];
            $current['_summary'] = 'Error al generar el resumen. Intenta de nuevo.';
            $current['_summary_challenge_hash'] = $hash;
            $session->update(['config' => $current]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildArquetipoContext(array $config): string
    {
        $birthDate = $config['birth_date'] ?? null;
        if (! $birthDate)
        {
            return '';
        }
        try
        {
            $birthCarbon = Carbon::parse($birthDate);
            $zodiac = $this->astralChartService->getZodiacSign($birthCarbon);
            $northNode = $this->astralChartService->getNorthNode($birthCarbon);
            $lines = [
                '- Signo zodiacal: '.($zodiac['sign'] ?? '').' '.($zodiac['symbol'] ?? '').' ('.($zodiac['element'] ?? '').')',
                '- Nodo Norte: '.($northNode['north'] ?? ''),
                '- Nodo Sur: '.($northNode['south'] ?? ''),
            ];
            if (! empty($config['birth_time']))
            {
                $lines[] = '- Hora de nacimiento: '.$config['birth_time'];
            }

            return implode("\n", $lines);
        } catch (\Throwable $e)
        {
            Log::warning('AstralChartService in business creation summary', ['error' => $e->getMessage()]);

            return '';
        }
    }
}
