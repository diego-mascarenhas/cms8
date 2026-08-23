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

class PaidAdImageSuggestionService
{
    /**
     * @param  array{goal?: string|null, name?: string|null, headline?: string|null, body?: string|null, objective?: string|null, locations?: string|null, platforms?: string|null, url?: string|null}  $context
     * @return array{hook: string, scene: string, framing: string, palette: array<int, string>, avoid: string, query: string, search: array{google: string, unsplash: string}}
     */
    public function suggest(Team $team, array $context): array
    {
        $userMessage = $this->userMessage($context);
        if (mb_strlen(trim($userMessage)) < 8)
        {
            throw new RuntimeException(__('Completá el titular, el texto o el nombre para sugerir una imagen.'));
        }

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
            $response = $agent->prompt($userMessage, [], AiTasks::provider('insight'));
            $text = (string) ($response->text ?? '');
        } catch (\Throwable $exception)
        {
            Log::error('PaidAdImageSuggestionService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo sugerir la imagen. Probá de nuevo.'), 0, $exception);
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'PaidAdImageSuggestionService',
            usage: $response->usage ?? null,
            moduleKey: 'paid_ads',
            inputSize: strlen($userMessage),
            outputSize: strlen($text),
        );

        return $this->parse($text);
    }

    /**
     * @return array{hook: string, scene: string, framing: string, palette: array<int, string>, avoid: string, query: string, search: array{google: string, unsplash: string}}
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
            Log::warning('PaidAdImageSuggestionService invalid JSON', [
                'text' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException(__('La IA no devolvió un JSON válido. Probá de nuevo.'));
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{hook: string, scene: string, framing: string, palette: array<int, string>, avoid: string, query: string, search: array{google: string, unsplash: string}}
     */
    public function normalize(array $decoded): array
    {
        $hook = trim((string) ($decoded['hook'] ?? ''));
        $scene = trim((string) ($decoded['scene'] ?? ''));
        $framing = trim((string) ($decoded['framing'] ?? ''));
        $avoid = trim((string) ($decoded['avoid'] ?? ''));
        $query = trim((string) ($decoded['query'] ?? ''));
        $palette = $this->normalizePalette($decoded['palette'] ?? []);

        if ($hook === '' || $scene === '')
        {
            throw new RuntimeException(__('La IA no devolvió una escena usable. Probá de nuevo.'));
        }

        $query = mb_substr($query !== '' ? $query : $hook, 0, 120);

        return [
            'hook' => mb_substr($hook, 0, 120),
            'scene' => mb_substr($scene, 0, 400),
            'framing' => mb_substr($framing !== '' ? $framing : 'Cuadrado o vertical, sujeto nítido, espacio limpio para el titular.', 0, 240),
            'palette' => $palette,
            'avoid' => mb_substr($avoid !== '' ? $avoid : 'Evitá texto enorme, marcas inventadas y capturas pixeladas.', 0, 240),
            'query' => $query,
            'search' => $this->searchLinks($query),
        ];
    }

    /**
     * @return array{google: string, unsplash: string}
     */
    public function searchLinks(string $query): array
    {
        $encoded = rawurlencode($query);

        return [
            'google' => 'https://www.google.com/search?udm=2&tbm=isch&q='.$encoded,
            'unsplash' => 'https://unsplash.com/s/photos/'.rawurlencode(str_replace(' ', '-', strtolower($query))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizePalette(mixed $value): array
    {
        $items = is_array($value) ? $value : [];
        $hexes = [];

        foreach ($items as $item)
        {
            $hex = strtoupper(trim((string) $item));
            if (preg_match('/^#?[0-9A-F]{6}$/', $hex) !== 1)
            {
                continue;
            }
            $hexes[] = str_starts_with($hex, '#') ? $hex : '#'.$hex;
            if (count($hexes) === 3)
            {
                break;
            }
        }

        if ($hexes === [])
        {
            return ['#0D9488', '#F4EFE6', '#102428'];
        }

        return $hexes;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sos director de arte de pauta paga. Respondé SOLO un JSON válido, sin markdown, con esta forma:
{"hook":"","scene":"","framing":"","palette":["#0D9488","#F4EFE6","#102428"],"avoid":"","query":""}

Reglas:
- hook: frase corta (máx. 80 caracteres) que resume la foto que hay que subir.
- scene: descripción concreta de la escena, en español, lista para tomarla, buscarla o generarla. Una o dos oraciones.
- framing: encuadre (cuadrado/vertical), dónde va el sujeto y el espacio para el titular.
- palette: exactamente 3 colores hex (#RRGGBB) coherentes con la marca o el anuncio.
- avoid: una oración con lo que no hay que subir (texto enorme, marcas inventadas).
- query: 4 a 8 palabras en inglés para buscar la foto en Google Imágenes o Unsplash. Concretas, sin marcas inventadas.
- No inventes marcas que no estén en el contexto.
PROMPT;
    }

    /**
     * @param  array{goal?: string|null, name?: string|null, headline?: string|null, body?: string|null, objective?: string|null, locations?: string|null, platforms?: string|null, url?: string|null}  $context
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

        $lines = [];
        foreach ([
            'Objetivo de negocio' => $context['goal'] ?? null,
            'Nombre de campaña' => $context['name'] ?? null,
            'Titular' => $context['headline'] ?? null,
            'Texto' => $context['body'] ?? null,
            'Objetivo de pauta' => $objectiveLabel,
            'Ubicaciones' => $context['locations'] ?? null,
            'Redes' => $context['platforms'] ?? null,
            'URL de destino' => $context['url'] ?? null,
        ] as $label => $value)
        {
            $value = trim((string) $value);
            if ($value !== '')
            {
                $lines[] = $label.': '.$value;
            }
        }

        return implode("\n", $lines);
    }
}
