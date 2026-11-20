<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function createBlank(Team $team, ?int $contactId = null): Order
    {
        return Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'contact_id' => $contactId,
            'order_number' => $this->nextOrderNumber(),
            'total_amount' => 0,
            'currency_id' => Currency::query()->where('code', 'ARS')->value('id'),
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'notes' => 'Pedido armado a mano.',
            'metadata' => [
                'source' => 'shop_manual',
                'items' => [],
            ],
        ]);
    }

    private function nextOrderNumber(): string
    {
        do
        {
            $orderNumber = 'PED-'.strtoupper(Str::random(12));
        } while (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function resolveContact(Team $team, ?int $contactId): ?Contact
    {
        if ($contactId === null || $contactId < 1)
        {
            return null;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereKey($contactId)
            ->first();

        if (! $contact)
        {
            throw ValidationException::withMessages([
                'contact_id' => __('The selected contact does not belong to the current team.'),
            ]);
        }

        return $contact;
    }

    public function attachContact(Order $order, Contact $contact): Order
    {
        $order->contact_id = $contact->id;
        $order->save();

        return $order;
    }
}
