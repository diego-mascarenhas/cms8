<?php

namespace App\Http\Controllers;

use App\Actions\Products\SyncStripeProducts;
use App\DataTables\SubscriptionProductDataTable;
use App\Models\SubscriptionProduct;
use App\Services\Stripe\StripeProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductManagementController extends Controller
{
    /**
     * Display a listing of subscription products.
     */
    public function index(SubscriptionProductDataTable $dataTable)
    {
        return $dataTable->render('account.products.index');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(string $id)
    {
        $product = SubscriptionProduct::findOrFail($id);

        return view('account.products.edit', compact('product'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = SubscriptionProduct::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'category' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'stripe_product' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'unit_amount' => 'nullable|numeric|min:0',
            'recurring_interval' => 'nullable|string|in:day,week,month,year',
            'recurring_interval_count' => 'nullable|integer|min:1',
        ]);

        // Ensure active is boolean (convert '1'/'0' to true/false)
        $validated['active'] = (bool) ($validated['active'] ?? false);

        // If stripe_product is set but stripe_id is not, set stripe_id from stripe_product
        if (! empty($validated['stripe_product']) && ! $product->stripe_id)
        {
            $validated['stripe_id'] = $validated['stripe_product'];
        }

        $product->update($validated);

        return redirect()
            ->route('account.products.index')
            ->with('success', 'Producto actualizado exitosamente');
    }

    /**
     * Update the product and sync with Stripe
     */
    public function updateAndSync(Request $request, string $id, StripeProductService $stripeService)
    {
        $product = SubscriptionProduct::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'category' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'stripe_product' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'unit_amount' => 'nullable|numeric|min:0',
            'recurring_interval' => 'nullable|string|in:day,week,month,year',
            'recurring_interval_count' => 'nullable|integer|min:1',
        ]);

        // Ensure active is boolean (convert '1'/'0' to true/false)
        $validated['active'] = (bool) ($validated['active'] ?? false);

        // If stripe_product is set but stripe_id is not, set stripe_id from stripe_product
        if (! empty($validated['stripe_product']) && ! $product->stripe_id)
        {
            $validated['stripe_id'] = $validated['stripe_product'];
        }

        // Sync with Stripe first, then update local product (to avoid Observer triggering)
        try
        {
            $stripeProductId = $product->stripe_id ?? $product->stripe_product;

            // Build metadata from validated data
            $metadata = [];
            if (! empty($validated['category']))
            {
                $metadata['category'] = $validated['category'];
            }
            if (! empty($validated['plan']))
            {
                $metadata['plan'] = $validated['plan'];
            }
            if (! empty($validated['type']))
            {
                $metadata['type'] = $validated['type'];
            }

            if ($stripeProductId)
            {
                // Update product in Stripe with validated data
                $stripeService->update($stripeProductId, [
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'active' => $validated['active'],
                    'metadata' => $metadata,
                ]);

                // Check if price changed by comparing with current values
                $priceChanged = ($product->unit_amount != ($validated['unit_amount'] ?? null)) ||
                    ($product->currency != $validated['currency']) ||
                    ($product->recurring_interval != ($validated['recurring_interval'] ?? null)) ||
                    ($product->recurring_interval_count != ($validated['recurring_interval_count'] ?? 1));

                // If price changed and we don't have a stripe_price, create a new price
                if ($priceChanged && ! $product->stripe_price)
                {
                    $stripePrice = $stripeService->createPrice($stripeProductId, [
                        'currency' => $validated['currency'] ?? 'usd',
                        'unit_amount' => (int) (($validated['unit_amount'] ?? 0) * 100),
                        'recurring' => ! empty($validated['recurring_interval']) ? [
                            'interval' => $validated['recurring_interval'],
                            'interval_count' => $validated['recurring_interval_count'] ?? 1,
                        ] : null,
                    ]);

                    $validated['stripe_price'] = $stripePrice->id;
                }
            } else
            {
                // Create product in Stripe if it doesn't exist
                $stripeProduct = $stripeService->create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'active' => $validated['active'],
                    'metadata' => $metadata,
                ]);

                // Create price in Stripe
                $stripePrice = $stripeService->createPrice($stripeProduct->id, [
                    'currency' => $validated['currency'] ?? 'usd',
                    'unit_amount' => (int) (($validated['unit_amount'] ?? 0) * 100),
                    'recurring' => ! empty($validated['recurring_interval']) ? [
                        'interval' => $validated['recurring_interval'],
                        'interval_count' => $validated['recurring_interval_count'] ?? 1,
                    ] : null,
                ]);

                // Update local record with Stripe IDs (without triggering Observer)
                $validated['stripe_id'] = $stripeProduct->id;
                $validated['stripe_product'] = $stripeProduct->id;
                $validated['stripe_price'] = $stripePrice->id;
            }

            // Update local product without triggering Observer events
            SubscriptionProduct::withoutEvents(function () use ($product, $validated)
            {
                $product->update($validated);
            });

            return redirect()
                ->route('account.products.index')
                ->with('success', 'Producto guardado y actualizado en Stripe exitosamente.');
        } catch (\Exception $e)
        {
            Log::error('Failed to update product in Stripe', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('account.products.index')
                ->with('error', 'Producto guardado localmente, pero hubo un error al actualizar en Stripe: '.$e->getMessage());
        }
    }

    /**
     * Build metadata array for Stripe
     */
    private function buildMetadata(SubscriptionProduct $product): array
    {
        $metadata = [];

        if ($product->category)
        {
            $metadata['category'] = $product->category;
        }

        if ($product->plan)
        {
            $metadata['plan'] = $product->plan;
        }

        if ($product->type)
        {
            $metadata['type'] = $product->type;
        }

        return $metadata;
    }

    /**
     * Sync a specific product from Stripe (overwrites local data)
     */
    public function sync(string $id, SyncStripeProducts $syncAction)
    {
        $product = SubscriptionProduct::findOrFail($id);

        try
        {
            // Use stripe_product or stripe_id to sync from Stripe
            $stripeProductId = $product->stripe_product ?? $product->stripe_id;

            if (! $stripeProductId)
            {
                return redirect()
                    ->route('account.products.edit', $product->id)
                    ->with('error', 'El producto no tiene un Stripe Product ID. Por favor, ingrésalo en el campo "Stripe Product ID" y guarda antes de sincronizar.');
            }

            // Sync specific product from Stripe (this will overwrite all local data)
            $syncAction->syncProduct($stripeProductId);

            // Reload product to get updated data
            $product->refresh();

            return redirect()
                ->route('account.products.edit', $product->id)
                ->with('success', 'Producto sincronizado desde Stripe. Todos los datos locales han sido actualizados.');
        } catch (\Exception $e)
        {
            return redirect()
                ->route('account.products.edit', $product->id)
                ->with('error', 'Error al sincronizar desde Stripe: '.$e->getMessage());
        }
    }
}
