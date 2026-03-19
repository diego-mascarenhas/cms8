<?php

namespace App\Services;

use App\Models\Team;

class AssistantCapabilitiesOverviewService
{
    /**
     * Phrases / words that suggest the user wants a tour or to try the assistant (Spanish + English).
     *
     * @var list<string>
     */
    protected const EXPLORATION_NEEDLES = [
        'demo',
        'demostración',
        'demostracion',
        'probar',
        'prueba',
        'probando',
        'quiero probar',
        'probar el asistente',
        'asistente de compra',
        'probar la compra',
        'tutorial',
        'recorrido',
        'qué puedes hacer',
        'que puedes hacer',
        'qué podés hacer',
        'que podés hacer',
        'qué sabes hacer',
        'que sabes hacer',
        'funciones',
        'capacidades',
        'ayuda del asistente',
        'cómo funciona',
        'como funciona',
        'show me what you can',
        'try out',
        'test drive',
        'what can you do',
    ];

    /**
     * Whether the user message looks like they want an exploration / demo intro.
     */
    public function matchesExplorationIntent(string $message): bool
    {
        $normalized = $this->normalizeForMatching($message);
        if ($normalized === '')
        {
            return false;
        }

        foreach (self::EXPLORATION_NEEDLES as $needle)
        {
            if (str_contains($normalized, $needle))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Offer the overview only near the start of the thread (first few user messages in the window).
     */
    public function shouldOfferOverview(array $history, string $message, bool $withTools): bool
    {
        if (! $withTools || ! $this->matchesExplorationIntent($message))
        {
            return false;
        }

        return $this->countInboundTurns($history) <= 2;
    }

    /**
     * Spanish overview of tools connected for this team (WhatsApp-friendly).
     */
    public function buildOverviewMessage(Team $team): string
    {
        $team->loadMissing('modules');

        $lines = [];
        $lines[] = '¡Perfecto! 🙌 Podés *probar el asistente con datos reales* de tu equipo.';
        $lines[] = '';
        $lines[] = '*Qué podés pedirme ahora (según tu configuración):*';
        $lines[] = '';

        if ($team->hasModule('products'))
        {
            $lines[] = '🛒 *Compras / catálogo*: ver *productos* o *catálogo*, buscar por nombre o código, sumar al *carrito* y cerrar con *checkout* (WhatsApp).';
        } else
        {
            $lines[] = '🛒 *Compras / catálogo*: el módulo de *productos* no está activo para este equipo. Activarlo en el panel permite vender y que el asistente use catálogo y carrito por WhatsApp.';
        }

        $lines[] = '👤 *Contactos*: crear o actualizar contactos, categorías, listados e informes.';
        $lines[] = '📅 *Agenda*: *reservar una cita*, consultar disponibilidad y crear o cambiar eventos en el calendario del equipo.';
        $lines[] = '📧 *Email marketing*: listar o *crear plantillas*, y *crear campañas* (News) eligiendo plantilla, canal y audiencia.';
        $lines[] = '✅ *Tareas y equipo*: crear tareas, ver miembros del equipo y resúmenes.';
        $lines[] = '💬 *WhatsApp*: enviar mensajes a contactos (cuando corresponda).';

        if ($team->hasModule('tickets'))
        {
            $lines[] = '🎫 *Soporte*: crear o responder *tickets* de soporte.';
        }

        $lines[] = '';
        $lines[] = '*Adaptarlo a tu negocio*';
        $appName = config('app.name', 'Humano CRM');
        $baseUrl = rtrim((string) config('app.url'), '/');
        $lines[] = "En *{$appName}* podés configurar tu equipo, activar módulos (productos, campañas, tickets, etc.) y cargar tus datos para que el asistente quede alineado con *tu* operación.";
        if ($baseUrl !== '')
        {
            $lines[] = "Panel: {$baseUrl}";
        }
        $lines[] = '';
        $lines[] = 'Decime por dónde querés empezar (por ejemplo: “mostrame el catálogo”, “quiero agendar una cita mañana” o “crear una plantilla de email”).';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>|object>  $history
     */
    protected function countInboundTurns(array $history): int
    {
        $n = 0;
        foreach ($history as $row)
        {
            $direction = is_array($row) ? ($row['direction'] ?? '') : ($row->direction ?? '');
            if ($direction === 'inbound')
            {
                $n++;
            }
        }

        return $n;
    }

    protected function normalizeForMatching(string $message): string
    {
        $t = mb_strtolower(trim($message));
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t, " \t\n\r\0\x0B!?.¡¿");
    }
}
