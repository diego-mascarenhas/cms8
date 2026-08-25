<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Natural add-to-cart confirmations that omit the product name ("Agregame 2", "poneme 3").
 */
class WhatsAppNaturalCartPhrase
{
    /**
     * @var array<string, int>
     */
    private const QUANTITY_WORDS = [
        'un' => 1,
        'una' => 1,
        'uno' => 1,
        'dos' => 2,
        'tres' => 3,
        'cuatro' => 4,
        'cinco' => 5,
        'seis' => 6,
        'siete' => 7,
        'ocho' => 8,
        'nueve' => 9,
        'diez' => 10,
        'once' => 11,
        'doce' => 12,
        'trece' => 13,
        'catorce' => 14,
        'quince' => 15,
        'veinte' => 20,
    ];

    /**
     * View-cart intent: the customer wants to see what is already in the cart.
     * Matches natural wording, not only the *carrito* command.
     */
    public static function isViewCart(string $message): bool
    {
        $ascii = self::normalizedAscii($message);
        if ($ascii === '')
        {
            return false;
        }

        if (preg_match('/\b(agregar|anadir|agrega|agregame|quitar|eliminar|sacar|vaciar|limpiar|borrar|sumar|poner|poneme)\b/u', $ascii) === 1)
        {
            return false;
        }

        $mentionsCart = preg_match('/\b(carrito|cart)\b/u', $ascii) === 1;
        if (! $mentionsCart)
        {
            return false;
        }

        if (in_array($ascii, ['carrito', 'cart', 'mi carrito', 'el carrito'], true))
        {
            return true;
        }

        return preg_match('/\b(ver|veo|viste|mostrar|mostrame|mostraros|dame|pasa|pasame|quiero|mira|mirar|listar|revisar|chequear|consultar|decir|decime|tiene|tienen|hay|tengo)\b/u', $ascii) === 1;
    }

    /**
     * @return array{quantity: int}|null
     */
    public static function quantityOnlyAdd(string $message): ?array
    {
        $parsed = self::addToCartCommand($message);
        if ($parsed !== null && $parsed['needle'] === '')
        {
            return ['quantity' => $parsed['quantity']];
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
        return self::addToCartCommand($message);
    }

    /**
     * Comprar / agregar / agregame, with digits or Spanish quantity words.
     *
     * @return array{quantity: int, needle: string}|null
     */
    public static function addToCartCommand(string $message): ?array
    {
        $ascii = self::normalizedAscii($message);
        if ($ascii === '')
        {
            return null;
        }

        $quantityWords = implode('|', array_keys(self::QUANTITY_WORDS));

        if (preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)$/u', $ascii, $match) === 1)
        {
            $rest = trim($match[2]);
            if ($rest === '' || $rest === 'todo')
            {
                return null;
            }

            return self::parseQuantityAndNeedle($rest);
        }

        if (preg_match('/^(agrega(?:me|lo|la|mel[oa]s?)?|anadi(?:me|lo|la)?|pone(?:me|lo|la)?|manda(?:me|lo|la)?|sumame|agregar|anadir)(?:\s+(.+))?$/u', $ascii, $match) === 1)
        {
            $rest = trim((string) ($match[2] ?? ''));
            if ($rest === '')
            {
                return ['quantity' => 1, 'needle' => ''];
            }

            if (preg_match('/^cita|turno|reunion|visita/u', $rest) === 1)
            {
                return null;
            }

            return self::parseQuantityAndNeedle($rest);
        }

        if (preg_match('/^(si|dale|ok|okay|quiero|dame)\s+(\d+|'.$quantityWords.')(?:\s+unidades?)?$/u', $ascii, $match) === 1)
        {
            $qty = self::parseQuantityToken($match[2]);
            if ($qty !== null)
            {
                return ['quantity' => $qty, 'needle' => ''];
            }
        }

        return null;
    }

    /**
     * @return array{quantity: int, needle: string}
     */
    private static function parseQuantityAndNeedle(string $rest): array
    {
        $rest = self::stripPriceAndUnits($rest);
        $quantityWords = implode('|', array_keys(self::QUANTITY_WORDS));

        if (preg_match('/^(\d+|'.$quantityWords.')\s+(?:de\s+)?(?:estas?|estos|este|esta|eso|esa|lo mismo|la misma)\s*(?:unidades?)?$/u', $rest, $qtyMatch) === 1)
        {
            return ['quantity' => self::parseQuantityToken($qtyMatch[1]) ?? 1, 'needle' => ''];
        }

        if (preg_match('/^(?:estas?|estos|este|esta|eso|esa)\s*(?:unidades?)?$/u', $rest) === 1)
        {
            return ['quantity' => 1, 'needle' => ''];
        }

        if (preg_match('/^(\d+|'.$quantityWords.')\s+unidades?$/u', $rest, $qtyMatch) === 1)
        {
            return ['quantity' => self::parseQuantityToken($qtyMatch[1]) ?? 1, 'needle' => ''];
        }

        if (preg_match('/^(\d+|'.$quantityWords.')$/u', $rest, $qtyMatch) === 1)
        {
            $qty = self::parseQuantityToken($qtyMatch[1]);
            if ($qty !== null && ($qty <= 50 || ! preg_match('/^\d+$/', $qtyMatch[1])))
            {
                return ['quantity' => $qty, 'needle' => ''];
            }
        }

        if (preg_match('/^(\d+|'.$quantityWords.')\s+(?:unidades?\s+)?(?:de\s+)?(.+)$/u', $rest, $qtyMatch) === 1)
        {
            $needle = self::sanitizeProductNeedle($qtyMatch[2]);
            if ($needle !== '')
            {
                return ['quantity' => self::parseQuantityToken($qtyMatch[1]) ?? 1, 'needle' => $needle];
            }
        }

        return ['quantity' => 1, 'needle' => self::sanitizeProductNeedle($rest)];
    }

    public static function sanitizeProductNeedle(string $raw): string
    {
        $t = self::stripPriceAndUnits(self::normalizedAscii($raw));
        $t = preg_replace('/\s+iguales?\s+al\s+carrito\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/\s+al\s+carrito\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/\s+iguales?\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:el|la|los|las|un|una)\s+/u', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:producto|codigo|code|sku|id|articulo|item)\s+/u', '', $t) ?? $t;

        return trim($t);
    }

    private static function stripPriceAndUnits(string $raw): string
    {
        $t = trim($raw);
        $t = preg_replace('/\s+a\s+\$?\s*\d+(?:[.,]\d+)?\s*(?:c\/u|cada uno|cada una)?.*$/u', '', $t) ?? $t;
        $t = preg_replace('/\s+\$\s*\d+(?:[.,]\d+)?.*$/u', '', $t) ?? $t;

        return trim($t);
    }

    private static function parseQuantityToken(string $token): ?int
    {
        $token = trim($token);
        if (preg_match('/^\d+$/', $token) === 1)
        {
            return self::clampQuantity((int) $token);
        }

        return isset(self::QUANTITY_WORDS[$token])
            ? self::clampQuantity(self::QUANTITY_WORDS[$token])
            : null;
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
