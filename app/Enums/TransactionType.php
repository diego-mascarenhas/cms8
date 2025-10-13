<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match ($this)
        {
            self::INCOME => __('Income'),
            self::EXPENSE => __('Expense'),
        };
    }

    public function color(): string
    {
        return match ($this)
        {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
        };
    }

    public function badge(): string
    {
        return match ($this)
        {
            self::INCOME => '<span class="badge rounded-circle bg-success" style="width:10px;height:10px;padding:0;display:inline-block;"></span>',
            self::EXPENSE => '<span class="badge rounded-circle bg-danger" style="width:10px;height:10px;padding:0;display:inline-block;"></span>',
        };
    }
}
