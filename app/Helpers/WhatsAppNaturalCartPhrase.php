<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Natural add-to-cart confirmations that omit the product name ("Agregame 2", "poneme 3").
 */
class WhatsAppNaturalCartPhrase
{
    /**
     * @return array{quantity: int}|null
     */
    public static function quantityOnlyAdd(string $message): ?array
    {
        $ascii = self::normalizedAscii($message);

        if ($ascii === '')
        {
            return null;
        }

        if (preg_match('/^(agrega(?:me|lo|la|mel[oa]s?)?|anadi(?:me|lo|la)?|pone(?:me|lo|la)?|manda(?:me|lo|la)?|sumame)(?:\s+(\d+))?(?:\s+unidades?)?$/u', $ascii, $match) === 1)
        {
            $qty = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 1;

            return ['quantity' => max(1, min(500, $qty))];
        }

        if (preg_match('/^(si|dale|ok|okay|quiero|dame)\s+(\d+)(?:\s+unidades?)?$/u', $ascii, $match) === 1)
        {
            return ['quantity' => max(1, min(500, (int) $match[2]))];
        }

        if (preg_match('/^(agregar|anadir)\s+(\d+)(?:\s+unidades?)?$/u', $ascii, $match) === 1)
        {
            return ['quantity' => max(1, min(500, (int) $match[2]))];
        }

        return null;
    }

    /**
     * Keyword buy lines: "comprar abrazadera 16 x 27", "comprar producto 21861", "comprar 2 de estas".
     *
     * @return array{quantity: int, needle: string}|null Empty needle means the last offered product.
     */
    public static function buyCommand(string $message): ?array
    {
        $ascii = self::normalizedAscii($message);
        if ($ascii === '' || preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)$/u', $ascii, $match) !== 1)
        {
            return null;
        }

        $rest = trim($match[2]);
        if ($rest === '' || $rest === 'todo')
        {
            return null;
        }

        if (preg_match('/^(\d+)\s+(?:de\s+)?(?:estas?|estos|este|esta|eso|esa|lo mismo|la misma)\s*(?:unidades?)?$/u', $rest, $qtyMatch) === 1)
        {
            return ['quantity' => self::clampQuantity((int) $qtyMatch[1]), 'needle' => ''];
        }

        if (preg_match('/^(?:estas?|estos|este|esta|eso|esa)\s*(?:unidades?)?$/u', $rest) === 1)
        {
            return ['quantity' => 1, 'needle' => ''];
        }

        if (preg_match('/^(\d+)\s+unidades?$/u', $rest, $qtyMatch) === 1)
        {
            return ['quantity' => self::clampQuantity((int) $qtyMatch[1]), 'needle' => ''];
        }

        if (preg_match('/^(\d+)\s+(?:unidades?\s+)?(?:de\s+)?(.+)$/u', $rest, $qtyMatch) === 1)
        {
            $needle = self::sanitizeProductNeedle($qtyMatch[2]);
            if ($needle !== '')
            {
                return ['quantity' => self::clampQuantity((int) $qtyMatch[1]), 'needle' => $needle];
            }
        }

        return ['quantity' => 1, 'needle' => self::sanitizeProductNeedle($rest)];
    }

    public static function sanitizeProductNeedle(string $raw): string
    {
        $t = self::normalizedAscii($raw);
        $t = preg_replace('/\s+al\s+carrito\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:el|la|los|las|un|una)\s+/u', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:producto|codigo|code|sku|id|articulo|item)\s+/u', '', $t) ?? $t;

        return trim($t);
    }

    private static function normalizedAscii(string $message): string
    {
        $ascii = mb_strtolower(trim(Str::ascii($message, 'es')), 'UTF-8');
        $ascii = preg_replace('/\s+/u', ' ', $ascii) ?? $ascii;

        return trim($ascii, " \t\n\r\0\x0B!?.¡¿,");
    }

    private static function clampQuantity(int $quantity): int
    {
        return max(1, min(500, $quantity));
    }
}
