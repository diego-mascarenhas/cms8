<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceDigestContactInvoiceContextService;
use App\Services\PerformanceDigestUnreadMessageDetailService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceDigestContactInvoiceContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedInvoiceAndContactDependencies(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_returns_invoice_context_for_contact_enterprise(): void
    {
        $this->seedInvoiceAndContactDependencies();

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Module::firstOrCreate(['key' => 'invoices'], ['name' => 'Invoices', 'is_core' => false]);
        $team->enableModule('invoices');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'phone' => '34600111222',
            'email' => 'ana@acme.test',
            'current_enterprise_id' => $enterprise->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-100',
            'date' => now()->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'gross_amount' => 150.50,
            'discount' => 0,
            'total_amount' => 150.50,
            'balance' => 150.50,
            'status' => 2,
        ]);

        $context = app(PerformanceDigestContactInvoiceContextService::class)->forContact($team, $contact);

        $this->assertNotNull($context);
        $this->assertSame('single_overdue', $context['variant']);
        $this->assertSame('F-100', $context['invoice_number']);
        $this->assertSame(1, $context['count']);
    }

    public function test_whatsapp_suggestion_uses_unpaid_invoice_dialogue_when_contact_linked(): void
    {
        $this->seedInvoiceAndContactDependencies();

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');
        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        Module::firstOrCreate(['key' => 'invoices'], ['name' => 'Invoices', 'is_core' => false]);
        $team->enableModule('chat');
        $team->enableModule('invoices');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SA',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'phone' => '34600111222',
            'email' => 'ana@example.com',
            'current_enterprise_id' => $enterprise->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-2024-99',
            'date' => now()->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 200,
            'status' => 2,
        ]);

        \App\Models\Conversation::create([
            'message_sid' => 'SM_invoice_ctx',
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999000111',
            'body' => 'Hola, ¿cómo va todo?',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $details = app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('whatsapp_unread', $team->fresh());

        $this->assertCount(1, $details);
        $this->assertStringContainsString('Hola Ana', $details[0]['suggestion']);
        $this->assertStringContainsString('F-2024-99', $details[0]['suggestion']);
        $this->assertStringContainsString('factura', mb_strtolower($details[0]['suggestion']));
        $this->assertStringContainsString('/invoices/', $details[0]['action_url'] ?? '');
    }

    public function test_billing_inquiry_uses_invoice_history_when_no_unpaid_balance(): void
    {
        $this->seedInvoiceAndContactDependencies();

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');
        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        Module::firstOrCreate(['key' => 'invoices'], ['name' => 'Invoices', 'is_core' => false]);
        $team->enableModule('chat');
        $team->enableModule('invoices');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SA',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'phone' => '34600111222',
            'current_enterprise_id' => $enterprise->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-2025-01',
            'date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subMonth()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        \App\Models\Conversation::create([
            'message_sid' => 'SM_billing_inquiry',
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999000111',
            'body' => 'Hola, querría información sobre facturación.',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $details = app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('whatsapp_unread', $team->fresh());

        $this->assertCount(1, $details);
        $this->assertStringContainsString('Cliente SA', $details[0]['suggestion']);
        $this->assertStringContainsString('F-2025-01', $details[0]['suggestion']);
        $this->assertStringContainsString('no hay pendientes', mb_strtolower($details[0]['suggestion']));
        $this->assertStringContainsString('f-2025-01', mb_strtolower($details[0]['response_hint']));
    }
}
