<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceInboundSyncButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
        ]);
    }

    public function test_index_shows_sync_button_when_cuentica_is_configured(): void
    {
        $user = $this->userWithInvoiceAccess();
        $team = $user->currentTeam;
        $team->setSetting('cuentica_api_token', 'test-token');
        $team->setSetting('cuentica_inbound_sync_enabled', true);

        $this->actingAs($user)
            ->get(route('invoice.index'))
            ->assertOk()
            ->assertSee(__('invoice_sync.sync_button'), false)
            ->assertSee(route('invoice.sync-inbound'), false);
    }

    public function test_index_hides_sync_button_without_providers(): void
    {
        $user = $this->userWithInvoiceAccess();

        $this->actingAs($user)
            ->get(route('invoice.index'))
            ->assertOk()
            ->assertDontSee(__('invoice_sync.sync_button'), false);
    }

    public function test_sync_inbound_runs_cuentica_commands(): void
    {
        $user = $this->userWithInvoiceAccess();
        $team = $user->currentTeam;
        $team->setSetting('cuentica_api_token', 'test-token');
        $team->setSetting('cuentica_inbound_sync_enabled', true);

        Http::fake(function (Request $request)
        {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'GET' && in_array($path, ['/invoice', '/expense'], true))
            {
                return Http::response([], 200);
            }

            return Http::response([], 200);
        });

        $this->actingAs($user)
            ->post(route('invoice.sync-inbound'))
            ->assertRedirect(route('invoice.index'))
            ->assertSessionHas('success');
    }

    private function userWithInvoiceAccess(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
