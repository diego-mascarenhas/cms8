<?php

namespace Tests\Unit;

use App\Models\ServiceSync;
use Tests\TestCase;

class ClientFacingNameTest extends TestCase
{
    public function test_a_product_without_a_description_uses_the_product_name(): void
    {
        $sync = new ServiceSync([
            'stripe_id' => 'sub_assistant',
            'plan_name' => null,
            'raw_payload' => [
                'description' => null,
                'metadata' => [
                    'subscription_type' => 'assistant',
                    'plan' => 'assistant',
                ],
            ],
        ]);

        $this->assertSame('Assistant', $sync->clientFacingName());
    }

    public function test_hosting_keeps_the_plan_product_and_the_domain(): void
    {
        $sync = new ServiceSync([
            'stripe_id' => 'sub_hosting',
            'plan_name' => 'Hosting REPUESTOSAV.COM',
            'raw_payload' => [
                'description' => 'Hosting REPUESTOSAV.COM',
                'items' => [
                    'data' => [[
                        'price' => [
                            'product' => [
                                'id' => 'prod_cloud',
                                'name' => 'Cloud Starter',
                            ],
                        ],
                    ]],
                ],
            ],
        ]);

        $this->assertSame('Cloud Starter · REPUESTOSAV.COM', $sync->clientFacingName());
        $this->assertTrue($sync->isClientHosting());
        $this->assertSame('Hosting', $sync->clientCategoryLabel());
    }

    public function test_a_renamed_price_nickname_does_not_replace_the_description(): void
    {
        $sync = new ServiceSync([
            'stripe_id' => 'sub_domain',
            'plan_name' => 'Actualizado 12/2025',
            'raw_payload' => [
                'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        $this->assertSame('Dominio CLEANUPBUENOSAIRES.COM', $sync->clientFacingName());
    }
}
