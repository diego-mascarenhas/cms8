<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactListEnglishLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_list_stats_and_toolbar_render_in_english(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Permission::firstOrCreate(['name' => 'contact.index', 'guard_name' => 'web']);
        $user->givePermissionTo('contact.index');

        app()->setLocale('en');
        session()->put('locale', 'en');

        $response = $this->actingAs($user)->get(route('contact-list'));

        $response->assertOk();
        $response->assertSee(__('app.contact_stats_leads_total'), false);
        $response->assertSee(__('app.contact_stats_follow_up'), false);
        $response->assertSee(__('app.contact_stats_clients_total'), false);
        $response->assertSee(__('app.contact_stats_finished'), false);
        $response->assertSee(__('app.contact_add'), false);
        $response->assertSee(__('app.contact_import'), false);
        $response->assertDontSee('Total de leads', false);
        $response->assertDontSee('Añadir contacto', false);
    }
}
