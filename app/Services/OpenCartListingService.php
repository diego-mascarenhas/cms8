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

        return $this->shoppingCarts
            ->openCartsForTeam($teamId)
            ->map(fn (ShoppingCart $cart): array => $this->mapCart($cart))
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
            'items_label' => $items
                ->map(fn ($item): string => ((int) $item->quantity).' × '.(string) $item->name)
                ->implode(', '),
            'quantity' => (int) $items->sum('quantity'),
            'total' => $cart->totalAmount(),
            'updated_at' => $cart->updated_at?->format('d/m/Y H:i') ?? '',
            'chat_url' => ($phone !== '' && (auth()->user()?->can('chat.list') || auth()->user()?->hasAnyRole(['admin', 'collaborator', 'developer', 'technical'])))
                ? route('chat.index', ['phone' => $phone])
                : null,
        ];
    }
}
