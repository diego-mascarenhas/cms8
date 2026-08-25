<?php

namespace Tests\Unit;

use App\Helpers\WhatsAppNaturalCartPhrase;
use PHPUnit\Framework\TestCase;

class WhatsAppNaturalCartPhraseTest extends TestCase
{
    public function test_view_cart_intent_is_discovered_beyond_the_command(): void
    {
        $this->assertTrue(WhatsAppNaturalCartPhrase::isViewCart('Ver carrito'));
        $this->assertTrue(WhatsAppNaturalCartPhrase::isViewCart('Quiero ver mi carrito'));
        $this->assertTrue(WhatsAppNaturalCartPhrase::isViewCart('Puedo ver lo que tengo en el carrito?'));
        $this->assertTrue(WhatsAppNaturalCartPhrase::isViewCart('Qué hay en el carrito'));
        $this->assertTrue(WhatsAppNaturalCartPhrase::isViewCart('carrito'));
        $this->assertFalse(WhatsAppNaturalCartPhrase::isViewCart('Agregar 3 vestidos al carrito'));
        $this->assertFalse(WhatsAppNaturalCartPhrase::isViewCart('vaciar carrito'));
        $this->assertFalse(WhatsAppNaturalCartPhrase::isViewCart('Agregar cita para hoy'));
    }

    public function test_agregame_two_is_a_quantity_only_add(): void
    {
        $this->assertSame(['quantity' => 2], WhatsAppNaturalCartPhrase::quantityOnlyAdd('Agregame 2'));
    }

    public function test_agregalo_defaults_to_one(): void
    {
        $this->assertSame(['quantity' => 1], WhatsAppNaturalCartPhrase::quantityOnlyAdd('agregalo'));
    }

    public function test_poneme_three_units(): void
    {
        $this->assertSame(['quantity' => 3], WhatsAppNaturalCartPhrase::quantityOnlyAdd('Poneme 3 unidades'));
    }

    public function test_appointment_phrase_is_not_a_cart_add(): void
    {
        $this->assertNull(WhatsAppNaturalCartPhrase::quantityOnlyAdd('Agregar cita para hoy a las 15 hs'));
    }

    public function test_agregar_with_product_name_is_not_quantity_only(): void
    {
        $this->assertNull(WhatsAppNaturalCartPhrase::quantityOnlyAdd('agregar 3 vestidos iguales al carrito'));
    }

    public function test_buy_command_parses_name_quantity_code_and_last_product(): void
    {
        $this->assertSame(
            ['quantity' => 1, 'needle' => 'abrazadera 16 x 27'],
            WhatsAppNaturalCartPhrase::buyCommand('comprar abrazadera 16 x 27'),
        );
        $this->assertSame(
            ['quantity' => 1, 'needle' => '21861'],
            WhatsAppNaturalCartPhrase::buyCommand('Comprar producto 21861'),
        );
        $this->assertSame(
            ['quantity' => 2, 'needle' => ''],
            WhatsAppNaturalCartPhrase::buyCommand('Comprar 2 de estas unidades'),
        );
        $this->assertSame(
            ['quantity' => 2, 'needle' => 'abrazadera 16 x 27'],
            WhatsAppNaturalCartPhrase::buyCommand('comprar 2 abrazadera 16 x 27'),
        );
        $this->assertSame(
            ['quantity' => 2, 'needle' => 'abrazadera 8 x 16'],
            WhatsAppNaturalCartPhrase::addToCartCommand('agregame dos ABRAZADERA 8 X 16'),
        );
        $this->assertSame(
            ['quantity' => 2, 'needle' => 'abraz de 8'],
            WhatsAppNaturalCartPhrase::addToCartCommand('Agregame 2 abraz de 8'),
        );
        $this->assertSame(
            ['quantity' => 2, 'needle' => 'abrazadera 8 x 16'],
            WhatsAppNaturalCartPhrase::buyCommand('Comprar  2 ABRAZADERA 8 X 16 a $989.43 c/u'),
        );
        $this->assertNull(WhatsAppNaturalCartPhrase::buyCommand('comprar todo'));
        $this->assertNull(WhatsAppNaturalCartPhrase::addToCartCommand('Agregar cita para hoy a las 15 hs'));
    }
}
