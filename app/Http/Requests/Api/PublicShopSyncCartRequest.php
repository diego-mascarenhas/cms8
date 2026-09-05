<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PublicShopSyncCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'guest_id' => ['required', 'string', 'uuid'],
            'items' => ['required', 'array', 'max:100'],
            'items.*.code' => ['required', 'string', 'max:120'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.detail' => ['nullable', 'string', 'max:500'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
