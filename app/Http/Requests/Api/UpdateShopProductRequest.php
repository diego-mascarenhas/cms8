<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\UpdateLocalProductRequest;

class UpdateShopProductRequest extends UpdateLocalProductRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'manage_stock' => $this->toFlag('manage_stock'),
            'whatsapp_enabled' => $this->toFlag('whatsapp_enabled'),
        ]);
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
