<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Enums\ShoppingCartChannel;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    use ResolvesShopTeam;

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        Store::ensureMainStoreForTeam((int) $team->id);

        $productsModuleId = Module::query()->where('key', 'products')->value('id');
        $categories = $productsModuleId
            ? Category::getOptions($team->id, null, $productsModuleId)->values()->all()
            : [];

        $brands = Brand::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
            ])
            ->values()
            ->all();

        $stores = Store::query()
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_main', 'status'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'is_main' => (bool) $store->is_main,
                'status' => (bool) $store->status,
            ])
            ->values()
            ->all();

        $currencies = Currency::query()
            ->where('status', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol'])
            ->map(fn (Currency $currency): array => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'brands' => $brands,
                'currencies' => $currencies,
                'stores' => $stores,
                'catalog_statuses' => $this->enumOptions(ProductCatalogStatus::cases()),
                'stock_statuses' => $this->enumOptions(ProductStockStatus::cases()),
                'payment_methods' => $this->keyedLabels(Store::checkoutPaymentMethodLabels()),
                'payment_types' => $this->keyedLabels(Store::checkoutPaymentMethodLabels()),
                'fulfillment_types' => $this->keyedLabels(Store::checkoutFulfillmentLabels()),
                'payment_statuses' => [
                    ['key' => 'pending', 'label' => __('Pendiente')],
                    ['key' => 'paid', 'label' => __('Pagado')],
                    ['key' => 'failed', 'label' => __('Fallido')],
                    ['key' => 'refunded', 'label' => __('Reembolsado')],
                    ['key' => 'cancelled', 'label' => __('Cancelado')],
                ],
                'delivery_statuses' => [
                    ['key' => 'processing', 'label' => __('Procesando')],
                    ['key' => 'dispatched', 'label' => __('Despachado')],
                    ['key' => 'out_for_delivery', 'label' => __('En camino')],
                    ['key' => 'delivered', 'label' => __('Entregado')],
                    ['key' => 'cancelled', 'label' => __('Cancelado')],
                ],
                'cart_channels' => $this->enumOptions(ShoppingCartChannel::cases()),
            ],
        ]);
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<array{key: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case): array => [
            'key' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : $case->value,
        ], $cases);
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private function keyedLabels(array $labels): array
    {
        $out = [];
        foreach ($labels as $key => $label)
        {
            $out[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        return $out;
    }
}
