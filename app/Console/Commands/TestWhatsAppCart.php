<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Console\Command;

class TestWhatsAppCart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:whatsapp-cart {phone=5491112345678} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WhatsApp cart functionality locally without webhooks';

    protected $whatsAppMessageService;

    public function __construct(WhatsAppMessageService $whatsAppMessageService)
    {
        parent::__construct();
        $this->whatsAppMessageService = $whatsAppMessageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->option('message');

        $this->info("🧪 Testing WhatsApp Cart for phone: {$phone}");
        $this->newLine();

        if ($message)
        {
            // Test single message
            $this->testSingleMessage($phone, $message);
        } else
        {
            // Interactive testing
            $this->interactiveTest($phone);
        }
    }

    private function testSingleMessage($phone, $message)
    {
        $this->info("📱 Simulating message: '{$message}'");
        $this->newLine();

        // Capture WhatsApp response instead of sending
        $this->captureWhatsAppResponse($phone, $message);
    }

    private function interactiveTest($phone)
    {
        $this->info('🎮 Interactive WhatsApp Cart Testing Mode');
        $this->newLine();

        $this->info('Available commands:');
        $this->line('📋 productos - View catalog');
        $this->line('🛒 comprar [product] - Add to cart');
        $this->line('👁️ carrito - View cart');
        $this->line('🗑️ vaciar carrito - Clear cart');
        $this->line('➖ quitar [n] [producto] / quitar todo [producto] - Sacar del carrito');
        $this->line('💳 finalizar - Cerrar pedido');
        $this->line('❌ exit - Exit testing');
        $this->newLine();

        while (true)
        {
            $message = $this->ask("💬 Enter WhatsApp message (or 'exit' to quit)");

            if (strtolower($message) === 'exit')
            {
                $this->info('👋 Exiting WhatsApp Cart testing');
                break;
            }

            $this->newLine();
            $this->info("📱 Processing: '{$message}'");
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            // Capture WhatsApp response
            $this->captureWhatsAppResponse($phone, $message);
        }
    }

    private function captureWhatsAppResponse($phone, $message)
    {
        try
        {
            // Test cart commands first (higher priority)
            if ($this->isCartCommand($message))
            {
                $response = $this->simulateCartResponse($phone, $message);
                if ($response)
                {
                    $this->displayWhatsAppResponse($response);

                    return;
                }
            }

            // Test product commands
            if ($this->isProductCommand($message))
            {
                $response = $this->simulateProductResponse($phone, $message);
                if ($response)
                {
                    $this->displayWhatsAppResponse($response);

                    return;
                }
            }

            $this->warn('⚠️ Command not recognized');
            $this->newLine();
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());
            $this->newLine();
        }
    }

    private function isProductCommand($message)
    {
        $normalizedMessage = strtolower(trim($message));

        // Exact product commands only (avoid conflicts with cart commands)
        return in_array($normalizedMessage, ['productos', 'servicios', 'catalogo', 'precios']) ||
               preg_match('/^productos?$/i', $message) ||
               preg_match('/^servicios?$/i', $message) ||
               preg_match('/^catalogo$/i', $message);
    }

    private function isCartCommand($message)
    {
        $normalizedMessage = strtolower(trim($message));

        return preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)/i', $normalizedMessage) ||
               in_array($normalizedMessage, ['carrito', 'ver carrito', 'mi carrito', 'cart']) ||
               in_array($normalizedMessage, ['vaciar carrito', 'limpiar carrito', 'borrar carrito', 'clear cart']) ||
               in_array($normalizedMessage, ['checkout', 'finalizar', 'finalizar compra', 'pagar', 'comprar todo']) ||
               in_array($normalizedMessage, ['si', 'sí', 'yes', 'confirmar', 'aceptar', 'proceder']) ||
               in_array($normalizedMessage, ['no', 'nah', 'seguir comprando', 'continuar', 'agregar mas', 'cancelar']);
    }

    private function simulateProductResponse($phone, $message)
    {
        $teamId = 1; // Default team for testing

        // Get products like the real service does
        $products = \App\Models\Product::where('team_id', $teamId)
            ->where('status', true)
            ->where('whatsapp_enabled', true)
            ->with(['category', 'currency'])
            ->orderBy('category_id')
            ->orderBy('price')
            ->get();

        if ($products->isEmpty())
        {
            return "📦 *Catálogo de Productos*\n\nActualmente no hay productos disponibles.\n\n📞 Contacta a soporte: https://revisionalpha.com/contactenos";
        }

        $response = "🛍️ *Catálogo de Productos y Servicios*\n\n";

        // Group products by category
        $productsByCategory = $products->groupBy('category.name');

        foreach ($productsByCategory as $categoryName => $categoryProducts)
        {
            $response .= "📂 *{$categoryName}*\n";

            foreach ($categoryProducts as $product)
            {
                $currency = $product->currency ? $product->currency->symbol : '$';
                $response .= "• *{$product->name}*\n";
                $response .= "  💰 {$currency}".number_format($product->currentSellingPrice(), 2)."\n";
                $response .= '  📝 '.\Illuminate\Support\Str::limit($product->description, 80)."\n\n";
            }
        }

        $response .= "💡 *Para contratar:*\n";
        $response .= "• Escribe: *comprar [nombre del producto]*\n";
        $response .= "• O contacta soporte: https://revisionalpha.com/contactenos\n\n";
        $response .= '🛒 *Tu carrito:* Escribe *carrito* para ver tus productos seleccionados';

        return $response;
    }

    private function simulateCartResponse($phone, $message)
    {
        $normalizedMessage = strtolower(trim($message));
        $teamId = 1;

        // Set cart session
        \Darryldecode\Cart\Facades\CartFacade::session($phone);

        // Handle add to cart
        if (preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)/i', $normalizedMessage, $matches))
        {
            $productName = trim($matches[2]);

            return $this->simulateAddToCart($phone, $productName, $teamId);
        }

        // Handle view cart
        if (in_array($normalizedMessage, ['carrito', 'ver carrito', 'mi carrito', 'cart']))
        {
            return $this->simulateViewCart($phone);
        }

        // Handle clear cart
        if (in_array($normalizedMessage, ['vaciar carrito', 'limpiar carrito', 'borrar carrito', 'clear cart']))
        {
            \Darryldecode\Cart\Facades\CartFacade::clear();

            return "🗑️ **Carrito vaciado exitosamente**\n\n📋 Escribe 'productos' para ver nuestro catálogo\n💡 Usa 'comprar [producto]' para agregar nuevos items";
        }

        // Handle checkout
        if (in_array($normalizedMessage, ['checkout', 'finalizar', 'finalizar compra', 'pagar', 'comprar todo']))
        {
            return $this->simulateCheckout($phone);
        }

        // Handle checkout confirmation (YES responses)
        if (in_array($normalizedMessage, ['si', 'sí', 'yes', 'confirmar', 'aceptar', 'proceder']))
        {
            return $this->simulateConfirmCheckout($phone);
        }

        // Handle continue shopping from checkout (NO responses)
        if (in_array($normalizedMessage, ['no', 'nah', 'seguir comprando', 'continuar', 'agregar mas', 'cancelar']))
        {
            return $this->simulateContinueShopping($phone);
        }

        return null;
    }

    private function simulateAddToCart($phone, $productName, $teamId)
    {
        $product = \App\Models\Product::where('team_id', $teamId)
            ->where('name', 'LIKE', "%{$productName}%")
            ->where('status', true)
            ->where('whatsapp_enabled', true)
            ->first();

        if (! $product)
        {
            return "❌ **Producto no encontrado**: '{$productName}'\n\n📋 Escribe 'productos' para ver nuestro catálogo completo\n💡 **Tip**: Usa el nombre exacto del producto";
        }

        $cartItems = \Darryldecode\Cart\Facades\CartFacade::getContent();
        $existingItem = $cartItems->where('id', $product->id)->first();

        if ($existingItem)
        {
            \Darryldecode\Cart\Facades\CartFacade::update($product->id, [
                'quantity' => [
                    'relative' => false,
                    'value' => $existingItem->quantity + 1,
                ],
            ]);
            $quantity = $existingItem->quantity + 1;
        } else
        {
            \Darryldecode\Cart\Facades\CartFacade::add([
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->currentSellingPrice(),
                'quantity' => 1,
                'attributes' => [
                    'team_id' => $teamId,
                    'currency_id' => $product->currency_id,
                    'description' => $product->description,
                    'category_name' => $product->category->name ?? '',
                ],
            ]);
            $quantity = 1;
        }

        $currency = $product->currency ? $product->currency->symbol : '$';

        $response = "✅ **{$product->name}** agregado al carrito!\n\n";
        $response .= "💰 **Precio**: {$currency}".number_format($product->currentSellingPrice(), 2)."\n";
        $response .= "📦 **Cantidad**: {$quantity}\n";
        $response .= '🏷️ **Categoría**: '.($product->category->name ?? 'General')."\n\n";
        $response .= "🛒 **Total del carrito**: {$currency}".number_format(\Darryldecode\Cart\Facades\CartFacade::getTotal(), 2)."\n\n";
        $response .= "**Opciones:**\n";
        $response .= "• Escribe 'carrito' para ver todos tus productos\n";
        $response .= "• Escribe 'comprar [producto]' para agregar más\n";
        $response .= "• Escribe 'checkout' para finalizar tu compra";

        return $response;
    }

    private function simulateViewCart($phone)
    {
        $cartItems = \Darryldecode\Cart\Facades\CartFacade::getContent();

        if ($cartItems->isEmpty())
        {
            return "🛒 **Tu carrito está vacío**\n\n📋 Escribe 'productos' para ver nuestro catálogo\n💡 **Tip**: Usa 'comprar [producto]' para agregar items";
        }

        $response = "🛒 **Tu Carrito de Compras**\n\n";

        foreach ($cartItems as $item)
        {
            $response .= "• **{$item->name}**\n";
            $response .= '  💰 $'.number_format($item->price, 2)." x {$item->quantity}\n";
            $response .= '  💵 Subtotal: $'.number_format($item->price * $item->quantity, 2)."\n";

            if (! empty($item->attributes->category_name))
            {
                $response .= "  🏷️ {$item->attributes->category_name}\n";
            }
            $response .= "\n";
        }

        $response .= '💰 **TOTAL: $'.number_format(\Darryldecode\Cart\Facades\CartFacade::getTotal(), 2)."**\n";
        $response .= '📦 **Items**: '.\Darryldecode\Cart\Facades\CartFacade::getTotalQuantity()."\n\n";

        $response .= "**Opciones:**\n";
        $response .= "• Escribe 'checkout' para finalizar tu compra\n";
        $response .= "• Escribe 'comprar [producto]' para agregar más\n";
        $response .= "• Escribe 'vaciar carrito' para empezar de nuevo";

        return $response;
    }

    private function simulateCheckout($phone)
    {
        $cartItems = \Darryldecode\Cart\Facades\CartFacade::getContent();

        if ($cartItems->isEmpty())
        {
            return "❌ **Tu carrito está vacío**\n\n📋 Escribe 'productos' para ver nuestro catálogo";
        }

        $total = \Darryldecode\Cart\Facades\CartFacade::getTotal();

        $response = "🛒 **Resumen de tu compra**\n\n";

        foreach ($cartItems as $item)
        {
            $response .= "• {$item->name} x{$item->quantity} - $".number_format($item->price * $item->quantity, 2)."\n";
        }

        $response .= "\n💰 **TOTAL: $".number_format($total, 2)."**\n";
        $response .= '📦 **Items**: '.$cartItems->sum('quantity')."\n\n";
        $response .= "❓ **¿Quieres confirmar tu compra?**\n\n";
        $response .= 'Responde *SÍ* para proceder o *NO* para seguir comprando.';

        return $response;
    }

    private function simulateConfirmCheckout($phone)
    {
        $cartItems = \Darryldecode\Cart\Facades\CartFacade::getContent();

        if ($cartItems->isEmpty())
        {
            return "❌ **Tu carrito está vacío**\n\n📋 Escribe 'productos' para ver nuestro catálogo";
        }

        $total = \Darryldecode\Cart\Facades\CartFacade::getTotal();

        $response = "✅ **¡Compra Confirmada!**\n\n";
        $response .= "📋 **Resumen del pedido:**\n";

        foreach ($cartItems as $item)
        {
            $response .= "• {$item->name} x{$item->quantity} - $".number_format($item->price * $item->quantity, 2)."\n";
        }

        $response .= "\n💰 **TOTAL: $".number_format($total, 2)."**\n";
        $response .= '📦 **Items**: '.$cartItems->sum('quantity')."\n\n";

        $response .= "📧 **Próximos pasos:**\n";
        $response .= "• Te enviaremos un email con los detalles completos\n";
        $response .= "• Incluirá enlaces de pago seguros y opciones de entrega\n";
        $response .= '• Número de orden: #'.strtoupper(substr(md5($phone.time()), 0, 8))."\n\n";

        $response .= "💳 **El proceso continúa por email:**\n";
        $response .= "• Enlaces de pago seguros\n";
        $response .= "• Instrucciones detalladas\n";
        $response .= "• Confirmación de entrega\n\n";

        $response .= "📞 **¿Dudas? Contáctanos:**\n";
        $response .= "• WhatsApp: Responde aquí directamente\n";
        $response .= "• Web: https://revisionalpha.com/contactenos\n\n";

        $response .= "¡Gracias por confiar en nosotros! 🎉\n";
        $response .= '📬 Revisa tu email en los próximos minutos.';

        // Clear the cart after successful checkout
        \Darryldecode\Cart\Facades\CartFacade::clear();

        return $response;
    }

    private function simulateContinueShopping($phone)
    {
        $response = "🛍️ **¡Perfecto!**\n\n";
        $response .= "Puedes seguir agregando productos a tu carrito.\n\n";
        $response .= "📋 **Opciones disponibles:**\n";
        $response .= "• Escribe '*productos*' para ver el catálogo completo\n";
        $response .= "• Usa '*comprar [producto]*' para agregar items\n";
        $response .= "• Escribe '*carrito*' para ver tu carrito actual\n";
        $response .= "• Usa '*checkout*' cuando estés listo para finalizar\n\n";
        $response .= '💡 **Tip:** Tu carrito actual se mantiene guardado';

        return $response;
    }

    private function displayWhatsAppResponse($response)
    {
        $this->info('✅ WhatsApp Response:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line($response);
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
    }
}
