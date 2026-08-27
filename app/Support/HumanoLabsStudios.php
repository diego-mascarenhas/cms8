<?php

namespace App\Support;

class HumanoLabsStudios
{
    /**
     * Partner studios that take a consultoría problem after Desafío.
     * More specific keys are listed first so match() prefers them over consulting.
     *
     * @return list<array{key: string, area: string, name: string, url: string, keywords: list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'signage',
                'area' => 'Señalética digital',
                'name' => 'Global Trafic',
                'url' => 'https://globaltraffic.com',
                'keywords' => [
                    'senaletica',
                    'carteleria',
                    'pantallas digitales',
                    'pantalla digital',
                    'totem',
                    'digital signage',
                    'global trafic',
                    'global traffic',
                ],
            ],
            [
                'key' => 'hosting',
                'area' => 'Hosting',
                'name' => 'REVISION ALPHA',
                'url' => 'https://revisionalpha.com',
                'keywords' => [
                    'hosting',
                    'servidor',
                    'servidores',
                    'cpanel',
                    'correo corporativo',
                    'revision alpha',
                    'revisionalpha',
                ],
            ],
            [
                'key' => 'design',
                'area' => 'Diseño',
                'name' => 'Mix Vasallo',
                'url' => 'https://mixvasallo.com',
                'keywords' => [
                    'diseno',
                    'branding',
                    'identidad visual',
                    'identidad de marca',
                    'logo',
                    'la marca',
                    'cambio de imagen',
                    'cambiar mi imagen',
                    'imagen de marca',
                    'mix vasallo',
                    'mixvasallo',
                ],
            ],
            [
                'key' => 'marketing',
                'area' => 'Marketing',
                'name' => 'CONGA',
                'url' => 'https://somosconga.com',
                'keywords' => [
                    'marketing',
                    'publicidad',
                    'anuncios',
                    'campanas',
                    'somosconga',
                    'conga',
                ],
            ],
            [
                'key' => 'innovation',
                'area' => 'Innovación',
                'name' => 'FANYION',
                'url' => 'https://fanyion.com',
                'keywords' => [
                    'innovacion',
                    'fanyion',
                    'laboratorio de ideas',
                ],
            ],
            [
                'key' => 'development',
                'area' => 'Desarrollo',
                'name' => 'IDONEO',
                'url' => 'https://idoneo.dev',
                'keywords' => [
                    'desarrollo',
                    'software a medida',
                    'app a medida',
                    'aplicacion a medida',
                    'sitio web',
                    'pagina web',
                    'website',
                    'idoneo.dev',
                ],
            ],
            [
                'key' => 'consulting',
                'area' => 'Consultoría de negocio',
                'name' => 'HUMANO Labs',
                'url' => 'https://humano.app',
                'keywords' => [
                    'estrategia',
                    'crecimiento',
                    'crecer',
                    'desorden',
                    'mentor',
                    'mentoria',
                    'desafio',
                    'diagnostico',
                    'escalar',
                    '12 pasos',
                    'problematica',
                    'consultoria',
                    'consultoria tecnica',
                    'auditoria',
                    'audit previo',
                    'humano labs',
                    'depende de una',
                    'depende de uno',
                    'depende de mi',
                    'el negocio',
                    'del negocio',
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, area: string, name: string, url: string, keywords: list<string>}|null
     */
    public static function match(string $normalized): ?array
    {
        foreach (self::all() as $studio)
        {
            foreach ($studio['keywords'] as $keyword)
            {
                if ($keyword !== '' && str_contains($normalized, $keyword))
                {
                    return $studio;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, area: string, name: string, url: string, keywords: list<string>}>
     */
    public static function matchAll(string $normalized): array
    {
        $hits = [];

        foreach (self::all() as $studio)
        {
            foreach ($studio['keywords'] as $keyword)
            {
                if ($keyword !== '' && str_contains($normalized, $keyword))
                {
                    $hits[] = $studio;
                    break;
                }
            }
        }

        return $hits;
    }

    public static function offerLine(): string
    {
        $areas = [];
        foreach (['consulting', 'design', 'development', 'hosting', 'marketing', 'innovation', 'signage'] as $key)
        {
            foreach (self::all() as $studio)
            {
                if ($studio['key'] === $key)
                {
                    $areas[] = $studio['area'];
                    break;
                }
            }
        }

        $last = array_pop($areas);

        return implode(', ', $areas).' o '.$last;
    }

    public static function promptBlock(): string
    {
        $lines = [];
        foreach (self::all() as $studio)
        {
            $lines[] = '- **'.$studio['area'].'** — '.$studio['name'].' ('.$studio['url'].')';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{key: string, area: string, name: string, url: string, keywords: list<string>}  $studio
     */
    public static function handoffMessage(array $studio): string
    {
        $line = match ($studio['key'])
        {
            'design' => 'Eso es marca e identidad visual: lo vemos con '.$studio['name'].'.',
            'development' => 'Eso es un sitio o software a medida: lo vemos en '.$studio['name'].'.',
            'hosting' => 'Eso es infraestructura: lo vemos en '.$studio['name'].'.',
            'marketing' => 'Eso es campañas y publicidad: lo vemos en '.$studio['name'].'.',
            'innovation' => 'Eso es innovación de proceso: lo vemos en '.$studio['name'].'.',
            'signage' => 'Eso es señalética digital: lo vemos en '.$studio['name'].'.',
            default => 'Eso lo vemos en '.$studio['name'].'.',
        };

        return $line."\n".$studio['url']."\n\n¿Qué les gustaría cambiar?";
    }
}
