<?php

namespace App\Support;

class AssistantCustomerText
{
    /**
     * Remove tool machine markers the model sometimes echoes as the reply.
     */
    public static function stripMachineMarkers(string $text): string
    {
        $text = preg_replace('/FLOW_COMMITTED:\s*\{[^{}]*\}/u', '', $text) ?? $text;
        $lines = preg_split('/\R/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line)
        {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, 'FLOW_COMMITTED:'))
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

        return $out;
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
