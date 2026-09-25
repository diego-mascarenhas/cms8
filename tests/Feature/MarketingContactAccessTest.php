<?php

namespace Tests\Feature;

use App\Models\AdPlatformConnection;
use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingContactAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);

        foreach (['admin', 'marketing'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
    }

    public function test_marketing_sees_all_team_contacts_without_billing(): void
    {
        [$admin, $marketer, $assigned, $other] = $this->makeMarketingTeam();

        $this->assertFalse($marketer->canAccessBilling());
        $this->assertTrue($marketer->isMarketing());
        $this->assertFalse($marketer->seesFullContactProfile());
        $this->assertTrue($marketer->can('view', $other));
        $this->assertFalse($marketer->can('update', $other));

        $response = $this->actingAs($marketer)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('contact-list').'?'.http_build_query($this->contactDataTableBaseQuery()));

        $response->assertOk();
        $this->assertSame(2, (int) $response->json('recordsFiltered'));

        $rowIds = collect($response->json('data'))->pluck('DT_RowId')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($assigned->id, $rowIds);
        $this->assertContains($other->id, $rowIds);

        $this->actingAs($marketer)
            ->get(route('contact.show', $other->id))
            ->assertOk()
            ->assertSee($other->name)
            ->assertSee($other->email)
            ->assertSee('Directorio: nombre, email, teléfono y empresa', false)
            ->assertDontSee('id="activity-tab"', false)
            ->assertDontSee('id="balance-tab"', false)
            ->assertDontSee('id="billing-tab"', false)
            ->assertDontSee('Editar contacto', false);

        $invoiceResponse = $this->actingAs($marketer)->get(route('invoice.index'));
        $this->assertTrue(
            $invoiceResponse->isForbidden() || $invoiceResponse->isRedirect(),
            'Marketing must not open the invoice list',
        );

        $this->actingAs($admin)
            ->get(route('contact.show', $other->id))
            ->assertOk()
            ->assertSee('id="billing-tab"', false);
    }

    public function test_marketing_can_create_paid_ad_campaign(): void
    {
        [, $marketer] = $this->makeMarketingTeam();

        Module::query()->firstOrCreate(
            ['key' => 'paid_ads'],
            [
                'name' => 'Paid Ads',
                'icon' => 'target-arrow',
                'description' => 'Paid advertising campaigns',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $marketer->currentTeam->enableModule('paid_ads');

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $marketer->currentTeam->id,
            'user_id' => $marketer->id,
        ]);

        $this->actingAs($marketer)->post(route('paid-ads.store'), [
            'name' => 'Marketing launch',
            'objective' => 'leads',
            'budget_type' => 'daily',
            'budget_amount' => 10,
            'currency' => 'EUR',
            'platforms' => [$connection->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('paid_ad_campaigns', [
            'name' => 'Marketing launch',
            'team_id' => $marketer->currentTeam->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Contact, 3: Contact}
     */
    private function makeMarketingTeam(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $marketer = User::factory()->create();
        $marketer->assignRole('marketing');
        $team->users()->attach($marketer->id, ['role' => 'marketing']);
        $marketer->forceFill(['current_team_id' => $team->id])->save();

        $assigned = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Assigned Lead',
            'email' => 'assigned-lead@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $marketer->id,
            'status_id' => 1,
        ]);

        $other = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Other Lead',
            'email' => 'other-lead@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        return [$admin, $marketer->fresh(), $assigned, $other];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ([
            ['data' => 'id', 'name' => 'id'],
            ['data' => 'name', 'name' => 'name'],
            ['data' => 'current_sentiment', 'name' => 'current_sentiment'],
            ['data' => 'current_intent', 'name' => 'current_intent'],
            ['data' => 'sources', 'name' => 'sources'],
            ['data' => 'responsible_name', 'name' => 'responsible_name'],
            ['data' => 'categories', 'name' => 'categories'],
            ['data' => 'status_id', 'name' => 'status_id'],
            ['data' => 'action', 'name' => 'action'],
        ] as $def)
        {
            $columns[] = array_merge($def, [
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ];
    }
}
