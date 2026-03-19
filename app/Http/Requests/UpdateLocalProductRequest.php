<?php

namespace App\Http\Requests;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Module;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateLocalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = Product::query()->find($this->route('id'));

        if (! $product)
        {
            return false;
        }

        return $this->user()?->can('update', $product) ?? false;
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
            'code' => ['required', 'string', 'max:64'],
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
            'size_options' => ['nullable', 'array'],
            'size_options.*' => ['string', 'max:50'],
            'color_options' => ['nullable', 'array'],
            'color_options.*' => ['string', 'max:50'],
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
                    $exists = DB::table('categories')
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
        $teamId = (int) ($this->user()?->current_team_id ?? 0);
        $rules = self::rulesForTeam($teamId);

        $rules['code'][] = Rule::unique('products', 'code')
            ->ignore($this->route('id'))
            ->where('team_id', $teamId);

        return $rules;
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
        ];
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
