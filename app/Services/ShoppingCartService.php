<?php

namespace App\Services;

use App\Enums\ShoppingCartChannel;
use App\Helpers\WhatsAppCartSessionKey;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ShoppingCartService
{
    public function forWhatsApp(int $teamId, string $phone): ShoppingCart
    {
        $sessionKey = WhatsAppCartSessionKey::fromPhone($phone);

        return $this->firstOrCreate($teamId, $sessionKey, ShoppingCartChannel::WhatsApp);
    }

    public function forPublicShop(int $teamId, string $laravelSessionId): ShoppingCart
    {
        return $this->firstOrCreate(
            $teamId,
            $this->publicShopSessionKey($teamId, $laravelSessionId),
            ShoppingCartChannel::PublicShop,
        );
    }

    public function findWhatsApp(int $teamId, string $phone): ?ShoppingCart
    {
        $sessionKey = WhatsAppCartSessionKey::fromPhone($phone);
        if ($teamId < 1 || $sessionKey === '')
        {
            return null;
        }

        return $this->find($teamId, $sessionKey, ShoppingCartChannel::WhatsApp);
    }

    public function findPublicShop(int $teamId, string $laravelSessionId): ?ShoppingCart
    {
        if ($teamId < 1 || $laravelSessionId === '')
        {
            return null;
        }

        return $this->find($teamId, $this->publicShopSessionKey($teamId, $laravelSessionId), ShoppingCartChannel::PublicShop);
    }

    public function find(int $teamId, string $sessionKey, ShoppingCartChannel $channel): ?ShoppingCart
    {
        return ShoppingCart::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('channel', $channel->value)
            ->where('session_key', $sessionKey)
            ->first();
    }

    public function firstOrCreate(int $teamId, string $sessionKey, ShoppingCartChannel $channel): ShoppingCart
    {
        if ($teamId < 1 || $sessionKey === '')
        {
            throw new \InvalidArgumentException('A team and session key are required for a shopping cart.');
        }

        return ShoppingCart::withoutGlobalScope('team')->firstOrCreate(
            [
                'team_id' => $teamId,
                'channel' => $channel->value,
                'session_key' => $sessionKey,
            ],
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function whatsAppLines(int $teamId, string $phone): Collection
    {
        return $this->linesOrEmpty($this->findWhatsApp($teamId, $phone));
    }

    /**
     * @return Collection<int, object>
     */
    public function linesOrEmpty(?ShoppingCart $cart): Collection
    {
        return $cart ? $this->lines($cart) : collect();
    }

    public function addProduct(ShoppingCart $cart, Product $product, int $quantity = 1): ShoppingCartItem
    {
        $quantity = max(1, min(500, $quantity));
        $product->loadMissing(['category', 'currency']);

        $item = $this->itemQuery($cart)
            ->where('product_id', $product->id)
            ->first();

        if ($item)
        {
            $item->quantity = (int) $item->quantity + $quantity;
            $item->name = $product->name;
            $item->price = $product->currentSellingPrice();
            $item->currency_id = $product->currency_id;
            $item->store_id = $product->store_id;
            $item->category_name = $product->category?->name;
            $item->description = $product->description;
            $item->save();
        } else
        {
            $item = $this->itemQuery($cart)->create([
                'team_id' => $cart->team_id,
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->currentSellingPrice(),
                'quantity' => $quantity,
                'currency_id' => $product->currency_id,
                'store_id' => $product->store_id,
                'category_name' => $product->category?->name,
                'description' => $product->description,
            ]);
        }

        $this->touch($cart);

        return ShoppingCartItem::withoutGlobalScope('team')->find($item->id) ?? $item;
    }

    public function setProductQuantity(ShoppingCart $cart, int $productId, int $quantity): void
    {
        $item = $this->itemQuery($cart)->where('product_id', $productId)->first();
        if (! $item)
        {
            return;
        }

        if ($quantity <= 0)
        {
            $item->delete();
            $this->touch($cart);

            return;
        }

        $item->quantity = min(500, $quantity);
        $item->save();
        $this->touch($cart);
    }

    public function removeProduct(ShoppingCart $cart, int $productId): void
    {
        $this->setProductQuantity($cart, $productId, 0);
    }

    public function clear(ShoppingCart $cart): void
    {
        $this->itemQuery($cart)->delete();
        $this->touch($cart);
    }

    /**
     * @return Collection<int, object>
     */
    public function lines(ShoppingCart $cart): Collection
    {
        $cart->unsetRelation('items');

        return $this->itemQuery($cart)->get()->map(fn (ShoppingCartItem $item): object => $item->toLineObject())->values();
    }

    public function total(ShoppingCart $cart): float
    {
        return round((float) $this->lines($cart)->sum(fn (object $item): float => (float) $item->price * (int) $item->quantity), 2);
    }

    public function quantity(ShoppingCart $cart): int
    {
        return (int) $this->lines($cart)->sum(fn (object $item): int => (int) $item->quantity);
    }

    /**
     * Open carts that still have lines, for the admin list.
     *
     * @return Collection<int, ShoppingCart>
     */
    public function openCartsForTeam(int $teamId): Collection
    {
        return $this->openCartsQuery($teamId)
            ->with(['items' => fn ($query) => $query->withoutGlobalScope('team')])
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @return Builder<ShoppingCart>
     */
    public function openCartsQuery(int $teamId): Builder
    {
        return ShoppingCart::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->whereHas('items', fn ($query) => $query->withoutGlobalScope('team'));
    }

    public function countOpenForTeam(int $teamId): int
    {
        if ($teamId < 1)
        {
            return 0;
        }

        return $this->openCartsQuery($teamId)->count();
    }

    public function findForTeam(int $teamId, int $cartId): ?ShoppingCart
    {
        if ($teamId < 1 || $cartId < 1)
        {
            return null;
        }

        return ShoppingCart::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->with(['items' => fn ($query) => $query->withoutGlobalScope('team')])
            ->find($cartId);
    }

    /**
     * @return HasMany<ShoppingCartItem, ShoppingCart>
     */
    private function itemQuery(ShoppingCart $cart): HasMany
    {
        return $cart->items()->withoutGlobalScope('team');
    }

    private function publicShopSessionKey(int $teamId, string $laravelSessionId): string
    {
        return 'pubshop_'.$teamId.'_'.$laravelSessionId;
    }

    private function touch(ShoppingCart $cart): void
    {
        $cart->touch();
        $cart->unsetRelation('items');
    }
}
