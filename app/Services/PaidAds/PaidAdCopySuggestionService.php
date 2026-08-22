<?php

namespace App\Services\PaidAds;

use App\Enums\PaidAdObjective;
use App\Models\Team;
use App\Services\TokenUsageLogService;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function Laravel\Ai\agent;

class PaidAdCopySuggestionService
{
    /**
     * @param  array{goal: string, name?: string|null, objective?: string|null, locations?: string|null, platforms?: string|null, url?: string|null}  $context
     * @return array{headline: string, body: string, interests: string, age_min: int, age_max: int}
     */
    public function suggest(Team $team, array $context): array
    {
        $goal = trim((string) ($context['goal'] ?? ''));
        if ($goal === '')
        {
            throw new RuntimeException(__('Describe qué querés lograr con la campaña.'));
        }

        $userMessage = $this->userMessage($context);

        try
        {
            $agent = agent(
                instructions: $this->instructions(),
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, [], AiTasks::provider('insight'));
            $text = (string) ($response->text ?? '');
        } catch (\Throwable $exception)
        {
            Log::error('PaidAdCopySuggestionService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudieron generar las sugerencias. Probá de nuevo.'), 0, $exception);
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'PaidAdCopySuggestionService',
            usage: $response->usage ?? null,
            moduleKey: 'paid_ads',
            inputSize: strlen($userMessage),
            outputSize: strlen($text),
        );

        return $this->parse($text);
    }

    /**
     * @return array{headline: string, body: string, interests: string, age_min: int, age_max: int}
     */
    public function parse(string $text): array
    {
        $raw = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $raw, $matches) === 1)
        {
            $raw = trim($matches[1]);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded))
        {
            Log::warning('PaidAdCopySuggestionService invalid JSON', [
                'text' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException(__('La IA no devolvió un JSON válido. Probá de nuevo.'));
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{headline: string, body: string, interests: string, age_min: int, age_max: int}
     */
    public function normalize(array $decoded): array
    {
        $headline = trim((string) ($decoded['headline'] ?? ''));
        $body = trim((string) ($decoded['body'] ?? ''));
        $interests = trim((string) ($decoded['interests'] ?? ''));
        $ageMin = (int) ($decoded['age_min'] ?? 18);
        $ageMax = (int) ($decoded['age_max'] ?? 45);

        if ($headline === '' || $body === '')
        {
            throw new RuntimeException(__('La IA no devolvió titular y texto. Probá de nuevo.'));
        }

        $ageMin = max(13, min(65, $ageMin));
        $ageMax = max(13, min(65, $ageMax));
        if ($ageMax < $ageMin)
        {
            [$ageMin, $ageMax] = [$ageMax, $ageMin];
        }

        return [
            'headline' => mb_substr($headline, 0, 255),
            'body' => mb_substr($body, 0, 2000),
            'interests' => mb_substr($interests, 0, 1000),
            'age_min' => $ageMin,
            'age_max' => $ageMax,
        ];
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sos un copywriter de pauta paga. Respondé SOLO un JSON válido, sin markdown, con esta forma:
{"headline":"","body":"","interests":"","age_min":25,"age_max":45}

Reglas:
- headline: titular corto, máximo 80 caracteres, en el idioma del contexto (español por defecto).
- body: texto del anuncio, 1 o 2 párrafos, máximo 400 caracteres, con un llamado a la acción claro.
- interests: intereses de targeting separados por coma, concretos y útiles para Meta/Google (máximo 8).
- age_min y age_max: enteros entre 13 y 65, coherentes con el producto y el objetivo.
- No inventes marcas que no estén en el contexto. No uses emojis en exceso. No agregues claves extra.
PROMPT;
    }

    /**
     * @param  array{goal: string, name?: string|null, objective?: string|null, locations?: string|null, platforms?: string|null, url?: string|null}  $context
     */
    private function userMessage(array $context): string
    {
        $objective = trim((string) ($context['objective'] ?? ''));
        $objectiveLabel = $objective;
        if ($objective !== '')
        {
            $case = PaidAdObjective::tryFrom($objective);
            $objectiveLabel = $case ? $case->label() : $objective;
        }

        $lines = [
            'Objetivo de negocio: '.trim((string) $context['goal']),
        ];

        if (trim((string) ($context['name'] ?? '')) !== '')
        {
            $lines[] = 'Nombre de campaña: '.trim((string) $context['name']);
        }
        if ($objectiveLabel !== '')
        {
            $lines[] = 'Objetivo de pauta: '.$objectiveLabel;
        }
        if (trim((string) ($context['locations'] ?? '')) !== '')
        {
            $lines[] = 'Ubicaciones: '.trim((string) $context['locations']);
        }
        if (trim((string) ($context['platforms'] ?? '')) !== '')
        {
            $lines[] = 'Redes: '.trim((string) $context['platforms']);
        }
        if (trim((string) ($context['url'] ?? '')) !== '')
        {
            $lines[] = 'URL de destino: '.trim((string) $context['url']);
        }

        return implode("\n", $lines);
    }
}
