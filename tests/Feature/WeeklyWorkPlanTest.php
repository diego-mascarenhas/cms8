<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Email;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\List60;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Service;
use App\Models\User;
use App\Services\Finance\InvoiceSummaryService;
use App\Services\StrategyFieldSuggestionService;
use App\Services\WeeklyWorkPlanService;
use Carbon\Carbon;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeeklyWorkPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_monday_plan_orders_work_and_includes_quarterly_tax_filing(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        $team->setSetting('business_config', [
            'business_challenge' => 'Cerrar más propuestas este mes',
        ], ['type' => 'json', 'group' => 'business-config']);

        $company = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
        ]);
        $personal = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);
        Email::factory()->count(2)->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);
        Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $personal->id,
            'seen' => false,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $this->invoice($team->id, $enterprise->id, InvoiceSummaryService::DRAFT_STATUS, now()->addDays(10)->toDateString(), 'DRAFT-1');
        $this->invoice($team->id, $enterprise->id, 1, now()->subDays(5)->toDateString(), 'OVERDUE-1');

        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Lista',
            'email' => 'lista@example.test',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);
        List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => '2026-10-06',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $plan = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame('plan', $plan['mode']);
        $this->assertSame('Plan de la semana', $plan['title']);
        $this->assertSame('Cerrar más propuestas este mes', $plan['challenge']);
        $this->assertSame([
            'sales_objective',
            'strategy_level',
            'business_challenge',
            'marketing',
            'company_email',
            'personal_email',
            'draft_invoices',
            'overdue_invoices',
            'tax_filing',
            'list60',
        ], array_column($plan['items'], 'key'));
        $drafts = collect($plan['items'])->firstWhere('key', 'draft_invoices');
        $overdue = collect($plan['items'])->firstWhere('key', 'overdue_invoices');
        $this->assertSame('DRAFT-1 · Acme SL', $drafts['details'][0]['label']);
        $this->assertSame('OVERDUE-1 · Acme SL', $overdue['details'][0]['label']);
        $list = collect($plan['items'])->firstWhere('key', 'list60');
        $this->assertSame('Cliente Lista', $list['details'][0]['label']);
        $this->assertStringContainsString('Hola Cliente', $list['details'][0]['note']);
        $tax = collect($plan['items'])->firstWhere('key', 'tax_filing');
        $this->assertSame('Presentar impuestos del T3 2026 (hasta el 20 de octubre)', $tax['label']);
        $challenge = collect($plan['items'])->firstWhere('key', 'business_challenge');
        $this->assertNotNull($challenge);
        $this->assertStringContainsString('Cerrar más propuestas', $challenge['label']);
        $marketing = collect($plan['items'])->firstWhere('key', 'marketing');
        $this->assertNotNull($marketing);
        $this->assertSame('linkedin', $marketing['social_channel']);
    }

    public function test_midweek_keeps_the_monday_plan(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        $company = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
        ]);
        Email::factory()->count(2)->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);

        $monday = app(WeeklyWorkPlanService::class)->present($user, $team);

        Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);
        Carbon::setTestNow('2026-10-07');

        $wednesday = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame($monday['items'], $wednesday['items']);
        $companyEmail = collect($wednesday['items'])->firstWhere('key', 'company_email');
        $this->assertSame(2, $companyEmail['count']);
    }

    public function test_friday_reviews_what_is_still_open(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        $company = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
        ]);
        Email::factory()->count(2)->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);

        app(WeeklyWorkPlanService::class)->present($user, $team);
        Email::query()->where('mailbox_id', $company->id)->update(['seen' => true]);
        Carbon::setTestNow('2026-10-09');

        $friday = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame('review', $friday['mode']);
        $this->assertSame('Cierre de la semana', $friday['title']);
        $email = collect($friday['items'])->firstWhere('key', 'company_email');
        $tax = collect($friday['items'])->firstWhere('key', 'tax_filing');
        $this->assertTrue($email['done']);
        $this->assertSame('Correos de la empresa. Resuelto.', $email['label']);
        $this->assertFalse($tax['done']);
        $this->assertStringContainsString('T3 2026', $tax['label']);
        $this->assertStringContainsString('sigue pendiente', $tax['label']);
    }

    public function test_sunday_shows_the_plan_until_friday_has_reviewed_it(): void
    {
        Carbon::setTestNow('2026-10-04');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();

        $sunday = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame('plan', $sunday['mode']);
        $this->assertSame('Plan de la semana', $sunday['title']);
    }

    public function test_week_outside_the_filing_month_omits_tax(): void
    {
        Carbon::setTestNow('2026-09-07');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();

        $plan = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame(['strategy_level', 'marketing'], array_column($plan['items'], 'key'));
        $this->assertNull(collect($plan['items'])->firstWhere('key', 'tax_filing'));
    }

    public function test_week_before_the_filing_month_includes_tax(): void
    {
        Carbon::setTestNow('2026-09-21');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();

        $plan = app(WeeklyWorkPlanService::class)->present($user, $team);

        $this->assertSame(['strategy_level', 'marketing', 'tax_filing'], array_column($plan['items'], 'key'));
        $tax = collect($plan['items'])->firstWhere('key', 'tax_filing');
        $this->assertStringContainsString('T3 2026', $tax['label']);
    }

    public function test_dashboard_card_shows_the_weekly_plan(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user] = $this->planner();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Plan de la semana', false);
        $response->assertSee('ti-calendar-week', false);
        $response->assertSee('Presentar impuestos del T3 2026 (hasta el 20 de octubre)', false);
        $response->assertSee(route('weekly-plan.index'), false);
        $response->assertSee('ti-sitemap', false);
        $response->assertSee(route('strategy.index'), false);
        $response->assertDontSee('Haz tenido una gran IDEA', false);
    }

    public function test_report_navigates_between_saved_weeks(): void
    {
        Carbon::setTestNow('2026-09-21');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        app(WeeklyWorkPlanService::class)->present($user, $team);

        Carbon::setTestNow('2026-10-05');
        $this->actingAs($user);

        $current = $this->get(route('weekly-plan.index'));
        $current->assertOk();
        $current->assertSee('Semana anterior', false);
        $current->assertSee('T3 2026', false);

        $previous = $this->get(route('weekly-plan.index', ['week' => '2026-09-21']));
        $previous->assertOk();
        $previous->assertSee('Semana siguiente', false);
        $previous->assertSee('T3 2026', false);
        $previous->assertSee('Equipo', false);
    }

    public function test_report_lists_projects_invoices_services_leads_and_campaign_dialogues(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        $this->seed(ProjectStatusSeeder::class);
        foreach (['projects', 'services', 'contacts'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $key,
                    'icon' => 'box',
                    'description' => $key,
                    'status' => 1,
                ],
            );
            $team->enableModule($key);
        }

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Web de Acme',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'date_end' => '2026-10-07',
        ]);
        $this->invoice($team->id, $enterprise->id, InvoiceSummaryService::DRAFT_STATUS, now()->addDays(10)->toDateString(), 'DRAFT-9');
        $this->invoice($team->id, $enterprise->id, 1, now()->subDays(5)->toDateString(), 'VENCIDA-3');
        Service::withoutGlobalScope('team')->withoutGlobalScope('ownership')->create([
            'enterprise_id' => $enterprise->id,
            'operation' => 'sell',
            'description' => 'Hosting corporativo',
            'status' => 3,
            'responsible_id' => $user->id,
        ]);
        Service::withoutGlobalScope('team')->withoutGlobalScope('ownership')->create([
            'enterprise_id' => $enterprise->id,
            'operation' => 'sell',
            'description' => 'Ya activo',
            'status' => 4,
            'responsible_id' => $user->id,
        ]);

        $this->contact($user, $team->id, 'Laura Lead', (int) ContactStatus::query()->where('name', 'Lead')->value('id'));
        $suggested = $this->contact($user, $team->id, 'Pedro Campaña', (int) ContactStatus::query()->where('name', 'En seguimiento')->value('id'));
        $message = Message::withoutGlobalScope('team')->create([
            'name' => 'Campaña empatía',
            'type_id' => 1,
            'text' => 'Medir empatía',
            'team_id' => $team->id,
            'status_id' => 1,
        ]);
        $campaign = Campaign::factory()->create([
            'team_id' => $team->id,
            'name' => 'Diagnóstico de empatía',
            'summary' => 'Medir la empatía en el equipo',
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $suggested->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
            'sent_at' => now()->subDay(),
            'opened_at' => now()->subHour(),
        ]);

        $fromMessage = $this->contact($user, $team->id, 'Nora Mensaje', (int) ContactStatus::query()->where('name', 'En seguimiento')->value('id'));
        $newsletter = Message::withoutGlobalScope('team')->create([
            'name' => 'News de septiembre',
            'type_id' => 1,
            'text' => 'Novedades del mes',
            'team_id' => $team->id,
            'status_id' => 1,
        ]);
        MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $newsletter->id,
            'contact_id' => $fromMessage->id,
            'status_id' => 1,
            'sent_at' => now()->subDay(),
            'opened_at' => now()->subHour(),
        ]);

        $plan = app(WeeklyWorkPlanService::class)->present($user, $team);
        $byKey = collect($plan['items'])->keyBy('key');

        $this->assertSame('Contactar 3 personas y 3 acciones de venta', $byKey['sales_objective']['label']);
        $this->assertSame('Web de Acme', $byKey['projects']['details'][0]['label']);
        $this->assertSame('DRAFT-9 · Acme SL', $byKey['draft_invoices']['details'][0]['label']);
        $this->assertSame('VENCIDA-3 · Acme SL', $byKey['overdue_invoices']['details'][0]['label']);
        $this->assertSame('Activar · Hosting corporativo · Acme SL', $byKey['services']['details'][0]['label']);
        $this->assertCount(1, $byKey['services']['details']);
        $this->assertSame('Laura Lead', $byKey['new_leads']['details'][0]['label']);
        $this->assertSame('Hola Laura, te escribo para retomar el contacto de esta semana.', $byKey['new_leads']['details'][0]['note']);
        $suggestions = collect($byKey['list60_suggestions']['details'])->keyBy('label');
        $this->assertStringContainsString('Diagnóstico de empatía', $suggestions['Pedro Campaña']['note']);
        $this->assertStringContainsString('Medir la empatía en el equipo', $suggestions['Pedro Campaña']['note']);
        $this->assertStringContainsString('News de septiembre', $suggestions['Nora Mensaje']['note']);
        $this->assertStringContainsString('Novedades del mes', $suggestions['Nora Mensaje']['note']);

        $this->actingAs($user)
            ->get(route('weekly-plan.index'))
            ->assertOk()
            ->assertSee('Web de Acme', false)
            ->assertSee('VENCIDA-3', false)
            ->assertSee('Hosting corporativo', false)
            ->assertSee('Diagnóstico de empatía', false);
    }

    public function test_force_rebuild_refreshes_counts_midweek(): void
    {
        Carbon::setTestNow('2026-10-05');
        app()->setLocale('es_ES');

        [$user, $team] = $this->planner();
        $company = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
        ]);
        Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);

        app(WeeklyWorkPlanService::class)->present($user, $team);
        Email::factory()->create([
            'team_id' => $team->id,
            'mailbox_id' => $company->id,
            'seen' => false,
        ]);
        Carbon::setTestNow('2026-10-07');

        $forced = app(WeeklyWorkPlanService::class)->present($user, $team, null, true);
        $companyEmail = collect($forced['items'])->firstWhere('key', 'company_email');
        $this->assertSame(2, $companyEmail['count']);
    }

    public function test_regenerate_endpoint_is_unavailable_outside_local(): void
    {
        [$user] = $this->planner();
        $this->actingAs($user);

        $this->post(route('weekly-plan.regenerate'))
            ->assertNotFound();
    }

    public function test_strategy_advance_persists_level(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $this->assertSame(1, app(WeeklyWorkPlanService::class)->strategyLevel($team));

        $this->post(route('strategy.advance'))
            ->assertRedirect(route('strategy.level'));

        $this->assertSame(2, app(WeeklyWorkPlanService::class)->strategyLevel($team->fresh()));
    }

    public function test_strategy_fields_persist_in_business_config(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $this->post(route('strategy.update'), [
            'strategy' => [
                'ideal_client' => 'PYMEs de servicios',
                'destination' => 'Cerrar 10 clientes/mes',
                'offer' => 'Auditoría + plan 90 días',
                'storytelling' => 'De caos operativo a sistema que vende solo',
            ],
        ])->assertRedirect(route('strategy.level'));

        $config = $team->fresh()->getSetting('business_config', []);
        if (is_string($config))
        {
            $config = json_decode($config, true) ?: [];
        }

        $this->assertSame('PYMEs de servicios', $config['strategy']['ideal_client']);
        $this->assertSame('De caos operativo a sistema que vende solo', $config['strategy']['storytelling']);

        $plans = app(WeeklyWorkPlanService::class);
        $step = $plans->strategyStep($team->fresh());
        $this->assertSame(4, $step['filled']);
        $this->assertSame(4, $step['total']);

        $progress = $plans->strategyStepsProgress($team->fresh());
        $this->assertTrue($progress[1]['complete']);
        $this->assertFalse($progress[2]['complete']);
    }

    public function test_strategy_page_shows_saved_storytelling(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $team->setSetting('business_config', [
            'strategy_level' => 1,
            'strategy' => [
                'storytelling' => 'Historia guardada en JSON',
            ],
        ], ['type' => 'json', 'group' => 'business-config']);

        $this->get(route('strategy.index'))
            ->assertOk()
            ->assertDontSee('id="strategy-storytelling"', false)
            ->assertDontSee(__('app.weekly_plan_strategy_here'), false)
            ->assertDontSee('4/4', false)
            ->assertSee('ti-briefcase', false)
            ->assertSee('ti-world', false)
            ->assertSee('ti-device-gamepad-2', false)
            ->assertDontSee('ti-circle-check', false)
            ->assertSee(route('strategy.level', ['level' => 2]), false)
            ->assertSee(__('app.weekly_plan_strategy_evaluate'), false)
            ->assertDontSee(__('app.weekly_plan_strategy_advance'), false);

        $this->get(route('strategy.level'))
            ->assertOk()
            ->assertSee('Historia guardada en JSON', false)
            ->assertSee('Storytelling', false)
            ->assertSee('ti-target', false)
            ->assertDontSee('ti-arrow-left', false)
            ->assertDontSee(__('app.weekly_plan_strategy_advance'), false)
            ->assertSee('col-12', false)
            ->assertDontSee('col-md-6', false);

        $this->get(route('strategy.level', ['level' => 2]))
            ->assertOk()
            ->assertSee('tu fachada digital.', false)
            ->assertSee('Web', false)
            ->assertSee('name="level"', false)
            ->assertSee('value="2"', false)
            ->assertSee('data-field="content_strategy"', false)
            ->assertSee('strategy-editor', false)
            ->assertSee('strategy-suggestion-mark', false)
            ->assertSee(__('app.strategy_field_suggest'), false)
            ->assertDontSee(__('app.strategy_field_suggestion_use'), false);

        $this->post(route('strategy.update'), [
            'level' => 2,
            'strategy' => [
                'web' => 'https://idoneo.dev',
            ],
        ])->assertRedirect(route('strategy.level', ['level' => 2]));
    }

    public function test_strategy_evaluation_sets_the_level_and_checks_validated_notes(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $team->setSetting('business_config', [
            'strategy_level' => 5,
            'strategy' => [
                'ideal_client' => 'PYMEs de servicios en España',
                'destination' => 'Cerrar 10 clientes al mes',
                'offer' => 'Auditoría y plan de 90 días',
                'storytelling' => 'De caos operativo a un sistema que vende',
            ],
        ], ['type' => 'json', 'group' => 'business-config']);

        $payload = json_encode([
            'validated' => ['ideal_client', 'destination', 'offer', 'storytelling', 'web'],
            'notes' => [
                'web' => 'Falta la URL y a quién convierte.',
            ],
            'summary' => 'El dossier comercial está concreto. La fachada digital sigue vacía.',
        ], JSON_UNESCAPED_UNICODE);

        $service = \Mockery::mock(\App\Services\StrategyLevelReviewService::class)->makePartial();
        $service->shouldReceive('suggest')->once()->andReturn($payload);
        $this->app->instance(\App\Services\StrategyLevelReviewService::class, $service);

        $this->post(route('strategy.evaluate'))
            ->assertRedirect(route('strategy.review'));

        $fresh = $team->fresh();
        $this->assertSame(2, app(WeeklyWorkPlanService::class)->strategyLevel($fresh));

        $board = $this->get(route('strategy.index'))
            ->assertOk()
            ->assertSee('ti-circle-check', false);
        $this->assertSame(4, substr_count($board->getContent(), 'ti-circle-check'));

        $this->get(route('strategy.review'))
            ->assertOk()
            ->assertSee('El dossier comercial está concreto. La fachada digital sigue vacía.', false)
            ->assertSee(__('app.weekly_plan_strategy_current', ['level' => 2]), false)
            ->assertSee('Falta la URL y a quién convierte.', false)
            ->assertSee(__('app.strategy_review_missing'), false);
    }

    public function test_strategy_evaluation_keeps_the_level_when_the_model_fails(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $team->setSetting('business_config', [
            'strategy_level' => 5,
            'strategy' => [
                'ideal_client' => 'PYMEs de servicios',
            ],
        ], ['type' => 'json', 'group' => 'business-config']);

        $service = \Mockery::mock(\App\Services\StrategyLevelReviewService::class)->makePartial();
        $service->shouldReceive('suggest')->once()->andReturn(__('app.strategy_review_failed'));
        $this->app->instance(\App\Services\StrategyLevelReviewService::class, $service);

        $this->post(route('strategy.evaluate'))
            ->assertRedirect(route('strategy.review'))
            ->assertSessionHas('error', __('app.strategy_review_failed'));

        $this->assertSame(5, app(WeeklyWorkPlanService::class)->strategyLevel($team->fresh()));
    }

    public function test_strategy_field_suggestion_uses_the_draft_for_that_field(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $service = \Mockery::mock(StrategyFieldSuggestionService::class, [
            $this->app->make(WeeklyWorkPlanService::class),
        ])->makePartial();
        $service->shouldReceive('suggest')
            ->once()
            ->withArgs(function ($givenTeam, string $field, string $draft, array $siblings) use ($team): bool
            {
                return $givenTeam->is($team)
                    && $field === 'content_strategy'
                    && $draft === 'Publicar un caso por semana'
                    && ($siblings['web'] ?? null) === 'https://idoneo.dev';
            })
            ->andReturn('Un caso semanal para el cliente ideal, publicado en la web y en redes.');
        $this->app->instance(StrategyFieldSuggestionService::class, $service);

        $this->postJson(route('strategy.suggest'), [
            'field' => 'content_strategy',
            'draft' => 'Publicar un caso por semana',
            'siblings' => [
                'web' => 'https://idoneo.dev',
            ],
        ])->assertOk()
            ->assertJsonPath('field', 'content_strategy')
            ->assertJsonPath('suggestion', 'Un caso semanal para el cliente ideal, publicado en la web y en redes.');
    }

    public function test_strategy_field_suggestion_rejects_an_unknown_field(): void
    {
        [$user] = $this->planner();
        $this->actingAs($user);

        $this->postJson(route('strategy.suggest'), [
            'field' => 'not_a_field',
        ])->assertStatus(422);
    }

    public function test_strategy_suggestion_context_starts_with_the_same_level(): void
    {
        [$user, $team] = $this->planner();
        $this->actingAs($user);

        $team->setSetting('business_config', [
            'strategy' => [
                'ideal_client' => 'PYMEs de servicios',
                'offer' => 'Auditoría de 90 días',
                'web' => 'https://idoneo.dev',
                'content_strategy' => 'Nota vieja que no debe entrar',
                'money' => 'Margen del 40%',
            ],
        ], ['type' => 'json', 'group' => 'business-config']);

        $context = app(StrategyFieldSuggestionService::class)->context(
            $team->fresh(),
            'content_strategy',
            'Publicar un caso por semana',
        );

        $this->assertSame(2, $context['level']);
        $this->assertSame('Estrategia contenido', $context['label']);
        $this->assertSame('Publicar un caso por semana', $context['draft']);
        $this->assertSame(['Web', 'Cliente', 'Oferta', 'Dinero'], array_column($context['notes'], 'label'));

        $withSibling = app(StrategyFieldSuggestionService::class)->context(
            $team->fresh(),
            'content_strategy',
            'Publicar un caso por semana',
            ['web' => 'https://nuevo.test'],
        );

        $this->assertSame('https://nuevo.test', $withSibling['notes'][0]['text']);
    }

    public function test_social_advisor_prefers_linkedin_for_b2b_challenge(): void
    {
        [$user, $team] = $this->planner();
        $team->setSetting('business_config', [
            'business_challenge' => 'Cerrar más propuestas B2B este mes',
            'business_industry' => 'Consultoría SaaS',
        ], ['type' => 'json', 'group' => 'business-config']);

        $tip = app(\App\Services\WeeklyPlanSocialChannelAdvisor::class)->recommend($team);

        $this->assertSame('linkedin', $tip['channel']);
        $this->assertStringContainsString('LinkedIn', $tip['label']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function planner(): array
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
        DB::table('list60_statuses')->insert([
            'id' => 1,
            'name' => 'Sin contactar',
            'label_class' => 'bg-label-secondary',
        ]);

        foreach (['mailbox', 'invoices', 'list60'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $key,
                    'icon' => 'box',
                    'description' => $key,
                    'status' => 1,
                ],
            );
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('mailbox');
        $team->enableModule('invoices');
        $team->enableModule('list60');

        return [$user, $team];
    }

    private function contact(User $user, int $teamId, string $name, int $statusId): Contact
    {
        return Contact::withoutGlobalScope('team')->withoutGlobalScope('ownership')->create([
            'team_id' => $teamId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $statusId,
        ]);
    }

    private function invoice(int $teamId, int $enterpriseId, int $status, string $dueDate, string $number): void
    {
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterpriseId,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => $number,
            'date' => '2026-09-01',
            'due_date' => $dueDate,
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => $status,
        ]);
    }
}
