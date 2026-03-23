<?php

namespace App\Enums;

enum ProductStockStatus: string
{
    case InStock = 'instock';
    case OutOfStock = 'outofstock';
    case OnBackorder = 'onbackorder';

    public function label(): string
    {
        return match ($this)
        {
            self::InStock => __('In stock'),
            self::OutOfStock => __('Out of stock'),
            self::OnBackorder => __('On backorder'),
        };
    }
}
