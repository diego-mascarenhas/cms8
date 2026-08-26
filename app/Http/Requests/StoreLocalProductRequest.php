<?php

namespace App\Http\Requests;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        $sale = $this->input('sale_price');
        if ($sale === '' || $sale === null || (is_numeric($sale) && (float) $sale <= 0))
        {
            $data['sale_price'] = null;
        }

        if ((string) $this->input('manage_stock') === '0')
        {
            $data['stock_quantity'] = null;
        }

        $image = $this->input('image');
        if ($image === null || $image === '' || (is_string($image) && trim($image) === ''))
        {
            $data['image'] = null;
        } elseif (is_string($image))
        {
            $data['image'] = trim($image);
        }

        $data['code'] = strtoupper(trim((string) ($this->input('code') ?? '')));
        $data['size_options'] = $this->parseCsvOptions($this->input('size_options'));
        $data['color_options'] = $this->parseCsvOptions($this->input('color_options'));

        if ($data !== [])
        {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesForTeam(int $teamId): array
    {
        $moduleId = Module::query()->where('key', 'products')->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique('products', 'code')->where('team_id', $teamId)],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'lte:price'],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('status', 1),
            ],
            'store_id' => [
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('team_id', $teamId),
            ],
            ...Product::storeAvailabilityValidationRules($teamId),
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('team_id', $teamId),
            ],
            'assortment_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            'size_options' => ['nullable', 'array'],
            'size_options.*' => ['string', 'max:50'],
            'color_options' => ['nullable', 'array'],
            'color_options.*' => ['string', 'max:50'],
            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['required_with:options', 'string', 'max:80'],
            'options.*.values' => ['required_with:options', 'array', 'min:1'],
            'options.*.values.*' => ['string', 'max:80'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.stock_status' => ['nullable', Rule::enum(ProductStockStatus::class)],
            'variants.*.manage_stock' => ['nullable', Rule::in([0, 1, '0', '1', true, false])],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.option_values' => ['nullable', 'array'],
            'variants.*.option_values.*' => ['string', 'max:80'],
            'category_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($teamId, $moduleId): void
                {
                    if ($moduleId === null)
                    {
                        $fail(__('The products module is not available.'));

                        return;
                    }
                    $exists = Category::query()
                        ->where('id', $value)
                        ->where('module_id', $moduleId)
                        ->whereNull('deleted_at')
                        ->where(function ($query) use ($teamId)
                        {
                            $query->whereNull('team_id')
                                ->orWhere('team_id', $teamId);
                        })
                        ->exists();
                    if (! $exists)
                    {
                        $fail(__('The selected category is invalid.'));
                    }
                },
            ],
            'catalog_status' => ['required', Rule::enum(ProductCatalogStatus::class)],
            'stock_status' => ['required', Rule::enum(ProductStockStatus::class)],
            'manage_stock' => ['required', Rule::in([0, 1, '0', '1'])],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'required_if:manage_stock,1'],
            'whatsapp_enabled' => ['required', Rule::in([0, 1, '0', '1'])],
            'image' => ['nullable', 'string', 'max:2048', 'url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->current_team_id;

        return self::rulesForTeam((int) $teamId);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The name is required.'),
            'code.required' => __('The code is required.'),
            'code.unique' => __('The code has already been used in this team.'),
            'price.required' => __('The price is required.'),
            'currency_id.required' => __('Please select a currency.'),
            'category_id.required' => __('Please select a category.'),
            'sale_price.lte' => __('Sale price must be less than or equal to regular price.'),
            'store_ids.required' => __('Select at least one store.'),
        ];
    }

    public function withValidator($validator): void
    {
        Product::validateStoreAvailability($validator);
    }

    /**
     * @return array<int, string>
     */
    protected function parseCsvOptions(mixed $value): array
    {
        if (is_array($value))
        {
            return collect($value)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (! is_string($value) || trim($value) === '')
        {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
