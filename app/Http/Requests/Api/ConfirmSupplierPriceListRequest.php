<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmSupplierPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->user()?->current_team_id ?? 0);

        return [
            'enterprise_id' => [
                'nullable',
                'integer',
                Rule::exists('enterprises', 'id')->where(function ($query) use ($teamId)
                {
                    $query->where('team_id', $teamId)->where('type_id', 2)->whereNull('deleted_at');
                }),
            ],
            'supplier_name' => ['nullable', 'string', 'max:75'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('status', 1)],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'name' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:2000'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.cost' => ['required', 'numeric', 'min:0'],
            'lines.*.code' => ['nullable', 'string', 'max:64'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.barcode' => ['nullable', 'string', 'max:64'],
            'lines.*.oem' => ['nullable', 'string', 'max:80'],
        ];
    }
}
