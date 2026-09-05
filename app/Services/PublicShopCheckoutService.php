<?php

namespace App\Services;

use App\Enums\ProductCatalogStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PublicShopCheckoutService
{
    public function __construct(
        protected ShoppingCartService $carts,
        protected WhatsAppCheckoutOrderService $orders,
    ) {}

    /**
     * @param  list<array{code: string, qty: int, detail?: string|null, name?: string|null}>  $items
     */
    public function syncCart(Team $team, string $guestId, array $items): ShoppingCart
    {
        $cart = $this->carts->forPublicShop((int) $team->id, $guestId);
        $this->replaceCartItems($cart, $team, $items);

        return $cart;
    }

    /**
     * @param  list<array{code: string, qty: int, detail?: string|null, name?: string|null}>  $items
     * @return array{
     *     order: Order,
     *     whatsapp_url: string|null,
     *     whatsapp_text: string,
     * }
     */
    public function checkout(
        Team $team,
        string $guestId,
        array $items,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?string $notes = null,
        ?int $storeId = null,
        ?string $fulfillmentType = null,
        ?string $paymentMethod = null,
        ?string $couponCode = null,
        ?string $deliveryAddress = null,
    ): array {
        $cart = $this->syncCart($team, $guestId, $items);
        $lines = $this->carts->linesOrEmpty($cart);

        if ($lines->isEmpty())
        {
            throw ValidationException::withMessages([
                'items' => [__('No se pudo armar el pedido. Revisá los productos del carrito.')],
            ]);
        }

        $store = $this->resolveCheckoutStore($team, $lines, $storeId);
        $snapshot = $this->orders->buildCheckoutSnapshot($store);
        $allowedFulfillment = $store?->enabledCheckoutFulfillmentTypes() ?? Store::checkoutFulfillmentKeys();
        $allowedPayments = $store?->enabledCheckoutPaymentMethods() ?? Store::checkoutPaymentMethodKeys();

        $fulfillment = is_string($fulfillmentType) ? trim($fulfillmentType) : '';
        if ($fulfillment !== '' && in_array($fulfillment, $allowedFulfillment, true))
        {
            $snapshot['chosen_fulfillment'] = $fulfillment;
            $snapshot['chosen_fulfillment_label'] = Store::checkoutFulfillmentLabels()[$fulfillment] ?? $fulfillment;
        }

        $payment = is_string($paymentMethod) ? trim($paymentMethod) : '';
        if ($payment !== '' && in_array($payment, $allowedPayments, true))
        {
            $snapshot['chosen_payment'] = $payment;
            $snapshot['chosen_payment_label'] = Store::checkoutPaymentMethodLabels()[$payment] ?? $payment;
        }

        $address = is_string($deliveryAddress) ? trim($deliveryAddress) : '';
        if ($address !== '' && ($snapshot['chosen_fulfillment'] ?? null) === Store::CHECKOUT_FULFILLMENT_DELIVERY)
        {
            $snapshot['delivery_address'] = $address;
        }

        $coupon = is_string($couponCode) ? trim($couponCode) : '';
        $coupon = $coupon !== '' ? mb_strtoupper($coupon) : null;

        try
        {
            $order = $this->orders->createFromPublicShopCart(
                teamId: (int) $team->id,
                cartItems: $lines,
                cartTotal: (float) $this->carts->total($cart),
                phoneDigits: $customerPhone,
                customerName: $customerName,
                storeId: $store?->id,
                checkoutSnapshot: $snapshot,
                customerNotes: $notes,
                couponCode: $coupon,
                deliveryAddress: $address !== '' ? $address : null,
            );
        } catch (InvalidArgumentException $e)
        {
            throw ValidationException::withMessages([
                'items' => [$e->getMessage()],
            ]);
        }

        $this->carts->clear($cart);

        $waText = $this->whatsAppMessage(
            $team,
            $order,
            $lines,
            $customerName,
            $notes,
            $coupon,
            $snapshot,
        );
        $digits = $this->resolveWhatsAppDigits($team, $store);
        $waUrl = $digits
            ? 'https://wa.me/'.$digits.'?text='.rawurlencode($waText)
            : null;

        return [
            'order' => $order,
            'whatsapp_url' => $waUrl,
            'whatsapp_text' => $waText,
        ];
    }

    /**
     * @param  Collection<int, object>  $lines
     */
    private function resolveCheckoutStore(Team $team, Collection $lines, ?int $storeId): ?Store
    {
        if ($storeId !== null && $storeId > 0)
        {
            $requested = Store::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->where('status', true)
                ->where('id', $storeId)
                ->first();

            if ($requested)
            {
                return $requested;
            }
        }

        return $this->orders->resolveStoreForCart((int) $team->id, $lines);
    }

    /**
     * @param  list<array{code: string, qty: int, detail?: string|null, name?: string|null}>  $items
     */
    private function replaceCartItems(ShoppingCart $cart, Team $team, array $items): void
    {
        $this->carts->clear($cart);

        /** @var array<string, array{product: Product, qty: int, detail: string|null}> $merged */
        $merged = [];

        foreach ($items as $item)
        {
            $code = mb_strtolower(trim((string) ($item['code'] ?? '')));
            if ($code === '')
            {
                continue;
            }

            $product = Product::withoutGlobalScope('team')
                ->with(['category', 'currency'])
                ->where('team_id', $team->id)
                ->where('catalog_status', ProductCatalogStatus::Publish)
                ->whereRaw('LOWER(code) = ?', [$code])
                ->first();

            if (! $product)
            {
                continue;
            }

            $detail = isset($item['detail']) ? trim((string) $item['detail']) : '';
            $key = $product->id.'|'.$detail;
            $qty = max(1, min(500, (int) ($item['qty'] ?? 1)));

            if (isset($merged[$key]))
            {
                $merged[$key]['qty'] = min(500, $merged[$key]['qty'] + $qty);
            } else
            {
                $merged[$key] = [
                    'product' => $product,
                    'qty' => $qty,
                    'detail' => $detail !== '' ? $detail : null,
                ];
            }
        }

        foreach ($merged as $row)
        {
            $cartItem = $this->carts->addProduct($cart, $row['product'], $row['qty']);
            if ($row['detail'])
            {
                $cartItem->option_label = $row['detail'];
                $cartItem->save();
            }
        }
    }

    /**
     * @param  Collection<int, object>  $lines
     * @param  array<string, mixed>  $snapshot
     */
    private function whatsAppMessage(
        Team $team,
        Order $order,
        Collection $lines,
        ?string $customerName,
        ?string $notes,
        ?string $couponCode,
        array $snapshot,
    ): string {
        $shopName = (string) (data_get($team->getSetting('business_config'), 'business_name')
            ?: $team->name
            ?: 'la tienda');

        $body = [
            'Hola, confirmo mi pedido en '.$shopName.'.',
            'Pedido: '.$order->order_number,
            '',
        ];

        foreach ($lines as $line)
        {
            $label = trim((string) (data_get($line->attributes, 'option_label') ?? ''));
            $name = (string) $line->name.($label !== '' ? ' — '.$label : '');
            $price = (float) $line->price;
            $qty = (int) $line->quantity;
            $priceBit = $price > 0 ? ' ('.number_format($price, 2, ',', '.').')' : '';
            $body[] = '• '.$qty.' x '.$name.$priceBit;
        }

        $body[] = '';
        $body[] = 'Total: '.number_format((float) $order->total_amount, 2, ',', '.');

        if ($customerName)
        {
            $body[] = 'Nombre: '.$customerName;
        }

        $storeName = trim((string) ($snapshot['store_name'] ?? ''));
        if ($storeName !== '')
        {
            $body[] = 'Sucursal: '.$storeName;
        }

        $fulfillmentLabel = trim((string) ($snapshot['chosen_fulfillment_label'] ?? ''));
        if ($fulfillmentLabel !== '')
        {
            $body[] = 'Entrega: '.$fulfillmentLabel;
        }

        $deliveryAddress = trim((string) ($snapshot['delivery_address'] ?? ''));
        if ($deliveryAddress !== '')
        {
            $body[] = 'Dirección: '.$deliveryAddress;
        }

        $paymentLabel = trim((string) ($snapshot['chosen_payment_label'] ?? ''));
        if ($paymentLabel !== '')
        {
            $body[] = 'Pago: '.$paymentLabel;
        }

        if ($couponCode)
        {
            $body[] = 'Cupón: '.$couponCode;
        }

        if ($notes)
        {
            $body[] = 'Notas: '.$notes;
        }

        return implode("\n", $body);
    }

    private function resolveWhatsAppDigits(Team $team, ?Store $store): ?string
    {
        $digits = $team->catalogCheckoutWhatsAppDigits();
        if ($digits)
        {
            return $digits;
        }

        $raw = trim((string) data_get($store?->data, 'whatsapp', ''));
        if ($raw === '')
        {
            $main = Store::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->where('status', true)
                ->orderByDesc('is_main')
                ->orderBy('id')
                ->first();
            $raw = trim((string) data_get($main?->data, 'whatsapp', ''));
        }

        $clean = preg_replace('/\D+/', '', $raw) ?: '';

        return $clean !== '' ? $clean : null;
    }
}
