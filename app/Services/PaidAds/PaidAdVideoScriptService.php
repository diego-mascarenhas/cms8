<?php

namespace App\Services\PaidAds;

use App\Enums\PaidAdObjective;
use App\Models\Team;
use App\Services\Business\BusinessProfileService;
use App\Services\TokenUsageLogService;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function Laravel\Ai\agent;

class PaidAdVideoScriptService
{
    /**
     * @param  array{goal: string, name?: string|null, objective?: string|null, locations?: string|null, platforms?: string|null, url?: string|null}  $context
     * @return array{headline: string, body: string, scenes: array<int, array{caption: string, spoken: string, visual: string}>}
     */
    public function suggest(Team $team, array $context): array
    {
        $goal = trim((string) ($context['goal'] ?? ''));
        if ($goal === '')
        {
            throw new RuntimeException(__('Describe qué querés lograr con la campaña.'));
        }

        $userMessage = $this->userMessage($context);
        $brand = app(BusinessProfileService::class)->promptAppendix($team);
        if ($brand !== '')
        {
            $userMessage .= "\n\n".$brand;
        }

        try
        {
            $agent = agent(
                instructions: $this->instructions(),
                messages: [],
                tools: [],
            );
            $response = AiTasks::prompt($agent, $userMessage, 60, 'insight');
            $text = (string) ($response->text ?? '');
        } catch (\Throwable $exception)
        {
            Log::error('PaidAdVideoScriptService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo escribir el guion. Probá de nuevo.'), 0, $exception);
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'PaidAdVideoScriptService',
            usage: $response->usage ?? null,
            moduleKey: 'paid_ads',
            inputSize: strlen($userMessage),
            outputSize: strlen($text),
        );

        return $this->parse($text);
    }

    /**
     * @return array{headline: string, body: string, scenes: array<int, array{caption: string, spoken: string, visual: string}>}
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
            Log::warning('PaidAdVideoScriptService invalid JSON', [
                'text' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException(__('La IA no devolvió un JSON válido. Probá de nuevo.'));
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{headline: string, body: string, scenes: array<int, array{caption: string, spoken: string, visual: string}>}
     */
    public function normalize(array $decoded): array
    {
        $headline = trim((string) ($decoded['headline'] ?? ''));
        $body = trim((string) ($decoded['body'] ?? ''));
        $scenes = $decoded['scenes'] ?? null;

        if ($headline === '' || $body === '' || ! is_array($scenes))
        {
            throw new RuntimeException(__('La IA no devolvió el guion completo. Probá de nuevo.'));
        }

        $normalized = [];
        foreach (array_slice(array_values($scenes), 0, 3) as $scene)
        {
            if (! is_array($scene))
            {
                continue;
            }

            $caption = rtrim(trim((string) ($scene['caption'] ?? '')), ". \t");
            $spoken = trim((string) ($scene['spoken'] ?? ''));
            $visual = trim((string) ($scene['visual'] ?? ''));
            if ($caption === '' || $spoken === '' || $visual === '')
            {
                continue;
            }

            $normalized[] = [
                'caption' => mb_substr($caption, 0, 80),
                'spoken' => mb_substr($spoken, 0, 240),
                'visual' => mb_substr($visual, 0, 400),
            ];
        }

        if (count($normalized) < 3)
        {
            throw new RuntimeException(__('La IA no devolvió las tres escenas. Probá de nuevo.'));
        }

        return [
            'headline' => mb_substr($headline, 0, 80),
            'body' => mb_substr($body, 0, 280),
            'scenes' => $normalized,
        ];
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sos un director de reels de venta. El formato es un anuncio vertical de unos 15 segundos: gancho en pantalla, voz en off y un cierre con acción. Respondé SOLO un JSON válido, sin markdown, con esta forma:
{"headline":"","body":"","scenes":[{"caption":"","spoken":"","visual":""},{"caption":"","spoken":"","visual":""},{"caption":"","spoken":"","visual":""}]}

Reglas:
- Español, salvo que el contexto esté en otro idioma.
- Exactamente 3 escenas, en este orden: gancho, tensión, cierre.
- caption: texto gigante en pantalla, 2 a 6 palabras. Puede ser una pregunta. Sin emojis y sin hashtags.
- spoken: lo que dice la voz, una frase de 8 a 18 palabras. No repitas la caption palabra por palabra.
- visual: una foto realista y concreta, sin texto, sin logos y sin marcas de agua. El sujeto queda en la mitad superior.
- headline: titular del anuncio, máximo 80 caracteres.
- body: texto principal, máximo 280 caracteres, con un llamado a la acción.
- No inventes precios, descuentos ni marcas que no estén en el contexto.
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
