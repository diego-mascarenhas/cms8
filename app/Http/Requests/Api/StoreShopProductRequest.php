<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreLocalProductRequest;

class StoreShopProductRequest extends StoreLocalProductRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $merged = [
            'manage_stock' => $this->toFlag('manage_stock'),
            'whatsapp_enabled' => $this->toFlag('whatsapp_enabled'),
        ];

        if ($this->exists('available_in_all_stores'))
        {
            $merged['available_in_all_stores'] = $this->toFlag('available_in_all_stores');
        }

        $this->merge($merged);
    }

    private function toFlag(string $key): int
    {
        $value = $this->input($key);

        if ($value === true || $value === 1 || $value === '1')
        {
            return 1;
        }

        return 0;
    }
}
