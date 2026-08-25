<?php

namespace App\Http\Controllers;

use App\DataTables\OpenCartDataTable;
use App\DataTables\OrderDataTable;
use App\Http\Requests\UpdateWooCommerceOrderRequest;
use App\Models\Order;
use App\Services\OpenCartListingService;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Constructor to authorize order permissions
     */
    public function __construct()
    {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {order} parameter, but routes use {id}
    }

    /**
     * Display a listing of the resource (local orders or WooCommerce orders when API is configured).
     */
    public function index(OrderDataTable $dataTable)
    {
        $this->authorize('viewAny', Order::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $openCartsCount = app(OpenCartListingService::class)->countForTeam((int) $team->id);

        $woo = new WooCommerceService($team);
        if ($woo->isConfigured())
        {
            $orders = $woo->getOrders(1, 100);
            $storeUrl = $woo->getStoreUrl();

            return view('order.woocommerce-list', compact('orders', 'storeUrl', 'openCartsCount'));
        }

        return $dataTable->render('order.list', compact('openCartsCount'));
    }

    /**
     * Carts that have items but were not confirmed as an order yet.
     */
    public function carts(OpenCartDataTable $dataTable)
    {
        $this->authorize('viewAny', Order::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('order.carts');
    }

    /**
     * Show the form for editing the specified WooCommerce order.
     */
    public function edit(string $id): View|RedirectResponse
    {
        $this->authorize('update', new Order(['team_id' => auth()->user()->currentTeam?->id]));

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('order.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        $order = $woo->getOrder((int) $id);
        if (! $order)
        {
            return redirect()->route('order.index')->with('error', __('Order not found.'));
        }

        return view('order.woocommerce-form', ['order' => $order, 'storeUrl' => $woo->getStoreUrl()]);
    }

    /**
     * Update the specified WooCommerce order via API.
     */
    public function update(UpdateWooCommerceOrderRequest $request, string $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('order.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        $data = $this->orderRequestToApiPayload($request->validated());
        $updated = $woo->updateOrder((int) $id, $data);

        if (! $updated)
        {
            return back()->withInput()->with('error', __('Failed to update order in WooCommerce.'));
        }

        return redirect()->route('order.index')->with('success', __('Order updated successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::with(['contact', 'currency', 'team', 'store'])->findOrFail($id);

        $this->authorize('view', $order);

        return view('order.show', compact('order'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function orderRequestToApiPayload(array $validated): array
    {
        $payload = [
            'status' => $validated['status'],
        ];

        if (array_key_exists('customer_note', $validated))
        {
            $payload['customer_note'] = (string) ($validated['customer_note'] ?? '');
        }

        if (isset($validated['billing']) && is_array($validated['billing']))
        {
            $payload['billing'] = array_filter([
                'first_name' => $validated['billing']['first_name'] ?? null,
                'last_name' => $validated['billing']['last_name'] ?? null,
                'company' => $validated['billing']['company'] ?? null,
                'address_1' => $validated['billing']['address_1'] ?? null,
                'address_2' => $validated['billing']['address_2'] ?? null,
                'city' => $validated['billing']['city'] ?? null,
                'state' => $validated['billing']['state'] ?? null,
                'postcode' => $validated['billing']['postcode'] ?? null,
                'country' => $validated['billing']['country'] ?? null,
                'email' => $validated['billing']['email'] ?? null,
                'phone' => $validated['billing']['phone'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $payload;
    }
}
