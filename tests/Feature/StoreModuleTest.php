<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_list_displays_team_stores(): void
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
            'status' => true,
            'is_main' => true,
        ]);

        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Tienda Centro',
            'code' => 'CENTRO',
            'address' => 'Av. Centro 123',
            'status' => true,
            'is_main' => false,
        ]);

        $response = $this->actingAs($user)->get(route('store.index'));

        $response->assertOk();
        $response->assertSee('Principal');
        $response->assertSee('Tienda Centro');
    }

    public function test_store_can_be_created(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->post(route('store.store'), [
            'name' => 'Tienda Palermo',
            'code' => 'PALERMO',
            'address' => 'Calle Palermo 456',
            'status' => '1',
            'is_main' => '0',
            'checkout_payment_methods' => [Store::CHECKOUT_PAYMENT_CASH, Store::CHECKOUT_PAYMENT_CARD],
            'checkout_fulfillment_types' => [Store::CHECKOUT_FULFILLMENT_PICKUP],
        ]);

        $response->assertRedirect(route('store.index'));
        $this->assertDatabaseHas('stores', [
            'team_id' => $team->id,
            'name' => 'Tienda Palermo',
            'code' => 'PALERMO',
        ]);

        $created = Store::withoutGlobalScope('team')->where('code', 'PALERMO')->where('team_id', $team->id)->first();
        $this->assertNotNull($created);
        $this->assertSame(
            [Store::CHECKOUT_PAYMENT_CASH, Store::CHECKOUT_PAYMENT_CARD],
            data_get($created->data, 'checkout.payment_methods'),
        );
        $this->assertSame(
            [Store::CHECKOUT_FULFILLMENT_PICKUP],
            data_get($created->data, 'checkout.fulfillment_types'),
        );
    }

    public function test_store_delete_uses_soft_delete(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Tienda Delete',
            'code' => 'DEL',
            'status' => true,
            'is_main' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('store.destroy', $store->id));

        $response->assertRedirect(route('store.index'));
        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_store_show_page_is_accessible(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Tienda Show',
            'code' => 'SHOW',
            'status' => true,
            'is_main' => false,
        ]);

        $response = $this->actingAs($user)->get(route('store.show', $store->id));

        $response->assertOk();
        $response->assertSee('Tienda Show');
        $response->assertSee(__('Medios de pago'), false);
        $response->assertSee(__('Formas de entrega'), false);
    }

    public function test_store_update_redirects_to_show_detail(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Tienda Edit Redirect',
            'code' => 'EDRED',
            'status' => true,
            'is_main' => false,
            'data' => [
                'checkout' => [
                    'payment_methods' => [Store::CHECKOUT_PAYMENT_CASH],
                    'fulfillment_types' => [Store::CHECKOUT_FULFILLMENT_PICKUP],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('store.update', $store->id), [
            'name' => 'Tienda Edit Redirect',
            'code' => 'EDRED',
            'address' => 'Calle Nueva 1',
            'status' => '1',
            'is_main' => '0',
            'checkout_payment_methods' => [Store::CHECKOUT_PAYMENT_CASH, Store::CHECKOUT_PAYMENT_PAYPAL],
            'checkout_fulfillment_types' => [Store::CHECKOUT_FULFILLMENT_PICKUP, Store::CHECKOUT_FULFILLMENT_DELIVERY],
        ]);

        $response->assertRedirect(route('store.show', $store->id));
        $response->assertSessionHas('success');
    }
}
