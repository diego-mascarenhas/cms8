<?php

namespace App\Models;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'barcode',
        'oem',
        'description',
        'short_description',
        'price',
        'sale_price',
        'currency_id',
        'store_id',
        'available_in_all_stores',
        'category_id',
        'brand_id',
        'status',
        'catalog_status',
        'stock_status',
        'manage_stock',
        'stock_quantity',
        'assortment_size',
        'whatsapp_enabled',
        'team_id',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'status' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'available_in_all_stores' => 'boolean',
        'manage_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'assortment_size' => 'integer',
        'catalog_status' => ProductCatalogStatus::class,
        'stock_status' => ProductStockStatus::class,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::saving(function (Product $product): void
        {
            if ($product->catalog_status === null)
            {
                $product->catalog_status = $product->status
                    ? ProductCatalogStatus::Publish
                    : ProductCatalogStatus::Draft;
            }

            $product->status = $product->catalog_status === ProductCatalogStatus::Publish;
        });
    }

    /**
     * Price used for cart and listings (sale when valid, otherwise regular).
     */
    public function currentSellingPrice(): float
    {
        $variant = $this->defaultVariant();
        if ($variant)
        {
            return $variant->currentSellingPrice();
        }

        $regular = (float) $this->price;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;
        if ($sale !== null && $sale > 0 && $sale < $regular)
        {
            return $sale;
        }

        return $regular;
    }

    public function isOnSale(): bool
    {
        $regular = (float) $this->price;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;

        return $sale !== null && $sale > 0 && $sale < $regular;
    }

    /**
     * Empty barcode / OEM values must be null so unique indexes do not collide.
     */
    public static function normalizeOptionalIdentifier(mixed $value): ?string
    {
        if (! is_scalar($value))
        {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the currency that owns the product.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the store that owns the product.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'product_store')
            ->withoutGlobalScope('team')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    /**
     * Products sold in every branch, or explicitly assigned to this one.
     */
    public function scopeAvailableAt(Builder $query, int $storeId): Builder
    {
        return $query->where(function (Builder $builder) use ($storeId)
        {
            $builder->where('available_in_all_stores', true)
                ->orWhereHas('stores', function (Builder $stores) use ($storeId)
                {
                    $stores->where('stores.id', $storeId);
                })
                ->orWhere(function (Builder $legacy) use ($storeId)
                {
                    $legacy->where('available_in_all_stores', false)
                        ->whereDoesntHave('stores')
                        ->where('store_id', $storeId);
                });
        });
    }

    public function isAvailableAt(int $storeId): bool
    {
        if ($this->available_in_all_stores)
        {
            return true;
        }

        $this->loadMissing('stores');

        if ($this->stores->contains('id', $storeId))
        {
            return true;
        }

        return (int) $this->store_id === $storeId;
    }

    /**
     * @return list<int>
     */
    public function availableStoreIds(): array
    {
        if ($this->available_in_all_stores)
        {
            return [];
        }

        $this->loadMissing('stores');
        $ids = $this->stores->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        if ($ids === [] && $this->store_id)
        {
            return [(int) $this->store_id];
        }

        return $ids;
    }

    /**
     * @param  list<int>  $storeIds
     */
    public function syncStoreAvailability(bool $availableInAllStores, array $storeIds = []): void
    {
        $ids = collect($storeIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $this->available_in_all_stores = $availableInAllStores;
        $this->store_id = $availableInAllStores
            ? ($this->store_id ?: $this->defaultPrimaryStoreId())
            : ($ids->first() ?: null);
        $this->save();

        if ($availableInAllStores)
        {
            $this->stores()->sync([]);

            return;
        }

        $this->stores()->sync($ids->mapWithKeys(fn (int $id): array => [
            $id => ['team_id' => $this->team_id],
        ])->all());
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function syncStoreAvailabilityFromValidated(array $validated, bool $creating = false): void
    {
        $hasFlag = array_key_exists('available_in_all_stores', $validated);
        $hasIds = array_key_exists('store_ids', $validated);

        if (! $hasFlag && ! $hasIds)
        {
            if ($creating)
            {
                $this->syncStoreAvailability(true, []);

                return;
            }

            if (array_key_exists('store_id', $validated))
            {
                $this->store_id = $validated['store_id'] !== null && $validated['store_id'] !== ''
                    ? (int) $validated['store_id']
                    : null;
                $this->save();
            }

            return;
        }

        [$all, $ids] = self::availabilityFromValidated($validated);
        $this->syncStoreAvailability($all, $ids);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: bool, 1: list<int>}
     */
    public static function availabilityFromValidated(array $validated): array
    {
        $ids = collect($validated['store_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $hasFlag = array_key_exists('available_in_all_stores', $validated);
        $all = $hasFlag
            ? (bool) (int) $validated['available_in_all_stores']
            : $ids === [];

        if (! $all && $ids === [] && isset($validated['store_id']) && $validated['store_id'] !== '' && $validated['store_id'] !== null)
        {
            $ids = [(int) $validated['store_id']];
        }

        return [$all, $ids];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeAvailabilityValidationRules(int $teamId): array
    {
        return [
            'available_in_all_stores' => ['nullable', Rule::in([0, 1, '0', '1', true, false])],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => [
                'integer',
                Rule::exists('stores', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    public static function validateStoreAvailability(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $data = $validator->getData();
            $flag = $data['available_in_all_stores'] ?? null;
            if ($flag === null || (bool) (int) $flag)
            {
                return;
            }

            $ids = collect($data['store_ids'] ?? [])
                ->filter(fn ($id): bool => $id !== null && $id !== '')
                ->all();
            $storeId = $data['store_id'] ?? null;

            if ($ids === [] && ($storeId === null || $storeId === ''))
            {
                $validator->errors()->add('store_ids', __('Select at least one store.'));
            }
        });
    }

    public function availabilityLabel(): string
    {
        if ($this->available_in_all_stores)
        {
            return __('All stores');
        }

        $this->loadMissing('stores');
        $names = $this->stores->pluck('name')->filter()->values();
        if ($names->isEmpty() && $this->store)
        {
            return (string) $this->store->name;
        }

        return $names->implode(', ') ?: __('All stores');
    }

    /**
     * Whether customer-facing catalog (shop / WhatsApp) may quote this product's price.
     * Follows the store «Mostrar precios» flag; unassigned products use the team's main store.
     */
    public function catalogShowsPrice(): bool
    {
        $this->loadMissing(['store', 'stores']);

        $stores = collect();
        if ($this->store)
        {
            $stores->push($this->store);
        }
        $stores = $stores->concat($this->stores)->unique('id')->values();

        if ($this->available_in_all_stores || $stores->isEmpty())
        {
            $teamStore = Store::withoutGlobalScope('team')
                ->where('team_id', (int) $this->team_id)
                ->where('status', true)
                ->orderByDesc('is_main')
                ->orderBy('id')
                ->first();

            return $teamStore ? $teamStore->showsPrices() : true;
        }

        return $stores->every(fn (Store $store): bool => $store->showsPrices());
    }

    private function defaultPrimaryStoreId(): ?int
    {
        $teamId = (int) $this->team_id;
        if ($teamId === 0)
        {
            return null;
        }

        return Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->value('id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class)->orderBy('position')->orderBy('name');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function defaultVariant(): ?ProductVariant
    {
        $this->loadMissing('variants');

        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    /**
     * @return list<string>
     */
    public function optionValuesNamed(string $name): array
    {
        $this->loadMissing('options.values');
        $needle = mb_strtolower($name);
        $option = $this->options->first(
            fn (ProductOption $option): bool => mb_strtolower($option->name) === $needle,
        );

        return $option?->values->pluck('value')->values()->all() ?? [];
    }

    /**
     * Get the team that owns the product.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to only include WhatsApp enabled products.
     */
    public function scopeWhatsAppEnabled($query)
    {
        return $query->where('whatsapp_enabled', true);
    }
}
