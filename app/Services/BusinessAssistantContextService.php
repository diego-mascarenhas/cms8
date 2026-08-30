<?php

namespace App\Services;

use App\Models\Team;

/**
 * Builds a markdown block from team {@see Team::getSetting('business_config')} for LLM system instructions.
 * Omits `business_challenge` (internal/strategic text): kept for wizard and AI summary flows, not sent to the chat assistant.
 * Omits `first_name` and `birth_date` (titular): not sent to any assistant context.
 */
class BusinessAssistantContextService
{
    private const MAX_CHARS = 8000;

    private const COMPACT_MAX_CHARS = 1200;

    /** @var array<string, string> */
    private const LABELS = [
        'business_name' => 'Nombre del negocio',
        'business_industry' => 'Rubro / sector',
        'business_location' => 'Ubicación',
        'business_postal_code' => 'Código postal',
        'business_phone' => 'Teléfono',
        'business_whatsapp' => 'WhatsApp (negocio)',
        'business_website' => 'Sitio web',
        'business_email' => 'Email del negocio',
        'contact_email' => 'Email de contacto',
        'business_tagline' => 'Eslogan',
        'business_description' => 'Descripción',
        'last_name' => 'Apellido (titular)',
        'birth_time' => 'Hora de nacimiento (titular)',
        'country' => 'País',
        'language' => 'Idioma',
        'address' => 'Dirección',
        'landmark' => 'Referencia / punto de encuentro',
        'pincode' => 'PIN / código postal (titular)',
        'city' => 'Ciudad',
        'twitter' => 'Twitter / X',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'whatsapp_url' => 'Enlace WhatsApp',
        'telegram' => 'Telegram',
        'pinterest' => 'Pinterest',
        'threads' => 'Threads',
        'wants_to_deepen' => 'Interés en profundizar (sí/no)',
    ];

    /**
     * Markdown appendix for the assistant, or empty string if nothing is configured.
     */
    public function buildMarkdownAppendix(?int $teamId, bool $compact = false): string
    {
        if ($teamId === null || $teamId <= 0)
        {
            return '';
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if (! $team)
        {
            return '';
        }

        $config = $team->getSetting('business_config', []);
        if (is_string($config))
        {
            $config = json_decode($config, true) ?: [];
        }
        if (! is_array($config))
        {
            $config = [];
        }
        if (trim((string) ($config['business_name'] ?? '')) === '')
        {
            $config['business_name'] = $team->name;
        }

        $businessKeys = $compact
            ? ['business_name', 'business_industry', 'business_location', 'business_tagline', 'business_description']
            : [
                'business_name', 'business_industry', 'business_location', 'business_postal_code',
                'business_phone', 'business_whatsapp', 'business_website', 'business_email',
                'contact_email', 'business_tagline', 'business_description',
            ];
        $ownerKeys = $compact ? [] : [
            'last_name', 'birth_time', 'country', 'language',
            'address', 'landmark', 'pincode', 'city',
        ];
        $socialKeys = $compact ? [] : [
            'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok',
            'whatsapp_url', 'telegram', 'pinterest', 'threads',
        ];
        $otherKeys = $compact ? [] : ['wants_to_deepen'];

        $sections = [];

        $bizLines = $this->linesForKeys($config, $businessKeys);
        if ($bizLines !== [])
        {
            $sections[] = "### Negocio\n\n".implode("\n", $bizLines);
        }

        $ownerLines = $this->linesForKeys($config, $ownerKeys);
        if ($ownerLines !== [])
        {
            $sections[] = "### Titular / contacto principal\n\n".implode("\n", $ownerLines);
        }

        $socialLines = $this->linesForKeys($config, $socialKeys);
        if ($socialLines !== [])
        {
            $sections[] = "### Redes y enlaces\n\n".implode("\n", $socialLines);
        }

        $otherLines = $this->linesForKeys($config, $otherKeys);
        if ($otherLines !== [])
        {
            $sections[] = "### Otros\n\n".implode("\n", $otherLines);
        }

        if ($sections === [])
        {
            return '';
        }

        $intro = '### Contexto del negocio (configuración del equipo)'."\n\n";
        $intro .= 'Equipo: **'.$team->name."**.\n\n";
        $intro .= 'Estos datos vienen de la configuración del negocio. Presentate como parte de este equipo; **no inventes** otra marca ni datos que no aparezcan aquí.'."\n\n";
        $body = implode("\n\n", $sections);
        $out = $intro.$body;
        $maxChars = $compact ? self::COMPACT_MAX_CHARS : self::MAX_CHARS;

        if (strlen($out) > $maxChars)
        {
            return substr($out, 0, $maxChars)."\n\n_(Contenido truncado por límite de tamaño.)_";
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function linesForKeys(array $config, array $keys): array
    {
        $lines = [];
        foreach ($keys as $key)
        {
            if (! isset(self::LABELS[$key]))
            {
                continue;
            }
            $value = $config[$key] ?? null;
            if ($value === null || $value === '' || $value === [])
            {
                continue;
            }
            if (is_array($value))
            {
                $value = implode(', ', array_map('strval', $value));
            }
            $text = trim((string) $value);
            if ($text === '')
            {
                continue;
            }
            $lines[] = '- **'.self::LABELS[$key].':** '.$text;
        }

        return $lines;
    }
}
