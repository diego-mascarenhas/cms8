<?php

namespace App\Helpers;

use App\Services\ShoppingCartService;

/**
 * Customer-facing WhatsApp cart text (keyword command and assistant intercept).
 */
class WhatsAppCartPresenter
{
    public static function customerMessage(int $teamId, string $phone): string
    {
        $sessionKey = WhatsAppCartSessionKey::fromPhone($phone);
        if ($teamId < 1 || $sessionKey === '')
        {
            return 'No pude abrir el carrito. Escribí de nuevo *ver carrito*.';
        }

        $carts = app(ShoppingCartService::class);
        $cartItems = $carts->whatsAppLines($teamId, $phone);

        if ($cartItems->isEmpty())
        {
            return "🛒 **Tu carrito está vacío**\n\n"
                .'Todavía no hay productos sumados en este chat. '
                ."Decime el nombre o el código (por ejemplo *agregame 2 ABRAZADERA 8 X 16*) y lo cargo acá.\n\n"
                .'📋 *comprar* o *agregar* cantidad y nombre | *productos* catálogo.';
        }

        $response = "🛒 **Tu Carrito de Compras**\n\n";

        foreach ($cartItems as $item)
        {
            $response .= "• **{$item->name}**\n";
            $response .= '  💰 $'.number_format((float) $item->price, 2)." x {$item->quantity}\n";
            $response .= '  💵 Subtotal: $'.number_format((float) $item->price * (int) $item->quantity, 2)."\n";

            $categoryName = $item->attributes->category_name ?? '';
            if ($categoryName !== '')
            {
                $response .= "  🏷️ {$categoryName}\n";
            }
            $response .= "\n";
        }

        $total = (float) $cartItems->sum(fn (object $item): float => (float) $item->price * (int) $item->quantity);
        $quantity = (int) $cartItems->sum(fn (object $item): int => (int) $item->quantity);

        $response .= '💰 **TOTAL: $'.number_format($total, 2)."**\n";
        $response .= '📦 **Items**: '.$quantity."\n\n";
        $response .= "**Siguiente paso:**\n";
        $response .= "• *finalizar* — total y confirmación con *SÍ*\n";
        $response .= "• *Comprar* más el producto o *agregar* cantidad y producto — sumar ítems\n";
        $response .= "• *Quitar* cantidad y producto o *quitar todo* el nombre — sacar unidades o el ítem\n";
        $response .= '• *vaciar carrito* — empezar de cero';

        return $response;
    }
}
