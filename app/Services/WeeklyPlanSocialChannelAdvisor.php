<?php

namespace App\Services;

use App\Models\Team;

class WeeklyPlanSocialChannelAdvisor
{
    /**
     * Recommend where to publish this week from challenge, industry and connected profiles.
     *
     * @return array{channel: string, label: string, reason: string, href: ?string}
     */
    public function recommend(Team $team): array
    {
        $config = $this->businessConfig($team);
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($config['business_challenge'] ?? ''),
            (string) ($config['business_industry'] ?? ''),
            (string) ($config['business_description'] ?? ''),
            (string) ($config['business_tagline'] ?? ''),
        ]))));

        $connected = $this->connectedProfiles($config);
        $scores = [
            'linkedin' => 12,
            'instagram' => 10,
            'tiktok' => 6,
            'youtube' => 5,
            'facebook' => 5,
            'x' => 4,
        ];

        foreach ($this->keywordBoosts() as $channel => $keywords)
        {
            foreach ($keywords as $keyword)
            {
                if ($keyword !== '' && str_contains($haystack, $keyword))
                {
                    $scores[$channel] = ($scores[$channel] ?? 0) + 8;
                }
            }
        }

        foreach ($connected as $channel => $url)
        {
            if ($url !== '')
            {
                $scores[$channel] = ($scores[$channel] ?? 0) + 5;
            }
        }

        arsort($scores);
        $channel = (string) array_key_first($scores);
        $meta = $this->channelMeta($channel);

        return [
            'channel' => $channel,
            'label' => $meta['label'],
            'reason' => $this->reason($channel, $haystack, $connected),
            'href' => $connected[$channel] ?? $meta['fallback_href'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessConfig(Team $team): array
    {
        $saved = $team->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }

        return is_array($saved) ? $saved : [];
    }

    /**
     * @return array<string, string>
     */
    private function connectedProfiles(array $config): array
    {
        $map = [
            'linkedin' => (string) ($config['linkedin'] ?? ''),
            'instagram' => (string) ($config['instagram'] ?? ''),
            'tiktok' => (string) ($config['tiktok'] ?? ''),
            'youtube' => (string) ($config['youtube'] ?? ''),
            'facebook' => (string) ($config['facebook'] ?? ''),
            'x' => (string) ($config['twitter'] ?? ''),
        ];

        return array_map(static fn (string $url): string => trim($url), $map);
    }

    /**
     * @return array<string, list<string>>
     */
    private function keywordBoosts(): array
    {
        return [
            'linkedin' => [
                'b2b', 'saas', 'consultor', 'consulting', 'empresa', 'empresas', 'profesional',
                'propuesta', 'propuestas', 'lead', 'leads', 'crm', 'software', 'servicio',
                'servicios', 'corporativ', 'negocio', 'negocios', 'decisor', 'venta consultiva',
            ],
            'instagram' => [
                'producto', 'productos', 'marca', 'visual', 'diseño', 'design', 'moda', 'belleza',
                'retail', 'ecommerce', 'e-commerce', 'tienda', 'foto', 'lifestyle', 'local',
            ],
            'tiktok' => [
                'viral', 'gen z', 'jóven', 'joven', 'tendencia', 'short', 'reels', 'entreten',
                'app consumer', 'consumo',
            ],
            'youtube' => [
                'tutorial', 'demo', 'formación', 'formacion', 'curso', 'webinar', 'explicar',
                'educativ', 'largo', 'video largo',
            ],
            'facebook' => [
                'comunidad', 'evento', 'eventos', 'grupo', 'grupos', 'local', 'barrio', 'pymes',
                'familia',
            ],
            'x' => [
                'noticia', 'prensa', 'opinión', 'opinion', 'tech', 'startup', 'thread',
            ],
        ];
    }

    /**
     * @return array{label: string, fallback_href: string}
     */
    private function channelMeta(string $channel): array
    {
        return match ($channel)
        {
            'linkedin' => [
                'label' => 'LinkedIn',
                'fallback_href' => 'https://www.linkedin.com/',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'fallback_href' => 'https://www.instagram.com/',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'fallback_href' => 'https://www.tiktok.com/',
            ],
            'youtube' => [
                'label' => 'YouTube',
                'fallback_href' => 'https://www.youtube.com/',
            ],
            'facebook' => [
                'label' => 'Facebook',
                'fallback_href' => 'https://www.facebook.com/',
            ],
            default => [
                'label' => 'X (Twitter)',
                'fallback_href' => 'https://x.com/',
            ],
        };
    }

    /**
     * @param  array<string, string>  $connected
     */
    private function reason(string $channel, string $haystack, array $connected): string
    {
        $hasProfile = ($connected[$channel] ?? '') !== '';
        $isB2b = str_contains($haystack, 'b2b')
            || str_contains($haystack, 'propuesta')
            || str_contains($haystack, 'saas')
            || str_contains($haystack, 'consult');

        $base = match ($channel)
        {
            'linkedin' => $isB2b
                ? (string) __('app.weekly_plan_social_reason_linkedin_b2b')
                : (string) __('app.weekly_plan_social_reason_linkedin'),
            'instagram' => (string) __('app.weekly_plan_social_reason_instagram'),
            'tiktok' => (string) __('app.weekly_plan_social_reason_tiktok'),
            'youtube' => (string) __('app.weekly_plan_social_reason_youtube'),
            'facebook' => (string) __('app.weekly_plan_social_reason_facebook'),
            default => (string) __('app.weekly_plan_social_reason_x'),
        };

        if ($hasProfile)
        {
            return $base.' '.(string) __('app.weekly_plan_social_reason_connected');
        }

        return $base;
    }
}
