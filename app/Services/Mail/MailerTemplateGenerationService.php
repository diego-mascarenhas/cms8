<?php

namespace App\Services\Mail;

use App\Models\Team;
use App\Services\Business\BusinessProfileService;
use App\Services\TokenUsageLogService;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

use function Laravel\Ai\agent;

class MailerTemplateGenerationService
{
    /**
     * @param  array{prompt: string, name?: string|null}  $context
     * @return array{name: string, html: string, css: string}
     */
    public function generate(Team $team, array $context): array
    {
        $prompt = trim((string) ($context['prompt'] ?? ''));
        if ($prompt === '')
        {
            throw new RuntimeException(__('Contá qué tipo de plantilla querés crear.'));
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
            $response = $agent->prompt($userMessage, [], AiTasks::provider('insight'));
            $text = (string) ($response->text ?? '');
        } catch (\Throwable $exception)
        {
            Log::error('MailerTemplateGenerationService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo generar la plantilla. Probá de nuevo.'), 0, $exception);
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'MailerTemplateGenerationService',
            usage: $response->usage ?? null,
            moduleKey: 'templates',
            inputSize: strlen($userMessage),
            outputSize: strlen($text),
        );

        return $this->parse($text);
    }

    /**
     * @return array{name: string, html: string, css: string}
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
            Log::warning('MailerTemplateGenerationService invalid JSON', [
                'text' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException(__('La IA no devolvió un JSON válido. Probá de nuevo.'));
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{name: string, html: string, css: string}
     */
    public function normalize(array $decoded): array
    {
        $name = trim((string) ($decoded['name'] ?? ''));
        $html = $this->sanitizeHtml((string) ($decoded['html'] ?? ''));
        $css = $this->sanitizeCss((string) ($decoded['css'] ?? ''));

        if ($name === '' || $html === '')
        {
            throw new RuntimeException(__('La IA no devolvió nombre y HTML. Probá de nuevo.'));
        }

        return [
            'name' => Str::limit($name, 75, ''),
            'html' => $html,
            'css' => $css,
        ];
    }

    private function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $matches) === 1)
        {
            $html = $matches[1];
        }

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;

        return trim(strip_tags($html, '<p><br><h1><h2><h3><h4><strong><b><em><i><u><ul><ol><li><a><span><div><table><thead><tbody><tr><td><th><img>'));
    }

    private function sanitizeCss(string $css): string
    {
        $css = trim($css);
        $css = preg_replace('/<\/?style\b[^>]*>/i', '', $css) ?? $css;

        return trim($css);
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sos un diseñador de emails para Idoneo Mailer. Respondé SOLO un JSON válido, sin markdown, con esta forma:
{"name":"","html":"","css":""}

Reglas:
- name: título corto de la plantilla, máximo 75 caracteres, en el idioma del pedido (español por defecto).
- html: SOLO el cuerpo (sin doctype, html, head ni body). Usá HTML simple que TipTap pueda editar: h2, p, ul/li, strong, em, a, br.
- Incluí merge tags cuando sumen: {{name}}, {{nombre}}, {{email}}, {{phone}}.
- Empezá con un saludo personalizado (Hola {{name}}) salvo que el pedido pida otra cosa.
- Cerrá con un llamado a la acción claro (un enlace <a href="#">).
- css: reglas extra opcionales (clases simples). Puede ser string vacío.
- No uses scripts, formularios, fuentes externas ni layouts complejos de tablas.
- No inventes marcas que no estén en el contexto. No uses emojis en exceso.
PROMPT;
    }

    /**
     * @param  array{prompt: string, name?: string|null}  $context
     */
    private function userMessage(array $context): string
    {
        $lines = [
            'Tipo de plantilla: '.trim((string) $context['prompt']),
        ];

        $name = trim((string) ($context['name'] ?? ''));
        if ($name !== '')
        {
            $lines[] = 'Nombre sugerido por el usuario: '.$name;
        }

        return implode("\n", $lines);
    }
}
