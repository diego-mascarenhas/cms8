<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavbarGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_navbar_uses_alpine_global_search_markup(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('x-data="globalSearch()"', false);
        $response->assertSee('search-results-anchor', false);
        $response->assertSee('search-toggler search-close cursor-pointer', false);
    }

    public function test_main_js_skips_jquery_search_when_alpine_global_search_is_present(): void
    {
        $mainJs = file_get_contents(base_path('resources/assets/js/main.js'));

        $this->assertNotFalse($mainJs);
        $this->assertStringContainsString('usesAlpineGlobalSearch', $mainJs);
        $this->assertStringContainsString("indexOf('globalSearch')", $mainJs);
    }
}
