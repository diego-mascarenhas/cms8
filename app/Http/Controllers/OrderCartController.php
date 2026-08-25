<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShoppingCartRequest;
use App\Models\Order;
use App\Models\ShoppingCartItem;
use App\Services\OpenCartListingService;
use App\Services\ShoppingCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderCartController extends Controller
{
    public function __construct(
        protected ShoppingCartService $shoppingCarts,
        protected OpenCartListingService $listing,
    ) {}

    public function show(int $id): View|RedirectResponse
    {
        $this->authorize('viewAny', Order::class);

        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $cart = $this->listing->detailForTeam((int) $team->id, $id);
        if (! $cart)
        {
            abort(404);
        }

        $canEdit = auth()->user()->can('update', new Order(['team_id' => $team->id]));

        return view('order.cart-show', compact('cart', 'canEdit'));
    }

    public function update(UpdateShoppingCartRequest $request, int $id): RedirectResponse
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart)
        {
            abort(404);
        }

        foreach ($request->validated('items') as $row)
        {
            $item = $cart->items->firstWhere('id', (int) $row['id']);
            if (! $item)
            {
                continue;
            }

            $this->shoppingCarts->setProductQuantity($cart, (int) $item->product_id, (int) $row['quantity']);
        }

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart || $cart->items->isEmpty())
        {
            return redirect()
                ->route('order.carts')
                ->with('success', __('El carrito quedó vacío.'));
        }

        return redirect()
            ->route('order.carts.show', $id)
            ->with('success', __('Carrito actualizado.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('update', new Order(['team_id' => auth()->user()?->currentTeam?->id]));

        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart)
        {
            abort(404);
        }

        $this->shoppingCarts->clear($cart);
        $cart->delete();

        return redirect()
            ->route('order.carts')
            ->with('success', __('Carrito eliminado.'));
    }

    public function destroyItem(int $id, int $item): RedirectResponse
    {
        $this->authorize('update', new Order(['team_id' => auth()->user()?->currentTeam?->id]));

        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart)
        {
            abort(404);
        }

        $line = $cart->items->firstWhere('id', $item);
        if (! $line instanceof ShoppingCartItem)
        {
            abort(404);
        }

        $this->shoppingCarts->removeProduct($cart, (int) $line->product_id);

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart || $cart->items->isEmpty())
        {
            return redirect()
                ->route('order.carts')
                ->with('success', __('El carrito quedó vacío.'));
        }

        return redirect()
            ->route('order.carts.show', $id)
            ->with('success', __('Producto quitado del carrito.'));
    }
}
