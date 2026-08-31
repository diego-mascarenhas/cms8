<?php

namespace App\Support;

use App\Helpers\WhatsAppOutboundText;

class AssistantCustomerText
{
    private const TOOL_NAME_PATTERN = 'search_contacts|get_contact_detail|create_contact|update_contact|check_calendar_availability|create_calendar_event|list_calendar_events|update_calendar_event|list_product_catalog|search_products|add_to_whatsapp_cart|view_whatsapp_cart|confirm_whatsapp_order|commit_assistant_flow';

    /**
     * Remove tool machine markers the model sometimes echoes as the reply.
     */
    public static function stripMachineMarkers(string $text): string
    {
        $text = preg_replace('/FLOW_COMMITTED:\s*\{[^{}]*\}/u', '', $text) ?? $text;
        $text = self::stripLeakedToolJson($text);
        $lines = preg_split('/\R/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line)
        {
            $trimmed = trim($line);
            if ($trimmed === ''
                || preg_match('/^```(?:json|javascript|js)?$/i', $trimmed)
                || str_starts_with($trimmed, 'FLOW_COMMITTED:')
                || preg_match('/^(?:'.self::TOOL_NAME_PATTERN.')\s*\([^)]*\)\s*;?$/u', $trimmed))
            {
                continue;
            }
            $kept[] = $line;
        }

        $out = trim(implode("\n", $kept));
        if ($out === '' || strcasecmp($out, 'No response text') === 0)
        {
            return '';
        }

        return WhatsAppOutboundText::sanitize($out);
    }

    public static function leakedToolName(string $text): ?string
    {
        if (preg_match('/\{\s*"(?:tool|name)"\s*:\s*"([a-z0-9_]+)"/u', $text, $matches))
        {
            return $matches[1];
        }
        if (preg_match('/\b('.self::TOOL_NAME_PATTERN.')\s*\(/u', $text, $matches))
        {
            return $matches[1];
        }

        return null;
    }

    public static function looksLikeToolStall(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '')
        {
            return true;
        }

        return (bool) preg_match(
            '/dame un momento|un segundo|voy a (?:consultar|buscar)|déjame (?:revisar|consultar)|espera(?:me)? un momento|paso\s*\d+|let me (?:check|look)/u',
            $normalized,
        );
    }

    public static function afterLeakedToolFallback(?string $toolName): string
    {
        $tool = mb_strtolower((string) $toolName);
        if (str_contains($tool, 'calendar'))
        {
            return '¿Qué horario te viene bien? Decime día y hora y lo confirmo en la agenda.';
        }

        return 'Dale. ¿Me contás un poco más para seguir?';
    }

    private static function stripLeakedToolJson(string $text): string
    {
        $text = preg_replace('/```(?:json|javascript|js)?\s*\{\s*"(?:tool|name)"\s*:[\s\S]*?\}\s*```/u', '', $text) ?? $text;
        $text = preg_replace('/```(?:json|javascript|js)?\s*(?:'.self::TOOL_NAME_PATTERN.')\s*\([^)]*\)\s*```/u', '', $text) ?? $text;
        $text = preg_replace('/\{\s*"tool"\s*:\s*"[a-z0-9_]+"[^{}]*\}/u', '', $text) ?? $text;
        $text = preg_replace('/\{\s*"name"\s*:\s*"[a-z0-9_]+"\s*,\s*"arguments"\s*:\s*\{[^{}]*\}\s*\}/u', '', $text) ?? $text;

        return $text;
    }

    /**
     * @return array{routing_key?: string, label?: string}|null
     */
    public static function parseCommittedPayload(string $text): ?array
    {
        if (! preg_match('/FLOW_COMMITTED:\s*(\{[^{}]*\})/u', $text, $matches))
        {
            return null;
        }

        $payload = json_decode($matches[1], true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array{routing_key?: string, label?: string}|null  $committed
     */
    public static function afterCommitFallback(?array $committed): string
    {
        $label = trim((string) ($committed['label'] ?? ''));
        $key = mb_strtolower(trim((string) ($committed['routing_key'] ?? '')));
        if (str_contains($key, 'presupuesto') || str_contains(mb_strtolower($label), 'presupuesto'))
        {
            return 'Dale, te armo el pedido. ¿Qué necesitan y para cuándo lo quieren?';
        }
        if ($label !== '')
        {
            return 'Dale, seguimos con '.$label.'. ¿Por dónde empezamos?';
        }

        return 'Dale. ¿Me contás un poco más para armarlo?';
    }
}
