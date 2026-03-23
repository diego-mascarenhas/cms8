<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WhatsAppCheckoutOrderService
{
    /**
     * Persist WhatsApp cart lines as a row in {@see Order} (line snapshot in metadata).
     *
     * @param  iterable<object>  $cartItems  Cart rows with id, name, price, quantity, attributes
     */
    public function createFromWhatsAppCart(int $teamId, string $cleanPhoneDigits, iterable $cartItems, float $cartTotal): Order
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

        return DB::transaction(function () use ($teamId, $cleanPhoneDigits, $lines, $cartTotal, $currencyId)
        {
            $orderNumber = $this->generateUniqueOrderNumber();

            return Order::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'order_number' => $orderNumber,
                'contact_id' => $this->findContactIdForTeamPhone($teamId, $cleanPhoneDigits),
                'total_amount' => round($cartTotal, 2),
                'currency_id' => $currencyId,
                'payment_status' => 'pending',
                'delivery_status' => 'processing',
                'notes' => 'Order placed via WhatsApp',
                'metadata' => [
                    'source' => 'whatsapp',
                    'phone' => $cleanPhoneDigits,
                    'items' => $lines,
                ],
            ]);
        });
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
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', '') = ?", [$cleanDigits]);
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
