<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantStoreInfoToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_store_info_returns_hours_payments_delivery_and_notes(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'address' => 'Av. Centro 123',
            'status' => true,
            'is_main' => true,
            'data' => [
                'phone' => '1144556677',
                'notes' => 'Cerrado feriados nacionales',
                'show_prices' => false,
                'checkout' => [
                    'payment_methods' => [
                        Store::CHECKOUT_PAYMENT_CASH,
                        Store::CHECKOUT_PAYMENT_BANK_TRANSFER,
                    ],
                    'fulfillment_types' => [
                        Store::CHECKOUT_FULFILLMENT_PICKUP,
                        Store::CHECKOUT_FULFILLMENT_DELIVERY,
                    ],
                ],
                'hours' => [
                    ['day' => 'mon', 'open' => '09:00', 'close' => '13:00', 'afternoon_open' => '16:00', 'afternoon_close' => '20:00', 'closed' => false],
                    ['day' => 'tue', 'open' => '09:00', 'close' => '18:00', 'closed' => false],
                    ['day' => 'wed', 'open' => '09:00', 'close' => '18:00', 'closed' => false],
                    ['day' => 'thu', 'open' => '09:00', 'close' => '18:00', 'closed' => false],
                    ['day' => 'fri', 'open' => '09:00', 'close' => '18:00', 'closed' => false],
                    ['day' => 'sat', 'open' => '09:00', 'close' => '13:00', 'closed' => false],
                    ['day' => 'sun', 'closed' => true],
                ],
                'delivery' => [
                    'area' => 'CABA y GBA',
                    'notes' => 'Pedidos antes de las 11 salen el mismo día',
                    'cost' => 1500,
                ],
            ],
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $out = $service->execute('get_store_info', []);

        $this->assertStringContainsString('Principal', $out);
        $this->assertStringContainsString('Av. Centro 123', $out);
        $this->assertStringContainsString('09:00–13:00 and 16:00–20:00', $out);
        $this->assertStringContainsString('closed', mb_strtolower($out));
        $this->assertStringContainsString((string) Store::checkoutPaymentMethodLabels()[Store::CHECKOUT_PAYMENT_CASH], $out);
        $this->assertStringContainsString((string) Store::checkoutPaymentMethodLabels()[Store::CHECKOUT_PAYMENT_BANK_TRANSFER], $out);
        $this->assertStringContainsString((string) Store::checkoutFulfillmentLabels()[Store::CHECKOUT_FULFILLMENT_PICKUP], $out);
        $this->assertStringContainsString((string) Store::checkoutFulfillmentLabels()[Store::CHECKOUT_FULFILLMENT_DELIVERY], $out);
        $this->assertStringContainsString('CABA y GBA', $out);
        $this->assertStringContainsString('Cerrado feriados nacionales', $out);
        $this->assertStringContainsString('Show prices: no', $out);
        $this->assertStringContainsString('Do not say you lack hours', $out);
    }

    public function test_get_store_info_without_stores_does_not_invent_data(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id);

        $out = $service->execute('get_store_info', []);

        $this->assertStringContainsString('No stores are configured', $out);
        $this->assertStringContainsString('Do not invent', $out);
    }
}
