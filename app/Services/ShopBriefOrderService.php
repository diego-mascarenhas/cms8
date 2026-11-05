<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Team;
use InvalidArgumentException;

class ShopBriefOrderService
{
    /**
     * Persist a priced product quote as a shop order.
     *
     * @param  array<string, mixed>  $quote
     */
    public function create(Team $team, array $quote, string $channel, ?string $phoneDigits = null): Order
    {
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];
        $items = [];
        $total = 0.0;

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $name = trim((string) ($line['name'] ?? ''));
            if ($name === '')
            {
                continue;
            }

            $quantity = max(1, (int) round((float) ($line['quantity'] ?? 1)));
            $unitPrice = is_numeric($line['unit_price_amount'] ?? null) ? (float) $line['unit_price_amount'] : 0.0;
            $productId = (int) ($line['product_id'] ?? 0);
            $total += round($unitPrice * $quantity, 2);

            $attributes = [
                'team_id' => $team->id,
            ];
            if (! empty($line['currency_id']))
            {
                $attributes['currency_id'] = (int) $line['currency_id'];
            }

            $items[] = (object) [
                'id' => $productId,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'attributes' => $attributes,
            ];
        }

        if ($items === [])
        {
            throw new InvalidArgumentException(__('Could not generate the estimate. Please try again.'));
        }

        $summary = trim((string) ($quote['summary'] ?? ''));
        $fromShop = $channel !== 'whatsapp';
        $notes = $fromShop
            ? 'Pedido armado desde Pedidos.'
            : 'Pedido armado por WhatsApp.';
        if ($summary !== '')
        {
            $notes .= "\n".$summary;
        }

        $phone = $phoneDigits !== null ? preg_replace('/[^0-9]/', '', $phoneDigits) : null;

        return app(WhatsAppCheckoutOrderService::class)->createFromCartLines(
            teamId: (int) $team->id,
            cartItems: $items,
            cartTotal: round($total, 2),
            source: $fromShop ? 'shop_brief' : 'whatsapp',
            phoneDigits: $phone !== '' ? $phone : null,
            notes: $notes,
            orderNumberPrefix: $fromShop ? 'PED-' : 'WA-',
        );
    }
}
