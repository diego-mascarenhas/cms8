<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceDigestUnreadMessageDetailService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoDigestScenariosSeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDigestScenariosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_invoices_chat_and_email_digest_scenarios(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Demo', 'user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);

        foreach (['invoices', 'chat', 'mailbox'] as $key)
        {
            Module::firstOrCreate(['key' => $key], ['name' => ucfirst($key), 'is_core' => false]);
        }

        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'TechCorp Solutions',
            'code' => 'TECH001',
            'type_id' => 1,
            'status_id' => 1,
            'email' => 'techcorp@example.com',
        ]);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Carlos',
            'surname' => 'García',
            'email' => 'carlos.garcia@cliente1.com',
            'phone' => '34600222001',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 5,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        $this->seed(DemoDigestScenariosSeeder::class);

        $team->refresh();

        $this->assertGreaterThanOrEqual(8, Enterprise::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertGreaterThanOrEqual(15, Contact::withoutGlobalScopes()->where('team_id', $team->id)->count());

        $paidAndUnpaid = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->selectRaw('SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) as unpaid, SUM(CASE WHEN balance = 0 THEN 1 ELSE 0 END) as paid')
            ->first();

        $this->assertGreaterThan(0, (int) $paidAndUnpaid->unpaid);
        $this->assertGreaterThan(0, (int) $paidAndUnpaid->paid);

        $this->assertTrue(
            Conversation::query()
                ->where('message_sid', 'SM_DEMO_DIGEST_billing_paid_whatsapp')
                ->where('status', 'received')
                ->exists(),
        );

        $this->assertTrue(
            Email::query()
                ->where('message_id', 'demo-mailbox-digest-laura-overdue')
                ->where('seen', false)
                ->exists(),
        );

        $details = app(PerformanceDigestUnreadMessageDetailService::class)
            ->forHighlightKey('whatsapp_unread', $team);

        $carlos = collect($details)->first(
            fn (array $row): bool => str_contains($row['preview'], 'facturación'),
        );

        $this->assertNotNull($carlos);
        $this->assertStringContainsString('F-2026', $carlos['suggestion']);
        $this->assertSame('whatsapp', $carlos['schedule_action']);
        $this->assertSame('34600222001', $carlos['schedule_recipient']);
    }
}
