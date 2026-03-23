<?php

namespace App\Enums;

enum ProductCatalogStatus: string
{
    case Publish = 'publish';
    case Draft = 'draft';
    case Pending = 'pending';
    case Private = 'private';

    public function label(): string
    {
        return match ($this)
        {
            self::Publish => __('Published'),
            self::Draft => __('Draft'),
            self::Pending => __('Pending'),
            self::Private => __('Private'),
        };
    }
}
