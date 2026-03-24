<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Team;
use App\Support\CollectionMessagingGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionMessagingGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_unpaid_invoices(): void
    {
        $contact = new Contact;
        $contact->name = 'Test User';

        $this->assertNull(CollectionMessagingGuide::build($contact, ['unpaid_invoices' => []], null));
    }

    public function test_hosting_collections_definition_serializes_to_json(): void
    {
        $definition = CollectionMessagingGuide::hostingCollectionsPromptDefinition();

        $this->assertArrayHasKey('advisor_notes', $definition);
        $this->assertArrayHasKey('steps', $definition);
        $this->assertNotFalse(json_encode($definition));
        $this->assertJson(json_encode($definition));
    }

    public function test_sync_hosting_collections_prompt_returns_false_when_team_missing(): void
    {
        $this->assertFalse(CollectionMessagingGuide::syncHostingCollectionsPromptForTeam(999_999_999));
    }

    public function test_builds_steps_with_invoice_lines_and_links(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('collection_bank_transfer', [
            'account_holder' => 'Diego Adrián Mascarenhas Goytía',
            'cuit' => '20-25024200-0',
            'cbu' => '0000003100042016955017',
            'alias' => 'revision.alpha.arg',
        ], [
            'type' => 'json',
            'group' => 'billing',
            'is_encrypted' => false,
        ]);

        $contact = new Contact;
        $contact->name = 'Paola Example';

        $stripeData = [
            'unpaid_invoices' => [
                [
                    'number' => '0005-0563',
                    'date' => '28/02/2026',
                    'amount' => 20909.09,
                    'currency' => 'ARS',
                    'hosted_invoice_url' => 'https://invoice.stripe.com/test',
                    'pdf' => 'https://pay.stripe.com/invoice/test/pdf',
                    'dashboard_url' => 'https://dashboard.stripe.com/invoices/in_xxx',
                ],
            ],
            'subscriptions' => [
                ['status' => 'past_due'],
            ],
        ];

        $guide = CollectionMessagingGuide::build($contact, $stripeData, $team->id);

        $this->assertNotNull($guide);
        $this->assertStringContainsString('Paola', $guide['full_copy']);
        $this->assertStringContainsString('0005-0563', $guide['full_copy']);
        $this->assertStringContainsString('https://invoice.stripe.com/test', $guide['full_copy']);
        $this->assertStringContainsString('suspendido', $guide['full_copy']);
        $this->assertStringContainsString('Diego Adrián Mascarenhas Goytía', $guide['full_copy']);
        $this->assertStringContainsString('revision.alpha.arg', $guide['full_copy']);
    }
}
