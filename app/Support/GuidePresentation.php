<?php

namespace App\Support;

class GuidePresentation
{
    /**
     * @var list<string>
     */
    public const SLUGS = [
        'primeros-pasos',
        'chat-contactos-modulos',
        'calendario',
        'tareas',
        'prospeccion',
        'lista-de-60',
        'facturacion',
        'afiliados',
        'cms-wordpress',
        'insight-diario',
        'embudos',
    ];

    public static function isValid(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }

    public static function filePath(string $slug): string
    {
        return public_path('homes/humano/presentations/'.$slug.'.html');
    }

    public static function url(string $slug): string
    {
        return route('presentacion.show', $slug);
    }

    public static function slugPattern(): string
    {
        return implode('|', self::SLUGS);
    }
}
