<?php

namespace App\Support;

class LlmsTxt
{
    public static function origin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function body(?string $origin = null): string
    {
        $origin = rtrim($origin ?? self::origin(), '/');

        return <<<TXT
# IDONEO Admin

> Panel de IDONEO en {$origin}. Un equipo entra con su cuenta y opera el negocio desde ahí: contactos, proyectos, facturación, marketing, comunicaciones y contenido.

IDONEO (https://idoneo.dev) construye el software. Este dominio es el administrador. No es una tienda ni un sitio de artículos: la parte pública explica el producto y el resto pide iniciar sesión.

## Qué hace

- Contactos, clientes, proyectos, oportunidades y tareas
- Suscripciones, facturas y pagos
- Campañas y anuncios
- Correo y WhatsApp
- CMS: publicar entradas y páginas, sincronizar con WordPress y responder con el asistente

## Páginas

- [CMS]({$origin}/cms): el sitio se gestiona desde Humano, se sincroniza con WordPress y el visitante consulta con el asistente
- [Entrar]({$origin}/login): acceso del equipo

## Productos relacionados

- [Pedimos Fácil](https://pedimosfacil.com): tiendas online con pedidos por WhatsApp
- [Wapify](https://wapify.me): el negocio en WhatsApp
- [SimpleMailer](https://simplemailer.online): email marketing
- [Growhit](https://growhit.net): el camino hasta el cliente
- [Humano](https://humano.website): consultoría tecnológica
TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public static function jsonLd(?string $origin = null): array
    {
        $origin = rtrim($origin ?? self::origin(), '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'IDONEO Admin',
            'url' => $origin,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => 'Panel de IDONEO para operar un equipo: contactos, proyectos, facturación, marketing, comunicaciones y CMS.',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'IDONEO',
                'url' => 'https://idoneo.dev',
            ],
        ];
    }

    public static function jsonScript(?string $origin = null): string
    {
        return str_replace(
            '<',
            '\u003c',
            (string) json_encode(
                self::jsonLd($origin),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        );
    }
}
