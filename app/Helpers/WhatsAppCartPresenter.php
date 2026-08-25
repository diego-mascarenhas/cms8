<?php

namespace App\Helpers;

use Darryldecode\Cart\Facades\CartFacade as Cart;

/**
 * Customer-facing WhatsApp cart text (keyword command and assistant intercept).
 */
class WhatsAppCartPresenter
{
    public static function customerMessage(string $sessionKey): string
    {
        if ($sessionKey === '')
        {
            return 'No pude abrir el carrito. Escribí de nuevo *ver carrito*.';
        }

        Cart::session($sessionKey);
        $cartItems = Cart::getContent();

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

        $response .= '💰 **TOTAL: $'.number_format((float) Cart::getTotal(), 2)."**\n";
        $response .= '📦 **Items**: '.Cart::getTotalQuantity()."\n\n";
        $response .= "**Siguiente paso:**\n";
        $response .= "• *finalizar* — total y confirmación con *SÍ*\n";
        $response .= "• *Comprar* más el producto o *agregar* cantidad y producto — sumar ítems\n";
        $response .= "• *Quitar* cantidad y producto o *quitar todo* el nombre — sacar unidades o el ítem\n";
        $response .= '• *vaciar carrito* — empezar de cero';

        return $response;
    }
}
