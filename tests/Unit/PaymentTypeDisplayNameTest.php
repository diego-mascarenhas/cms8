<?php

namespace Tests\Unit;

use App\Models\PaymentType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentTypeDisplayNameTest extends TestCase
{
    #[DataProvider('translatedPaymentTypeNamesProvider')]
    public function test_display_name_uses_spanish_translation_when_available(string $englishName, string $spanishName): void
    {
        $type = new PaymentType(['name' => $englishName]);

        $this->assertSame($spanishName, $type->display_name);
    }

    #[DataProvider('untranslatedPaymentTypeNamesProvider')]
    public function test_display_name_keeps_original_name_for_brands_without_translation(string $name): void
    {
        $type = new PaymentType(['name' => $name]);

        $this->assertSame($name, $type->display_name);
    }

    public static function translatedPaymentTypeNamesProvider(): array
    {
        return [
            ['Cash', 'Efectivo'],
            ['Bank Transfer', 'Transferencia bancaria'],
            ['Bank Deposit', 'Ingreso bancario'],
            ['Check', 'Cheque'],
            ['Debit', 'Tarjeta de débito'],
            ['Credit Card', 'Tarjeta de crédito'],
            ['Wise Transfer', 'Transferencia Wise'],
            ['Cryptocurrency', 'Criptomoneda'],
        ];
    }

    public static function untranslatedPaymentTypeNamesProvider(): array
    {
        return [
            ['PayPal'],
            ['Stripe'],
            ['Bizum'],
            ['MercadoPago'],
            ['Cuéntica'],
        ];
    }
}
