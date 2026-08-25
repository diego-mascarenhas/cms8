<?php

namespace App\Services;

use App\Enums\ShoppingCartChannel;
use App\Helpers\WhatsAppCartSessionKey;
use App\Models\ShoppingCart;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Open (not yet checked-out) carts scoped to a team.
 */
class OpenCartListingService
{
    public function __construct(
        protected UserResolverService $userResolver,
        protected ShoppingCartService $shoppingCarts,
    ) {}

    /**
     * @return SupportCollection<int, array{
     *     id: string,
     *     channel: string,
     *     customer: string,
     *     phone: string,
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

        return $this->shoppingCarts
            ->openCartsForTeam($teamId)
            ->map(fn (ShoppingCart $cart): array => $this->mapCart($cart))
            ->values();
    }

    public function countForTeam(int $teamId): int
    {
        return $this->shoppingCarts->countOpenForTeam($teamId);
    }

    /**
     * @return array{
     *     id: string,
     *     channel: string,
     *     customer: string,
     *     phone: string,
     *     quantity: int,
     *     total: float,
     *     updated_at: string,
     *     chat_url: string|null,
     *     items: list<array{id: int, product_id: int, product_variant_id: int|null, name: string, quantity: int, unit_price: float, line_total: float, category_name: string|null, option_label: string|null}>
     * }|null
     */
    public function detailForTeam(int $teamId, int $cartId): ?array
    {
        $cart = $this->shoppingCarts->findForTeam($teamId, $cartId);
        if (! $cart)
        {
            return null;
        }

        $row = $this->mapCart($cart);
        $items = $cart->relationLoaded('items')
            ? $cart->items
            : $cart->items()->withoutGlobalScope('team')->get();

        $row['items'] = $items
            ->map(fn ($item): array => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                'option_label' => $item->option_label,
                'name' => (string) $item->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'line_total' => $item->lineTotal(),
                'category_name' => $item->category_name,
            ])
            ->values()
            ->all();

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(ShoppingCart $cart, bool $withItems = false): array
    {
        $channel = $cart->channel instanceof ShoppingCartChannel
            ? $cart->channel
            : ShoppingCartChannel::WhatsApp;
        $phone = $channel === ShoppingCartChannel::PublicShop
            ? ''
            : WhatsAppCartSessionKey::fromPhone((string) $cart->session_key);
        $contact = $phone !== ''
            ? $this->userResolver->findContactInTeamByPhone((int) $cart->team_id, $phone)
            : null;
        $items = $cart->relationLoaded('items')
            ? $cart->items
            : $cart->items()->withoutGlobalScope('team')->get();

        $payload = [
            'id' => (int) $cart->id,
            'channel' => $channel->value,
            'channel_label' => $channel->label(),
            'customer' => $contact?->name ?? ($phone !== '' ? $phone : __('Visitante')),
            'phone' => $phone !== '' ? $phone : null,
            'contact' => $contact ? [
                'id' => (int) $contact->id,
                'name' => $contact->name,
            ] : null,
            'items_label' => $items
                ->map(fn ($item): string => ((int) $item->quantity).' × '.(string) $item->name)
                ->implode(', '),
            'quantity' => (int) $items->sum('quantity'),
            'total' => round((float) $items->sum(fn ($item): float => $item->lineTotal()), 2),
            'updated_at' => $cart->updated_at?->toIso8601String(),
        ];

        if ($withItems)
        {
            $payload['items'] = $items
                ->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                    'option_label' => $item->option_label,
                    'name' => (string) $item->name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->price,
                    'line_total' => $item->lineTotal(),
                    'category_name' => $item->category_name,
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array{
     *     id: string,
     *     channel: string,
     *     customer: string,
     *     phone: string,
     *     quantity: int,
     *     total: float,
     *     updated_at: string,
     *     chat_url: string|null
     * }
     */
    private function mapCart(ShoppingCart $cart): array
    {
        $isPublicShop = $cart->channel === ShoppingCartChannel::PublicShop;
        $phone = $isPublicShop ? '' : WhatsAppCartSessionKey::fromPhone((string) $cart->session_key);
        $contact = $phone !== ''
            ? $this->userResolver->findContactInTeamByPhone((int) $cart->team_id, $phone)
            : null;
        $items = $cart->items;

        return [
            'id' => (string) $cart->id,
            'channel' => $cart->channel instanceof ShoppingCartChannel
                ? $cart->channel->label()
                : ($isPublicShop ? __('Tienda web') : 'WhatsApp'),
            'customer' => $contact?->name ?? ($phone !== '' ? $phone : __('Visitante')),
            'phone' => $phone,
            'quantity' => (int) $items->sum('quantity'),
            'total' => $cart->totalAmount(),
            'updated_at' => $cart->updated_at?->format('d/m/Y H:i') ?? '',
            'chat_url' => ($phone !== '' && (auth()->user()?->can('chat.list') || auth()->user()?->hasAnyRole(['admin', 'collaborator', 'developer', 'technical'])))
                ? route('chat.index', ['phone' => $phone])
                : null,
        ];
    }
}
