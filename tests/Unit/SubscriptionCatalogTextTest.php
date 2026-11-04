<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Services\Billing\SubscriptionCatalogText;
use Tests\TestCase;

class SubscriptionCatalogTextTest extends TestCase
{
    public function test_groups_subscriptions_and_shows_the_metadata_domain(): void
    {
        $hosting = new ServiceSync([
            'stripe_id' => 'sub_host',
            'status' => 'active',
            'plan_name' => 'Revision Cloud',
            'price_currency' => 'EUR',
            'amount_total' => 115.2,
            'plan_interval' => 'month',
            'data' => ['category' => 'hosting'],
            'raw_payload' => ['metadata' => ['domain' => 'grupogremond.com']],
        ]);
        $domain = new ServiceSync([
            'stripe_id' => 'sub_domain',
            'status' => 'active',
            'plan_name' => 'Renovación anual',
            'price_currency' => 'EUR',
            'amount_total' => 24,
            'plan_interval' => 'year',
            'raw_payload' => ['metadata' => ['domain' => 'gremond.com', 'category' => 'dominio']],
        ]);

        $text = app(SubscriptionCatalogText::class)->grouped(collect([$domain, $hosting]));

        $this->assertStringContainsString("Hosting\n- Revision Cloud · grupogremond.com · activa · 115,20 EUR · Mensual", $text);
        $this->assertStringContainsString("Dominios\n- Renovación anual · gremond.com · activa · 24,00 EUR · Anual", $text);
        $this->assertLessThan(strpos($text, 'Dominios'), strpos($text, 'Hosting'));
    }

    public function test_a_service_without_a_name_uses_the_domain_and_skips_a_zero_price(): void
    {
        $blank = new Service([
            'description' => '',
            'price' => 0,
            'status' => 4,
            'data' => ['domain' => 'tiendagremond.com'],
            'next_billing' => '2026-11-01',
        ]);
        $blank->setRelation('category', new Category(['name' => 'Hosting']));
        $empty = new Service([
            'description' => '',
            'price' => 0,
            'data' => [],
        ]);
        $empty->setRelation('category', new Category(['name' => 'Hosting']));

        $text = app(SubscriptionCatalogText::class)->groupedServices(collect([$blank, $empty]));

        $this->assertStringContainsString("Hosting\n- tiendagremond.com · próxima 01/11/2026", $text);
        $this->assertStringNotContainsString('0,00', $text);
        $this->assertSame(1, substr_count($text, 'tiendagremond.com'));
    }
}
