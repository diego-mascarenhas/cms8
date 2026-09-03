<?php

namespace Tests\Feature;

use App\Enums\TeamBillingFrequency;
use App\Enums\TeamBillingProduct;
use App\Models\Module;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TokenBillingRateService;
use App\Support\MailerPaygPricing;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountFormModuleGroupLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_and_support_group_titles_remain_english_when_locale_is_spanish(): void
    {
        app()->setLocale('es');

        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('account.edit', $team->id));

        $response->assertOk();
        $response->assertDontSee('id="module_accounting"', false);
        $response->assertDontSee('id="module_events"', false);
        $response->assertDontSee('Stripe billing', false);
        $response->assertDontSee('Events management module', false);
        $response->assertSee('<i class="ti ti-calculator me-2"></i>', false);
        $response->assertSee('Additional Modules', false);
        $response->assertSee('Accounting', false);
        $response->assertSee('Subscriptions, invoices, payments, affiliates and financial modules', false);
        $response->assertSee('Security', false);
        $response->assertSee('Support', false);
        $response->assertSee('Automation', false);
        $response->assertSee('Assistant instructions, funnel and API.', false);
        $response->assertSee('Marketing', false);
        $response->assertDontSee('Módulos adicionales', false);
        $response->assertDontSee('Seguridad', false);
    }

    public function test_account_update_preserves_hidden_modules_when_not_in_request(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $team->enableModule('accounting');
        $team->enableModule('events');
        $team->enableModule('invoices');

        $coreKeys = Module::query()->where('is_core', true)->pluck('key')->all();

        $this->actingAs($user)->put(route('account.update', $team->id), [
            'name' => $team->name,
            'modules' => array_merge($coreKeys, ['invoices']),
        ]);

        $team->refresh();
        $this->assertTrue($team->hasModule('accounting'));
        $this->assertTrue($team->hasModule('events'));
        $this->assertTrue($team->hasModule('invoices'));
    }

    public function test_account_edit_does_not_show_usage_rates(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('account.edit', $team->id))
            ->assertOk()
            ->assertDontSee('name="tokens_multiplier"', false)
            ->assertDontSee('Tarifas de consumo', false);
    }

    public function test_account_rates_page_shows_current_and_history(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        TeamBillingRate::factory()->create([
            'team_id' => $team->id,
            'product' => TeamBillingProduct::TokensMultiplier,
            'amount' => 10,
        ]);

        $this->fakeTokenCatalog();

        $this->actingAs($user)
            ->get(route('account.rates.edit', $team->id))
            ->assertOk()
            ->assertSee('Tarifas', false)
            ->assertSee('Preview de factura', false)
            ->assertSee(route('help.team-billing', [], false), false)
            ->assertSee('A facturar', false)
            ->assertSee('Tokens IA', false)
            ->assertSee('Envíos WhatsApp', false)
            ->assertSee('Envíos email', false)
            ->assertSee('id="frequency-change-previews"', false)
            ->assertSee('name="invoice_frequency"', false)
            ->assertSee('Meses anteriores', false)
            ->assertSee('id="account-rates-usage-table"', false)
            ->assertSee('name="tokens_multiplier"', false)
            ->assertSee('name="whatsapp_send"', false)
            ->assertSee('name="mailer_send"', false)
            ->assertSee('value="10"', false)
            ->assertSee('value="0.003"', false)
            ->assertSee('class="text-center">Importe', false)
            ->assertSee('class="text-center">10', false)
            ->assertSee('Multiplicador de tokens', false);
    }

    public function test_account_rates_page_shows_current_month_consumption_and_markup(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->fakeTokenCatalog();

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $chat = Module::query()->where('key', 'chat')->first();
        $projects = Module::query()->where('key', 'projects')->first();
        $this->assertNotNull($chat);
        $this->assertNotNull($projects);

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $chat->id,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 700_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $projects->id,
            'service' => 'ProjectBudgetSpecService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 300_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $this->actingAs($user)
            ->get(route('account.rates.edit', $team->id))
            ->assertOk()
            ->assertSee('10.000.000', false)
            ->assertSee('Chat 7.000.000 · Projects 3.000.000', false)
            ->assertSee('7.000.000', false)
            ->assertSee('3.000.000', false)
            ->assertSee('1,00 EUR', false)
            ->assertSee('10,00 EUR', false)
            ->assertSee('9,00 EUR', false)
            ->assertSee('×10 sobre tokens reales', false)
            ->assertSee('Preview de factura', false)
            ->assertSee('Aún no se emite', false)
            ->assertSee('class="text-end text-nowrap">Importe', false);
    }

    public function test_account_rates_usage_datatable_lists_past_months(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->fakeTokenCatalog();

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $previous = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 2_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $previous->forceFill(['created_at' => now()->subMonth(), 'updated_at' => now()->subMonth()])->save();

        $columns = [];
        foreach (['month', 'month_label', 'tokens', 'whatsapp', 'mailer', 'cost', 'billed', 'markup'] as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account.rates.edit', $team->id), [
            'draw' => 1,
            'start' => 0,
            'length' => 12,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => $columns,
        ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayNotHasKey('error', $payload);
        $this->assertSame(1, (int) $payload['recordsTotal']);
        $this->assertSame(now()->subMonth()->format('Y-m'), $payload['data'][0]['month']);
        $this->assertSame('20.000.000', $payload['data'][0]['tokens']);
        $this->assertSame('2,00 EUR', $payload['data'][0]['cost']);
        $this->assertSame('20,00 EUR', $payload['data'][0]['billed']);
        $this->assertSame('18,00 EUR', $payload['data'][0]['markup']);
    }

    public function test_account_update_stores_team_billing_rates_without_repricing_the_past(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $this->actingAs($user)->put(route('account.rates.update', $team->id), [
            'tokens_multiplier' => 8,
            'whatsapp_send' => 0.002,
            'mailer_send' => 0.008,
            'invoice_frequency' => TeamBillingFrequency::Weekly->value,
        ])->assertRedirect(route('account.rates.edit', $team->id))
            ->assertSessionHas('success', 'Frecuencia cambiada a semanal. El ciclo anterior queda como factura de ajuste. Aún no se emite.');

        $this->assertSame(8.0, TokenBillingRateService::clientTokenMultiplier($team));
        $this->assertSame(10.0, TokenBillingRateService::clientTokenMultiplier($team, now()->subMinute()));
        $this->assertSame(0.002, TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::WhatsappSend));
        $this->assertSame(0.003, TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::WhatsappSend, now()->subMinute()));
        $this->assertSame('0.008', MailerPaygPricing::pricePerEmail($team));
        $this->assertSame('0.002', MailerPaygPricing::pricePerEmail($team, now()->subMinute()));
        $this->assertSame(TeamBillingFrequency::Weekly, TeamUsageInvoiceFrequency::for($team));
    }

    public function test_switching_invoice_frequency_to_weekly_shows_adjustment_preview(): void
    {
        $this->seed(ModuleSeeder::class);
        $this->fakeTokenCatalog();

        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $elapsed = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $elapsed->forceFill([
            'created_at' => Carbon::parse('2026-09-10 10:00:00'),
            'updated_at' => Carbon::parse('2026-09-10 10:00:00'),
        ])->save();

        $this->actingAs($user)->put(route('account.rates.update', $team->id), [
            'tokens_multiplier' => 10,
            'whatsapp_send' => 0.003,
            'mailer_send' => 0.01,
            'invoice_frequency' => TeamBillingFrequency::Weekly->value,
        ])->assertRedirect(route('account.rates.edit', $team->id))
            ->assertSessionHas('success', 'Frecuencia cambiada a semanal. El ciclo anterior queda como factura de ajuste. Aún no se emite.');

        $this->actingAs($user)
            ->get(route('account.rates.edit', $team->id))
            ->assertOk()
            ->assertSee('Incluye ajuste del ciclo anterior', false)
            ->assertSee('Ajuste Mensual', false)
            ->assertSee('Tokens IA · 1 al 22 de septiembre 2026', false)
            ->assertSee('1 al 22 de septiembre 2026', false)
            ->assertSee('Total factura', false)
            ->assertSee('10,00 EUR', false);

        Carbon::setTestNow();
    }

    private function fakeTokenCatalog(): void
    {
        config([
            'services.openrouter.cache_store' => 'array',
            'humano_pricing.token_billing.currency' => 'EUR',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);
    }
}
