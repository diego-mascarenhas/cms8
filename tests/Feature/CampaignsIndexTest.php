<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Content;
use App\Models\Currency;
use App\Models\Message;
use App\Models\Product;
use App\Models\SubscriptionProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_campaigns_datatables_ajax_returns_json_when_accept_json(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Ajax Test Campaign',
        ]);
        Campaign::factory()->sequenceSummary()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Ajax Sequence Campaign',
        ]);

        $columnDefs = [
            ['campaign_display', false],
            ['type_display', false],
            ['performance_display', false],
            ['status', false],
            ['action', false],
        ];
        $columns = [];
        foreach ($columnDefs as [$data, $orderable])
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'false',
                'orderable' => $orderable ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('campaigns.index'), [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => $columns,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type'),
        );
        $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertGreaterThanOrEqual(2, (int) $response->json('recordsFiltered'));

        $htmlFromTypes = implode(' ', array_map(function ($row): string
        {
            return (string) ($row['type_display'] ?? '');
        }, $response->json('data') ?? []));
        $this->assertStringContainsString('Difusión', $htmlFromTypes);
        $this->assertStringContainsString('Secuencia', $htmlFromTypes);

        foreach ($response->json('data') ?? [] as $row)
        {
            $this->assertStringNotContainsString(
                'ti-copy',
                (string) ($row['action'] ?? ''),
            );
        }
    }

    public function test_campaigns_page_shows_campaign_manager_sections_when_authenticated(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('campaigns.index'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Campañas'),
        );
        $this->assertTrue(
            str_contains($html, 'Campañas de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas'),
        );
        $this->assertTrue(
            str_contains($html, 'Nueva campaña'),
        );
        $this->assertTrue(
            str_contains($html, 'campaigns-table'),
        );
    }

    public function test_campaigns_edit_literal_path_is_not_matched_as_numeric_id(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get('/campaigns/edit');

        $response->assertNotFound();
    }

    public function test_campaign_update_persists_name_and_redirects_to_show(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Nombre inicial',
        ]);

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Título actualizado',
            'send_time_zone' => 'Europe/Madrid',
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHas('success');
        $campaign->refresh();
        $this->assertSame('Título actualizado', $campaign->name);
        $this->assertSame('Europe/Madrid', data_get($campaign->settings, 'send_time_zone'));
        $this->assertNull(data_get($campaign->settings, 'automations'));
        $this->assertNull(data_get($campaign->settings, 'sequence_exclusions'));
    }

    public function test_campaign_update_without_exclusions_flag_preserves_sequence_exclusions(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Nombre inicial',
            'settings' => [
                'sequence_exclusions' => [
                    'product_ids' => [101],
                    'subscription_product_ids' => [202],
                    'content_ids' => [303],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Solo título',
            'send_time_zone' => 'UTC',
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $campaign->refresh();
        $this->assertSame([101], data_get($campaign->settings, 'sequence_exclusions.product_ids'));
        $this->assertSame([202], data_get($campaign->settings, 'sequence_exclusions.subscription_product_ids'));
        $this->assertSame([303], data_get($campaign->settings, 'sequence_exclusions.content_ids'));
    }

    public function test_campaign_edit_page_lists_catalog_products_and_subscription_products(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = $user->current_team_id;

        $currency = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'status' => true],
        );
        $productCategory = Category::factory()->create(['team_id' => $teamId]);
        $product = Product::factory()->create([
            'team_id' => $teamId,
            'category_id' => $productCategory->id,
            'currency_id' => $currency->id,
            'name' => 'Catalog Item Alpha',
            'status' => true,
        ]);

        $subscriptionProduct = SubscriptionProduct::create([
            'name' => 'Plan Stripe Beta',
            'active' => true,
            'currency' => 'usd',
        ]);

        $campaign = Campaign::factory()->sequenceSummary()->create([
            'team_id' => $teamId,
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.edit', $campaign));

        $response->assertOk();
        $response->assertSee('Catalog Item Alpha', false);
        $response->assertSee('Plan Stripe Beta', false);
        $response->assertSee('value="product:'.$product->id.'"', false);
        $response->assertSee('value="subscription:'.$subscriptionProduct->id.'"', false);
    }

    public function test_campaign_update_with_sequence_exclusions_saves_settings(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = $user->current_team_id;

        $currency = Currency::query()->firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'status' => true],
        );
        $category = Category::factory()->create(['team_id' => $teamId]);
        $product = Product::factory()->create([
            'team_id' => $teamId,
            'category_id' => $category->id,
            'currency_id' => $currency->id,
            'status' => true,
        ]);

        $subscriptionProduct = SubscriptionProduct::create([
            'name' => 'Sub Plan',
            'active' => true,
            'currency' => 'usd',
        ]);
        $content = Content::create([
            'team_id' => $teamId,
            'section_category_id' => $category->id,
            'category_id' => null,
            'template' => 'default',
            'order' => 0,
            'status' => 1,
            'title' => ['es' => 'Formulario webinar'],
            'content' => ['es' => ''],
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Camp',
        ]);

        $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Camp',
            'send_time_zone' => 'Europe/Madrid',
            'sequence_exclusions_present' => '1',
            'exclude_offer_refs' => [
                'product:'.$product->id,
                'subscription:'.$subscriptionProduct->id,
            ],
            'exclude_content_ids' => [(string) $content->id],
        ]);

        $campaign->refresh();
        $this->assertSame([$product->id], data_get($campaign->settings, 'sequence_exclusions.product_ids'));
        $this->assertSame([$subscriptionProduct->id], data_get($campaign->settings, 'sequence_exclusions.subscription_product_ids'));
        $this->assertSame([$content->id], data_get($campaign->settings, 'sequence_exclusions.content_ids'));
    }

    public function test_campaign_put_update_preserves_automations_configured_on_show(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Con automatizaciones',
            'settings' => [
                'send_time_zone' => 'UTC',
                'automations' => [
                    ['trigger' => 'delay_after_enrollment', 'channel_type_id' => 1],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Título solo editor',
            'send_time_zone' => 'Europe/Madrid',
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $campaign->refresh();
        $this->assertSame('Título solo editor', $campaign->name);
        $this->assertSame('Europe/Madrid', data_get($campaign->settings, 'send_time_zone'));
        $autos = data_get($campaign->settings, 'automations');
        $this->assertIsArray($autos);
        $this->assertSame('delay_after_enrollment', $autos[0]['trigger']);
    }

    public function test_campaign_sequence_and_automations_persist_via_detail_patch_routes(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        DB::table('message_type')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Mailer', 'status' => 1],
        );
        DB::table('message_type')->updateOrInsert(
            ['id' => 2],
            ['name' => 'WhatsApp', 'status' => 1],
        );

        $messageA = Message::withoutGlobalScopes()->create([
            'name' => 'Paso A',
            'type_id' => 1,
            'text' => 'A',
            'team_id' => $teamId,
        ]);
        $messageB = Message::withoutGlobalScopes()->create([
            'name' => 'Paso B',
            'type_id' => 2,
            'text' => 'B',
            'team_id' => $teamId,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Secuencia test',
        ]);

        $campaign->messages()->syncWithoutDetaching([
            $messageA->id => ['sort_order' => 0, 'delay_minutes_after_previous' => null, 'conditions' => null],
            $messageB->id => ['sort_order' => 1, 'delay_minutes_after_previous' => null, 'conditions' => null],
        ]);

        $this->actingAs($user)->patch(route('campaigns.sequence.update', $campaign), [
            'manage_automations' => true,
            'sequence' => [
                [
                    'message_id' => $messageA->id,
                    'sort_order' => 1,
                    'delay_minutes_after_previous' => 60,
                    'condition_preset' => 'none',
                    'automations' => [],
                ],
                [
                    'message_id' => $messageB->id,
                    'sort_order' => 2,
                    'delay_minutes_after_previous' => '',
                    'condition_preset' => 'opened',
                    'automations' => [
                        [
                            'trigger' => 'if_opened_previous',
                            'delay_hours' => 24,
                            'channel_type_id' => 2,
                            'linked_message_id' => $messageB->id,
                            'notes' => 'WA follow-up',
                        ],
                        [
                            'trigger' => 'if_not_opened_previous',
                            'delay_hours' => 48,
                            'channel_type_id' => 1,
                            'linked_message_id' => $messageA->id,
                            'notes' => 'Mail nudge',
                        ],
                    ],
                ],
            ],
        ])->assertRedirect(route('campaigns.show', $campaign));

        $campaign->refresh();
        $autos = data_get($campaign->settings, 'automations');
        $this->assertIsArray($autos);
        $this->assertCount(2, $autos);
        $this->assertSame('if_opened_previous', $autos[0]['trigger']);
        $this->assertSame(2, $autos[0]['channel_type_id']);
        $this->assertSame(24, $autos[0]['delay_hours']);
        $this->assertSame($messageB->id, $autos[0]['message_id']);
        $this->assertSame($messageB->id, $autos[0]['step_message_id']);
        $this->assertSame('if_not_opened_previous', $autos[1]['trigger']);
        $this->assertSame(1, $autos[1]['channel_type_id']);
        $this->assertSame(48, $autos[1]['delay_hours']);
        $this->assertSame($messageA->id, $autos[1]['message_id']);
        $this->assertSame($messageB->id, $autos[1]['step_message_id']);

        $rowB = DB::table('campaign_message')
            ->where('campaign_id', $campaign->id)
            ->where('message_id', $messageB->id)
            ->first();
        $this->assertNotNull($rowB);
        $this->assertSame(2, (int) $rowB->sort_order);
        $this->assertSame(['require_previous' => 'opened'], json_decode((string) $rowB->conditions, true));
    }

    public function test_campaign_edit_page_shows_sequence_settings_sections(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->sequenceSummary()->create([
            'team_id' => $user->current_team_id,
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.edit', ['campaign' => $campaign]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Configuración de secuencia de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'Detalles de la secuencia'),
        );
        $this->assertTrue(
            str_contains($html, 'Guardar'),
        );
        $this->assertSame(
            1,
            substr_count($html, 'type="submit"'),
            'Expected a single submit control in the campaign edit form.',
        );
        $this->assertTrue(
            str_contains($html, 'btn-primary'),
        );
        $this->assertTrue(
            str_contains($html, 'Volver'),
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, route('campaigns.index')),
            'Expected Volver header link and Cancel footer action to reference the campaigns list.',
        );
        $this->assertTrue(
            str_contains($html, 'btn-label-secondary'),
            'Expected a secondary Cancel button alongside Guardar.',
        );
    }

    public function test_template_selection_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.templates.select', [
            'type' => 'broadcasts',
            'title' => 'Mi campaña',
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Selecciona una plantilla'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas personalizadas'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas destacadas'),
        );
    }

    public function test_template_selection_with_campaign_id_shows_back_link_and_context(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $campaign = Campaign::factory()->create([
            'team_id' => $team->id,
            'name' => 'Context campaign',
        ]);

        $response = $this->actingAs($user->fresh())->get(route('campaigns.templates.select', [
            'type' => 'sequences',
            'title' => $campaign->name,
            'campaign_id' => $campaign->id,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(str_contains($html, 'Volver a la campaña'));
        $this->assertTrue(str_contains($html, 'Context campaign'));
        $this->assertTrue(str_contains($html, 'campaign_id'));
    }

    public function test_classic_editor_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.classic-editor', [
            'type' => 'sequences',
            'title' => 'Mi secuencia',
            'template_id' => 2,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Editar correo de secuencia'),
        );
        $this->assertTrue(
            str_contains($html, 'Título interno'),
        );
        $this->assertTrue(
            str_contains($html, 'Asunto'),
        );
        $this->assertTrue(
            str_contains($html, 'email-template-content-preview'),
        );
        $this->assertTrue(
            str_contains($html, 'Contenido del correo'),
        );
        $this->assertSame(1, substr_count($html, 'Contenido del correo'), 'Expected a single mail content toolbar block.');
        $this->assertSame(1, substr_count($html, 'Guardar para después'), 'Expected a single save-for-later submit control.');
    }

    public function test_grapes_editor_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.classic-editor.grapes', [
            'type' => 'sequences',
            'title' => 'Mi secuencia',
            'template_id' => 2,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Editor visual de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'gjs-editor'),
        );
    }
}
