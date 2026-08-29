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

class MailerNewsGenerationService
{
    /**
     * @param  array<string, mixed>  $brief
     * @return array{name: string, text: string, html: string, css: string}
     */
    public function generate(Team $team, array $brief): array
    {
        $userMessage = $this->userMessage($brief);
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
            Log::error('MailerNewsGenerationService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo generar el News. Probá de nuevo.'), 0, $exception);
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'MailerNewsGenerationService',
            usage: $response->usage ?? null,
            moduleKey: 'mailer',
            inputSize: strlen($userMessage),
            outputSize: strlen($text),
        );

        return $this->parse($text);
    }

    /**
     * @return array{name: string, text: string, html: string, css: string}
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
            Log::warning('MailerNewsGenerationService invalid JSON', [
                'text' => mb_substr($text, 0, 500),
            ]);

            throw new RuntimeException(__('La IA no devolvió un JSON válido. Probá de nuevo.'));
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{name: string, text: string, html: string, css: string}
     */
    public function normalize(array $decoded): array
    {
        $name = trim((string) ($decoded['name'] ?? ''));
        $preview = trim((string) ($decoded['text'] ?? ''));
        $html = $this->sanitizeHtml((string) ($decoded['html'] ?? ''));
        $css = $this->sanitizeCss((string) ($decoded['css'] ?? ''));

        if ($name === '' || $preview === '' || $html === '')
        {
            throw new RuntimeException(__('La IA no devolvió asunto, vista previa y HTML. Probá de nuevo.'));
        }

        return [
            'name' => Str::limit($name, 50, ''),
            'text' => Str::limit($preview, 255, ''),
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
Sos copywriter y planner de una agencia de email marketing. Respondé SOLO un JSON válido, sin markdown, con esta forma:
{"name":"","text":"","html":"","css":""}

Reglas:
- name: asunto del correo, máximo 50 caracteres, sin clickbait vacío, en el idioma del brief (español por defecto).
- text: texto de vista previa de bandeja de entrada, máximo 255 caracteres, complementa el asunto (no lo repitas).
- html: SOLO el cuerpo (sin doctype, html, head ni body). HTML simple para TipTap: h2, p, ul/li, strong, em, a, br.
- Incluí merge tags cuando sumen: {{name}}, {{nombre}}, {{email}}, {{phone}}.
- Empezá con un saludo personalizado (Hola {{name}}) salvo que el brief pida otra cosa.
- Un solo mensaje principal. Un llamado a la acción claro (enlace <a>).
- css: opcional. Puede ser string vacío.
- No inventes marcas, precios ni fechas que no estén en el brief. No uses emojis en exceso.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function userMessage(array $brief): string
    {
        $types = [
            'newsletter' => 'Newsletter / novedades',
            'launch' => 'Lanzamiento',
            'promo' => 'Promoción / oferta',
            'reactivate' => 'Reactivación / winback',
            'event' => 'Evento / invitación',
            'onboarding' => 'Onboarding / bienvenida',
            'other' => 'Otro',
        ];
        $tones = [
            'close' => 'Cercano y humano',
            'professional' => 'Profesional',
            'urgent' => 'Directo y urgente',
            'educational' => 'Educativo y claro',
        ];

        $type = (string) ($brief['goal_type'] ?? 'other');
        $tone = (string) ($brief['tone'] ?? 'close');

        $lines = [
            'Tipo de campaña: '.($types[$type] ?? $type),
            'Objetivo de negocio: '.trim((string) ($brief['goal'] ?? '')),
            'Acción que tiene que hacer el lector: '.trim((string) ($brief['cta'] ?? '')),
            'Audiencia: '.trim((string) ($brief['audience'] ?? '')),
            'Novedad / oferta: '.trim((string) ($brief['offer'] ?? '')),
            'Tono: '.($tones[$tone] ?? $tone),
        ];

        foreach ([
            'Beneficios' => $brief['benefits'] ?? '',
            'Urgencia o vencimiento' => $brief['urgency'] ?? '',
            'URL de destino' => $brief['url'] ?? '',
            'Evitar' => $brief['avoid'] ?? '',
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
