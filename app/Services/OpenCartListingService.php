<?php

namespace App\Services;

use App\Helpers\WhatsAppCartSessionKey;
use App\Models\DatabaseStorageModel;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Open (not yet checked-out) carts from cart_storage, scoped to a team.
 */
class OpenCartListingService
{
    public function __construct(
        protected UserResolverService $userResolver,
    ) {}

    /**
     * @return SupportCollection<int, array{
     *     id: string,
     *     channel: string,
     *     customer: string,
     *     phone: string,
     *     items_label: string,
     *     quantity: int,
     *     total: float,
     *     updated_at: string,
     *     chat_url: string|null
     * }>
     */
    public function forTeam(int $teamId): SupportCollection
    {
        if ($teamId < 1)
        {
            return collect();
        }

        return DatabaseStorageModel::query()
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DatabaseStorageModel $row): ?array => $this->mapRow($row, $teamId))
            ->filter()
            ->values();
    }

    public function countForTeam(int $teamId): int
    {
        return $this->forTeam($teamId)->count();
    }

    /**
     * @return array{
     *     id: string,
     *     channel: string,
     *     customer: string,
     *     phone: string,
     *     items_label: string,
     *     quantity: int,
     *     total: float,
     *     updated_at: string,
     *     chat_url: string|null
     * }|null
     */
    private function mapRow(DatabaseStorageModel $row, int $teamId): ?array
    {
        $storageId = (string) $row->id;
        if (str_ends_with($storageId, '_cart_conditions'))
        {
            return null;
        }

        $sessionKey = str_ends_with($storageId, '_cart_items')
            ? substr($storageId, 0, -strlen('_cart_items'))
            : $storageId;

        $isPublicShop = str_starts_with($sessionKey, 'pubshop_');
        if ($isPublicShop && ! str_starts_with($sessionKey, 'pubshop_'.$teamId.'_'))
        {
            return null;
        }

        $items = $this->itemsForTeam($row->cart_data, $teamId, $isPublicShop);
        if ($items->isEmpty())
        {
            return null;
        }

        $phone = $isPublicShop ? '' : WhatsAppCartSessionKey::fromPhone($sessionKey);
        $contact = $phone !== ''
            ? $this->userResolver->findContactInTeamByPhone($teamId, $phone)
            : null;

        return [
            'id' => $storageId,
            'channel' => $isPublicShop ? __('Tienda web') : 'WhatsApp',
            'customer' => $contact?->name ?? ($phone !== '' ? $phone : __('Visitante')),
            'phone' => $phone,
            'items_label' => $items
                ->map(fn (object $item): string => ((int) $item->quantity).' × '.(string) $item->name)
                ->implode(', '),
            'quantity' => (int) $items->sum(fn (object $item): int => (int) $item->quantity),
            'total' => round((float) $items->sum(fn (object $item): float => (float) $item->price * (int) $item->quantity), 2),
            'updated_at' => $row->updated_at?->format('d/m/Y H:i') ?? '',
            'chat_url' => ($phone !== '' && (auth()->user()?->can('chat.list') || auth()->user()?->hasAnyRole(['admin', 'collaborator', 'developer', 'technical'])))
                ? route('chat.index', ['phone' => $phone])
                : null,
        ];
    }

    /**
     * @return SupportCollection<int, object>
     */
    private function itemsForTeam(mixed $cartData, int $teamId, bool $isPublicShop): SupportCollection
    {
        $items = collect();

        foreach ($this->normalizeCartData($cartData) as $raw)
        {
            $item = $this->normalizeItem($raw);
            if ($item === null)
            {
                continue;
            }

            $attrs = $this->normalizeAttributes($item->attributes ?? null);
            $itemTeamId = isset($attrs['team_id']) ? (int) $attrs['team_id'] : null;

            if ($itemTeamId === $teamId || ($itemTeamId === null && $isPublicShop))
            {
                $items->push($item);
            }
        }

        return $items;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeCartData(mixed $cartData): array
    {
        if ($cartData instanceof SupportCollection)
        {
            return $cartData->values()->all();
        }

        if (is_array($cartData))
        {
            return array_values($cartData);
        }

        if (is_object($cartData) && method_exists($cartData, 'toArray'))
        {
            $asArray = $cartData->toArray();

            return is_array($asArray) ? array_values($asArray) : [];
        }

        return [];
    }

    private function normalizeItem(mixed $raw): ?object
    {
        if ($raw instanceof SupportCollection)
        {
            $name = $raw->get('name');
            $quantity = $raw->get('quantity');
            $price = $raw->get('price');
            if ($name === null || $quantity === null || $price === null)
            {
                return null;
            }

            return (object) [
                'id' => $raw->get('id'),
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'attributes' => $raw->get('attributes'),
            ];
        }

        if (is_object($raw) && isset($raw->name, $raw->quantity, $raw->price))
        {
            return $raw;
        }

        if (is_array($raw) && isset($raw['name'], $raw['quantity'], $raw['price']))
        {
            return (object) $raw;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAttributes(mixed $attributes): array
    {
        if ($attributes instanceof SupportCollection)
        {
            return $attributes->toArray();
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
