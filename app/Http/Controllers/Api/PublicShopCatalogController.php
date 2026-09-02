<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductCatalogStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Team;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;

class PublicShopCatalogController extends Controller
{
    public function show(string $slug, string $code): JsonResponse
    {
        $team = Team::findForPublicCatalog($slug);
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('Shop not found.'),
            ], 404);
        }

        $normalized = mb_strtolower(trim($code));
        if ($normalized === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found.'),
            ], 404);
        }

        $product = Product::withoutGlobalScope('team')
            ->with(['brand', 'currency', 'category', 'store', 'stores'])
            ->where('team_id', $team->id)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->whereRaw('LOWER(code) = ?', [$normalized])
            ->first();

        if (! $product)
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transform($team, $product),
        ]);
    }

    /**
     * @return array{
     *     name: string,
     *     code: string,
     *     brand: string|null,
     *     category: string|null,
     *     description: string|null,
     *     short_description: string|null,
     *     price: string|null,
     *     image: string|null,
     *     images: list<array<string, mixed>>,
     *     shop_name: string,
     *     url: string|null
     * }
     */
    private function transform(Team $team, Product $product): array
    {
        $businessName = $team->getDecodedBusinessConfig()['business_name'] ?? null;
        $shopName = trim((string) ((is_string($businessName) && trim($businessName) !== '') ? $businessName : $team->name));
        $image = $this->publicImageUrl($product->image);
        $images = app(ProductImageService::class)->variantsForUrl(is_string($product->image) ? $product->image : null);

        return [
            'name' => (string) $product->name,
            'code' => (string) ($product->code ?? ''),
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'description' => $product->description ? trim(strip_tags((string) $product->description)) : null,
            'short_description' => $product->short_description ? trim(strip_tags((string) $product->short_description)) : null,
            'price' => $this->priceLine($product),
            'image' => $image,
            'images' => $images,
            'shop_name' => $shopName !== '' ? $shopName : $team->name,
            'url' => $team->publicCatalogProductUrl((string) $product->code),
        ];
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
