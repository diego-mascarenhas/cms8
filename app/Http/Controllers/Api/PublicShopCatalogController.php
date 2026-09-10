<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductCatalogStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Services\ProductImageService;
use App\Support\ShopCatalogApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicShopCatalogController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $team = Team::findByCatalogSlug($slug);
        if (! $team)
        {
            return $this->shopNotFound();
        }

        $ttlSeconds = (int) config('cache.shop_catalog_ttl', 0);
        if ($ttlSeconds === 0)
        {
            return response()->json($this->buildIndexPayload($team, $slug));
        }

        $generation = ShopCatalogApiCache::currentGeneration((int) $team->id);
        $cacheKey = ShopCatalogApiCache::indexCacheKey((int) $team->id, $generation, $slug);
        $resolver = fn (): array => $this->buildIndexPayload($team, $slug);

        $payload = $ttlSeconds < 0
            ? Cache::rememberForever($cacheKey, $resolver)
            : Cache::remember($cacheKey, $ttlSeconds, $resolver);

        return response()->json($payload);
    }

    public function show(string $slug, string $code): JsonResponse
    {
        $team = Team::findByCatalogSlug($slug);
        if (! $team)
        {
            return $this->shopNotFound();
        }

        $normalized = mb_strtolower(trim($code));
        if ($normalized === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found.'),
            ], 404);
        }

        $ttlSeconds = (int) config('cache.shop_catalog_ttl', 0);
        if ($ttlSeconds === 0)
        {
            return $this->executeShow($team, $normalized);
        }

        $generation = ShopCatalogApiCache::currentGeneration((int) $team->id);
        $cacheKey = ShopCatalogApiCache::productCacheKey((int) $team->id, $generation, $slug, $normalized);
        $resolver = fn (): array => $this->buildShowPayload($team, $normalized);

        $payload = $ttlSeconds < 0
            ? Cache::rememberForever($cacheKey, $resolver)
            : Cache::remember($cacheKey, $ttlSeconds, $resolver);

        $status = ($payload['success'] ?? false) ? 200 : 404;

        return response()->json($payload, $status);
    }

    /**
     * @return array{success: true, data: array<string, mixed>}
     */
    private function buildIndexPayload(Team $team, string $slug): array
    {
        $products = Product::withoutGlobalScope('team')
            ->with(['brand', 'currency', 'category', 'store', 'stores', 'options.values'])
            ->where('team_id', $team->id)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(200)
            ->get();

        $transformed = $products
            ->map(fn (Product $product): array => $this->transform($team, $product))
            ->values();

        return [
            'success' => true,
            'data' => array_merge($this->storefrontMeta($team, $slug), [
                'categories' => $this->categoriesFromProducts($products),
                'products' => $transformed,
                'featured_products' => $this->featuredProducts($transformed),
            ]),
        ];
    }

    private function executeShow(Team $team, string $normalizedCode): JsonResponse
    {
        $payload = $this->buildShowPayload($team, $normalizedCode);
        $status = ($payload['success'] ?? false) ? 200 : 404;

        return response()->json($payload, $status);
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    private function buildShowPayload(Team $team, string $normalizedCode): array
    {
        $product = Product::withoutGlobalScope('team')
            ->with(['brand', 'currency', 'category', 'store', 'stores', 'options.values'])
            ->where('team_id', $team->id)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->whereRaw('LOWER(code) = ?', [$normalizedCode])
            ->first();

        if (! $product)
        {
            return [
                'success' => false,
                'message' => __('Product not found.'),
            ];
        }

        return [
            'success' => true,
            'data' => $this->transform($team, $product),
        ];
    }

    /**
     * @return array{
     *     slug: string,
     *     shop_name: string,
     *     url: string|null,
     *     address: string|null,
     *     phone: string|null,
     *     whatsapp: string|null,
     *     hours_label: string|null,
     *     notes: string|null,
     *     logo: string|null,
     *     banner: string|null,
     *     social: array{facebook: string|null, instagram: string|null, youtube: string|null},
     *     stores: list<array{
     *         id: int,
     *         name: string,
     *         is_main: bool,
     *         address: string|null,
     *         phone: string|null,
     *         whatsapp: string|null,
     *         hours_label: string|null,
     *         notes: string|null
     *     }>
     * }
     */
    private function storefrontMeta(Team $team, string $slug): array
    {
        $stores = Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('status', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $store = $stores->first();
        $config = $team->getDecodedBusinessConfig();
        $phone = trim((string) (data_get($store?->data, 'phone') ?: ($config['business_phone'] ?? '')));
        $whatsapp = trim((string) (data_get($store?->data, 'whatsapp') ?: $team->getWhatsAppFrom() ?: ''));
        $address = trim((string) ($store?->address ?: ($config['business_address'] ?? '')));
        $notes = trim((string) (data_get($store?->data, 'notes') ?: ''));
        $logo = $this->publicImageUrl(
            data_get($config, '_logo.url')
            ?? data_get($config, '_logo.path')
            ?? ($config['business_logo'] ?? null)
            ?? ($config['logo'] ?? null),
        );
        $storeBanner = $this->publicImageUrl(data_get($store?->data, 'banner'));
        $businessBanner = $this->publicImageUrl($config['business_banner'] ?? $config['banner'] ?? null);

        return [
            'slug' => $team->publicCatalogPathSlug() ?? $slug,
            'shop_name' => $this->shopName($team),
            'url' => $team->publicCatalogShopUrl(),
            'address' => $address !== '' ? $address : null,
            'phone' => $phone !== '' ? $phone : null,
            'whatsapp' => $whatsapp !== '' ? preg_replace('/\D+/', '', $whatsapp) : null,
            'hours_label' => $this->hoursLabel($store),
            'notes' => $notes !== '' ? $notes : null,
            'logo' => $logo,
            'banner' => $storeBanner ?? $businessBanner,
            'social' => [
                'facebook' => $this->nullableUrl($config['facebook'] ?? $config['business_facebook'] ?? null),
                'instagram' => $this->nullableUrl($config['instagram'] ?? $config['business_instagram'] ?? null),
                'youtube' => $this->nullableUrl($config['youtube'] ?? $config['business_youtube'] ?? null),
            ],
            'stores' => $stores
                ->map(fn (Store $item): array => $this->transformStorefrontStore($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     is_main: bool,
     *     address: string|null,
     *     phone: string|null,
     *     whatsapp: string|null,
     *     hours_label: string|null,
     *     notes: string|null,
     *     checkout_payment_methods: list<array{key: string, label: string}>,
     *     checkout_fulfillment_types: list<array{key: string, label: string}>
     * }
     */
    private function transformStorefrontStore(Store $store): array
    {
        $phone = trim((string) data_get($store->data, 'phone', ''));
        $whatsapp = trim((string) data_get($store->data, 'whatsapp', ''));
        $address = trim((string) ($store->address ?? ''));
        $notes = trim((string) data_get($store->data, 'notes', ''));
        $paymentLabels = Store::checkoutPaymentMethodLabels();
        $fulfillmentLabels = Store::checkoutFulfillmentLabels();

        return [
            'id' => (int) $store->id,
            'name' => (string) $store->name,
            'is_main' => (bool) $store->is_main,
            'address' => $address !== '' ? $address : null,
            'phone' => $phone !== '' ? $phone : null,
            'whatsapp' => $whatsapp !== '' ? preg_replace('/\D+/', '', $whatsapp) : null,
            'hours_label' => $this->hoursLabel($store),
            'notes' => $notes !== '' ? $notes : null,
            'checkout_payment_methods' => array_map(
                static fn (string $key): array => [
                    'key' => $key,
                    'label' => (string) ($paymentLabels[$key] ?? $key),
                ],
                $store->enabledCheckoutPaymentMethods(),
            ),
            'checkout_fulfillment_types' => array_map(
                static fn (string $key): array => [
                    'key' => $key,
                    'label' => (string) ($fulfillmentLabels[$key] ?? $key),
                ],
                $store->enabledCheckoutFulfillmentTypes(),
            ),
            'delivery_area' => self::nullableTrimmedString(data_get($store->data, 'delivery.area')),
            'delivery_notes' => self::nullableTrimmedString(data_get($store->data, 'delivery.notes')),
            'delivery_cost' => is_numeric(data_get($store->data, 'delivery.cost'))
                ? (float) data_get($store->data, 'delivery.cost')
                : null,
            'banner' => $this->publicImageUrl(data_get($store->data, 'banner')),
        ];
    }

    private static function nullableTrimmedString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Featured strip: only products marked is_featured in the admin form.
     *
     * @param  Collection<int, array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function featuredProducts(Collection $products): array
    {
        return $products
            ->filter(fn (array $product): bool => (bool) ($product['is_featured'] ?? false))
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array{slug: string, name: string, description: string|null, image: string|null, products_count: int}>
     */
    private function categoriesFromProducts(Collection $products): array
    {
        $groups = [];
        foreach ($products as $product)
        {
            $name = trim((string) ($product->category?->name ?? ''));
            if ($name === '')
            {
                $name = __('Sin categoría');
            }
            $key = Str::slug($name) ?: 'sin-categoria';
            if (! isset($groups[$key]))
            {
                $groups[$key] = [
                    'slug' => $key,
                    'name' => $name,
                    'description' => $product->category?->description
                        ? trim(strip_tags((string) $product->category->description))
                        : null,
                    'image' => $this->publicImageUrl($product->image),
                    'products_count' => 0,
                ];
            }
            $groups[$key]['products_count']++;
            if ($groups[$key]['image'] === null)
            {
                $groups[$key]['image'] = $this->publicImageUrl($product->image);
            }
        }

        return array_values($groups);
    }

    private function hoursLabel(?Store $store): ?string
    {
        if (! $store)
        {
            return null;
        }

        $hours = $store->openingHours();
        if ($hours === [])
        {
            return null;
        }

        $openRows = array_values(array_filter(
            $hours,
            fn ($row): bool => is_array($row) && empty($row['closed']) && ! empty($row['open']) && ! empty($row['close']),
        ));
        if ($openRows === [])
        {
            return null;
        }

        $first = $openRows[0];
        $label = trim((string) $first['open']).' a '.trim((string) $first['close']);
        if (! empty($first['afternoon_open']) && ! empty($first['afternoon_close']))
        {
            $label = 'De '.$label.' y de '.trim((string) $first['afternoon_open']).' a '.trim((string) $first['afternoon_close']);
        }

        return $label !== '' ? $label.'hs' : null;
    }

    private function nullableUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '')
        {
            return null;
        }

        if (preg_match('#^https?://#i', $url) !== 1)
        {
            $url = 'https://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * @return array{
     *     name: string,
     *     code: string,
     *     slug: string,
     *     brand: string|null,
     *     category: string|null,
     *     category_slug: string|null,
     *     description: string|null,
     *     short_description: string|null,
     *     price: string|null,
     *     price_amount: float|null,
     *     currency_symbol: string|null,
     *     image: string|null,
     *     images: list<array<string, mixed>>,
     *     configurator: array{groups: list<array<string, mixed>>}|null,
     *     shop_name: string,
     *     shop_url: string|null,
     *     url: string|null
     * }
     */
    private function transform(Team $team, Product $product): array
    {
        $image = $this->publicImageUrl($product->image);
        $images = app(ProductImageService::class)->variantsForUrl(is_string($product->image) ? $product->image : null);
        $categoryName = $product->category?->name;
        $code = (string) ($product->code ?? '');
        $slug = Str::slug((string) $product->name) ?: Str::slug($code) ?: $code;
        $showsPrice = $product->catalogShowsPrice();

        return [
            'name' => (string) $product->name,
            'code' => $code,
            'slug' => $slug,
            'brand' => $product->brand?->name,
            'category' => $categoryName,
            'category_slug' => $categoryName ? (Str::slug($categoryName) ?: null) : null,
            'description' => $product->description ? trim(strip_tags((string) $product->description)) : null,
            'short_description' => $product->short_description ? trim(strip_tags((string) $product->short_description)) : null,
            'price' => $this->priceLine($product),
            'price_amount' => $showsPrice ? (float) $product->currentSellingPrice() : null,
            'compare_at_price' => $this->compareAtPriceLine($product),
            'compare_at_price_amount' => $showsPrice && $product->isOnSale() ? (float) $product->price : null,
            'on_sale' => $showsPrice && $product->isOnSale(),
            'currency_symbol' => $showsPrice ? ($product->currency?->symbol ?? '$') : null,
            'image' => $image,
            'images' => $images,
            'options' => $this->publicOptions($product),
            'configurator' => $this->normalizeConfigurator($product->configurator),
            'is_featured' => (bool) $product->is_featured,
            'shop_name' => $this->shopName($team),
            'shop_url' => $team->publicCatalogShopUrl(),
            'url' => $team->publicCatalogProductUrl($code),
        ];
    }

    /**
     * @return list<array{name: string, values: list<string>}>
     */
    private function publicOptions(Product $product): array
    {
        $options = [];
        foreach ($product->options as $option)
        {
            $values = $option->values
                ->sortBy('position')
                ->map(fn ($value): string => trim((string) $value->value))
                ->filter(fn (string $value): bool => $value !== '')
                ->values()
                ->all();
            if ($values === [])
            {
                continue;
            }
            $options[] = [
                'name' => (string) $option->name,
                'values' => $values,
            ];
        }

        return $options;
    }

    /**
     * @return array{groups: list<array<string, mixed>>}|null
     */
    private function normalizeConfigurator(mixed $configurator): ?array
    {
        if (! is_array($configurator))
        {
            return null;
        }

        $groups = $configurator['groups'] ?? null;
        if (! is_array($groups) || $groups === [])
        {
            return null;
        }

        $normalized = [];
        foreach ($groups as $group)
        {
            if (! is_array($group))
            {
                continue;
            }

            $choices = [];
            foreach (($group['choices'] ?? []) as $choice)
            {
                if (! is_array($choice))
                {
                    continue;
                }

                $choiceId = trim((string) ($choice['id'] ?? $choice['name'] ?? ''));
                $choiceName = trim((string) ($choice['name'] ?? ''));
                if ($choiceId === '' || $choiceName === '')
                {
                    continue;
                }

                $choices[] = [
                    'id' => $choiceId,
                    'name' => $choiceName,
                    'price' => isset($choice['price']) ? (float) $choice['price'] : 0.0,
                    'units' => isset($choice['units']) ? (int) $choice['units'] : null,
                    'label' => isset($choice['label']) ? trim((string) $choice['label']) : null,
                ];
            }

            if ($choices === [])
            {
                continue;
            }

            $type = strtolower(trim((string) ($group['type'] ?? 'single')));
            if (! in_array($type, ['single', 'quantity'], true))
            {
                $type = 'single';
            }

            $groupId = trim((string) ($group['id'] ?? $group['name'] ?? ''));
            $groupName = trim((string) ($group['name'] ?? ''));
            if ($groupId === '' || $groupName === '')
            {
                continue;
            }

            $normalized[] = [
                'id' => $groupId,
                'name' => $groupName,
                'type' => $type,
                'max' => max(1, (int) ($group['max'] ?? 1)),
                'min' => max(0, (int) ($group['min'] ?? 0)),
                'required' => (bool) ($group['required'] ?? ($type === 'single')),
                'choices' => $choices,
            ];
        }

        return $normalized === [] ? null : ['groups' => $normalized];
    }

    private function shopName(Team $team): string
    {
        $businessName = $team->getDecodedBusinessConfig()['business_name'] ?? null;
        $shopName = trim((string) ((is_string($businessName) && trim($businessName) !== '') ? $businessName : $team->name));

        return $shopName !== '' ? $shopName : $team->name;
    }

    private function shopNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Shop not found.'),
        ], 404);
    }

    private function priceLine(Product $product): ?string
    {
        if (! $product->catalogShowsPrice())
        {
            return null;
        }

        $symbol = $product->currency?->symbol ?? '$';

        return $symbol.number_format($product->currentSellingPrice(), 2, ',', '.');
    }

    private function compareAtPriceLine(Product $product): ?string
    {
        if (! $product->catalogShowsPrice() || ! $product->isOnSale())
        {
            return null;
        }

        $symbol = $product->currency?->symbol ?? '$';

        return $symbol.number_format((float) $product->price, 2, ',', '.');
    }

    private function publicImageUrl(mixed $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '')
        {
            return null;
        }

        if (preg_match('#^https?://#i', $image) === 1)
        {
            return $image;
        }

        $path = ltrim($image, '/');
        if (str_starts_with($path, 'storage/'))
        {
            return url($path);
        }

        return url('storage/'.$path);
    }
}
