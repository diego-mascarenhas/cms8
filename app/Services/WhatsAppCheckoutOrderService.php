<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WhatsAppCheckoutOrderService
{
    /**
     * Persist WhatsApp cart lines as a row in {@see Order} (line snapshot in metadata).
     *
     * @param  iterable<object>  $cartItems  Cart rows with id, name, price, quantity, attributes
     * @param  array<string, mixed>|null  $checkoutSnapshot  Branch checkout options shown to the customer (labels, store name)
     */
    public function createFromWhatsAppCart(int $teamId, string $cleanPhoneDigits, iterable $cartItems, float $cartTotal, ?int $storeId = null, ?array $checkoutSnapshot = null): Order
    {
        $lines = [];
        foreach ($cartItems as $item)
        {
            $lines[] = $this->lineFromCartItem($teamId, $item);
        }

        if ($lines === [])
        {
            throw new InvalidArgumentException('Cannot create order from an empty cart.');
        }

        $currencyId = $this->resolveCurrencyId($lines);

        return Order::query()->getModel()->getConnection()->transaction(function () use ($teamId, $cleanPhoneDigits, $lines, $cartTotal, $currencyId, $storeId, $checkoutSnapshot)
        {
            $orderNumber = $this->generateUniqueOrderNumber();

            $metadata = [
                'source' => 'whatsapp',
                'phone' => $cleanPhoneDigits,
                'items' => $lines,
            ];
            if ($checkoutSnapshot !== null && $checkoutSnapshot !== [])
            {
                $metadata['checkout_offered'] = $checkoutSnapshot;
            }

            return Order::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'store_id' => $storeId,
                'order_number' => $orderNumber,
                'contact_id' => $this->findContactIdForTeamPhone($teamId, $cleanPhoneDigits),
                'total_amount' => round($cartTotal, 2),
                'currency_id' => $currencyId,
                'payment_status' => 'pending',
                'delivery_status' => 'processing',
                'notes' => 'Pedido realizado por WhatsApp',
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Persist the current WhatsApp cart as an order and empty the cart.
     *
     * @throws InvalidArgumentException when the cart is empty
     */
    public function confirmAndClearCart(
        int $teamId,
        string $phoneDigits,
        ?string $fulfillmentType = null,
        ?string $paymentMethod = null,
    ): Order {
        $carts = app(ShoppingCartService::class);
        $cart = $carts->findWhatsApp($teamId, $phoneDigits);
        $items = $carts->linesOrEmpty($cart);

        if ($items->isEmpty())
        {
            throw new InvalidArgumentException('The WhatsApp cart is empty.');
        }

        $store = $this->resolveStoreForCart($teamId, $items);
        $snapshot = $this->buildCheckoutSnapshot($store);
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

        $order = $this->createFromWhatsAppCart(
            $teamId,
            preg_replace('/[^0-9]/', '', $phoneDigits) ?: $phoneDigits,
            $items,
            (float) $carts->total($cart),
            $store?->id,
            $snapshot,
        );

        if ($cart)
        {
            $carts->clear($cart);
        }

        return $order;
    }

    /**
     * @param  iterable<object>  $cartItems
     */
    public function resolveStoreForCart(int $teamId, iterable $cartItems): ?Store
    {
        $storeIds = [];
        foreach ($cartItems as $item)
        {
            $attrs = $this->normalizeCartItemAttributes($item->attributes ?? null);
            $sid = isset($attrs['store_id']) && $attrs['store_id'] !== null && $attrs['store_id'] !== ''
                ? (int) $attrs['store_id']
                : 0;
            if ($sid <= 0 && (int) ($item->id ?? 0) > 0)
            {
                $sid = (int) (Product::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('id', (int) $item->id)
                    ->value('store_id') ?? 0);
            }
            if ($sid > 0)
            {
                $storeIds[] = $sid;
            }
        }
        $storeIds = array_values(array_unique($storeIds));

        if (count($storeIds) === 1)
        {
            $store = Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('id', $storeIds[0])
                ->first();

            return $store ?? Store::ensureMainStoreForTeam($teamId);
        }

        return Store::ensureMainStoreForTeam($teamId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCheckoutSnapshot(?Store $store): array
    {
        if (! $store)
        {
            return [];
        }

        return [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'payment_methods' => $store->enabledCheckoutPaymentMethods(),
            'payment_method_labels' => array_map(
                static fn (string $key): string => (string) (Store::checkoutPaymentMethodLabels()[$key] ?? $key),
                $store->enabledCheckoutPaymentMethods(),
            ),
            'fulfillment_types' => $store->enabledCheckoutFulfillmentTypes(),
            'fulfillment_labels' => array_map(
                static fn (string $key): string => (string) (Store::checkoutFulfillmentLabels()[$key] ?? $key),
                $store->enabledCheckoutFulfillmentTypes(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineFromCartItem(int $expectedTeamId, object $item): array
    {
        $attrs = $this->normalizeCartItemAttributes($item->attributes ?? null);
        $itemTeamId = isset($attrs['team_id']) ? (int) $attrs['team_id'] : null;
        if ($itemTeamId !== null && $itemTeamId !== $expectedTeamId)
        {
            throw new InvalidArgumentException('Cart item belongs to a different team.');
        }

        $productId = (int) $item->id;
        $quantity = (int) $item->quantity;
        $unitPrice = (float) $item->price;
        $lineTotal = round($unitPrice * $quantity, 2);

        $currencyId = isset($attrs['currency_id']) ? (int) $attrs['currency_id'] : null;
        if ($currencyId === null && $productId > 0)
        {
            $currencyId = Product::withoutGlobalScope('team')
                ->where('id', $productId)
                ->where('team_id', $expectedTeamId)
                ->value('currency_id');
        }

        return [
            'product_id' => $productId,
            'name' => (string) $item->name,
            'quantity' => $quantity,
            'unit_price' => round($unitPrice, 2),
            'line_total' => $lineTotal,
            'category_name' => isset($attrs['category_name']) ? (string) $attrs['category_name'] : null,
            'currency_id' => $currencyId,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function resolveCurrencyId(array $lines): ?int
    {
        foreach ($lines as $line)
        {
            if (! empty($line['currency_id']))
            {
                return (int) $line['currency_id'];
            }
        }

        return null;
    }

    private function generateUniqueOrderNumber(): string
    {
        do
        {
            $orderNumber = 'WA-'.strtoupper(Str::random(12));
        } while (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function findContactIdForTeamPhone(int $teamId, string $cleanDigits): ?int
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where(function ($q) use ($cleanDigits)
            {
                $q->whereHas('sources', function ($q2) use ($cleanDigits)
                {
                    $q2->where('source_id', 2)->where('value', $cleanDigits);
                })
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(CONCAT(phone, ''), ' ', ''), '+', ''), '-', '') = ?", [$cleanDigits]);
            })
            ->first();

        return $contact?->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCartItemAttributes(mixed $attributes): array
    {
        if ($attributes === null)
        {
            return [];
        }

        if (is_array($attributes))
        {
            return $attributes;
        }

        if (is_object($attributes))
        {
            $decoded = json_decode(json_encode($attributes), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
