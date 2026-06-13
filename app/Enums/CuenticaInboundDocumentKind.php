<?php

namespace App\Enums;

enum CuenticaInboundDocumentKind: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';

    public function billingReason(): string
    {
        return match ($this)
        {
            self::Sale => 'cuentica_sale',
            self::Purchase => 'cuentica_purchase',
        };
    }

    public function operation(): string
    {
        return match ($this)
        {
            self::Sale => 'sell',
            self::Purchase => 'buy',
        };
    }

    public function enterpriseTypeId(): int
    {
        return match ($this)
        {
            self::Sale => 1,
            self::Purchase => 2,
        };
    }

    public function counterpartyKey(): string
    {
        return match ($this)
        {
            self::Sale => 'customer',
            self::Purchase => 'provider',
        };
    }

    public function externalId(int|string $cuenticaId): string
    {
        return $this->value.':'.$cuenticaId;
    }

    public static function fromBillingReason(?string $billingReason): ?self
    {
        return match ($billingReason)
        {
            'cuentica_sale' => self::Sale,
            'cuentica_purchase' => self::Purchase,
            default => null,
        };
    }

    public static function fromExternalId(string $externalId): ?self
    {
        if (str_starts_with($externalId, self::Sale->value.':'))
        {
            return self::Sale;
        }

        if (str_starts_with($externalId, self::Purchase->value.':'))
        {
            return self::Purchase;
        }

        return null;
    }
}
