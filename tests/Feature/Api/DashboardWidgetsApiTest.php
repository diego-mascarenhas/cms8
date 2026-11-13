<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\PaymentSync;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widgets_use_team_data(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['projects', 'calendar', 'mailer', 'paid_ads'] as $key)
        {
            $module = Module::query()->create([
                'name' => $key,
                'key' => $key,
                'icon' => null,
                'description' => null,
                'is_core' => false,
                'group' => null,
                'order' => 0,
                'status' => 1,
            ]);
            $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);
        }

        CalendarEvent::query()->create([
            'team_id' => $team->id,
            'title' => 'Reunión con Norte',
            'start' => now()->addDay()->setTime(10, 0),
            'end' => now()->addDay()->setTime(11, 0),
            'all_day' => false,
            'label' => 'Cliente',
        ]);

        ServiceSync::query()->create([
            'stripe_id' => 'sub_expiring_widget',
            'team_id' => $team->id,
            'customer_id' => 'cus_norte',
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'customer_name' => 'Norte Studio',
            'price_currency' => 'eur',
            'amount_total' => 49,
            'current_period_end' => now()->addDays(12),
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'ch_old_card',
            'customer_id' => 'cus_norte',
            'customer_email' => 'norte@example.com',
            'status' => 'succeeded',
            'currency' => 'eur',
            'amount_cents' => 4900,
            'charge_created_at' => now()->subMonth(),
            'raw_payload' => [
                'payment_method_details' => [
                    'card' => [
                        'brand' => 'visa',
                        'funding' => 'credit',
                        'last4' => '0000',
                        'exp_month' => (int) now()->format('n'),
                        'exp_year' => (int) now()->format('Y'),
                    ],
                ],
            ],
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'ch_expiring_card',
            'customer_id' => 'cus_norte',
            'customer_email' => 'norte@example.com',
            'status' => 'succeeded',
            'currency' => 'eur',
            'amount_cents' => 4900,
            'charge_created_at' => now(),
            'raw_payload' => [
                'payment_method_details' => [
                    'card' => [
                        'brand' => 'visa',
                        'funding' => 'credit',
                        'last4' => '4242',
                        'exp_month' => (int) now()->format('n'),
                        'exp_year' => (int) now()->format('Y'),
                    ],
                ],
            ],
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'ch_later_card',
            'customer_id' => 'cus_later',
            'status' => 'succeeded',
            'currency' => 'eur',
            'amount_cents' => 1000,
            'charge_created_at' => now(),
            'raw_payload' => [
                'payment_method_details' => [
                    'card' => [
                        'brand' => 'mastercard',
                        'funding' => 'debit',
                        'last4' => '9999',
                        'exp_month' => (int) now()->addYears(3)->format('n'),
                        'exp_year' => (int) now()->addYears(3)->format('Y'),
                    ],
                ],
            ],
        ]);

        $token = $user->createToken('dashboard-widgets')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('widgets.projects', [])
            ->assertJsonPath('widgets.meetings.0.title', 'Reunión con Norte')
            ->assertJsonPath('widgets.meetings.0.label', 'Cliente')
            ->assertJsonCount(1, 'widgets.expiring')
            ->assertJsonPath('widgets.expiring.0.customer_name', 'Norte Studio')
            ->assertJsonPath('widgets.expiring.0.last4', '4242')
            ->assertJsonPath('widgets.expiring.0.funding', 'credit')
            ->assertJsonPath('widgets.expiring.0.brand', 'visa')
            ->assertJsonCount(3, 'widgets.payments')
            ->assertJsonPath('widgets.payments.0.last4', '9999')
            ->assertJsonPath('widgets.payments.0.amount', '10,00 EUR')
            ->assertJsonPath('widgets.payments.1.last4', '4242')
            ->assertJsonPath('widgets.payments.1.customer_name', 'Norte Studio')
            ->assertJsonPath('widgets.payments.1.amount', '49,00 EUR')
            ->assertJsonPath('widgets.payments.2.last4', '0000')
            ->assertJsonPath('widgets.sentiment.0.id', 1)
            ->assertJsonPath('widgets.sentiment.0.label', 'Muy Negativo')
            ->assertJsonPath('widgets.sentiment.0.emoji', '😡')
            ->assertJsonPath('widgets.sentiment.4.id', 5)
            ->assertJsonPath('widgets.sentiment.4.emoji', '🥳')
            ->assertJsonCount(5, 'widgets.sentiment')
            ->assertJsonPath('widgets.mailer.sent_total', 0)
            ->assertJsonPath('widgets.mailer.period_label', 'Últimas 8 semanas')
            ->assertJsonCount(8, 'widgets.mailer.labels')
            ->assertJsonCount(8, 'widgets.mailer.dates')
            ->assertJsonCount(8, 'widgets.mailer.sent')
            ->assertJsonCount(7, 'widgets.ads.clicks')
            ->assertJsonCount(12, 'widgets.income.amounts')
            ->assertJsonCount(12, 'widgets.income.expenses')
            ->assertJsonPath('widgets.income.period_label', (string) now()->year)
            ->assertJsonPath('widgets.income.total', 0)
            ->assertJsonPath('widgets.income.expense_total', 0)
            ->assertJsonPath('widgets.income.currency', 'EUR')
            ->assertJsonPath('widgets.income.metrics.mbr', 0)
            ->assertJsonPath('widgets.income.metrics.monthly_expense', 0)
            ->assertJsonPath('widgets.income.metrics.monthly_profit', 0)
            ->assertJsonPath('widgets.income.metrics.margin_percent', 0)
            ->assertJsonPath('widgets.income.metrics.ytd_profit', 0)
            ->assertJsonPath('widgets.income.metrics.avg_monthly_income', 0)
            ->assertJsonPath('widgets.income.metrics.avg_monthly_expense', 0)
            ->assertJsonPath('widgets.income.metrics.avg_monthly_profit', 0)
            ->assertJsonPath('widgets.analytics', null)
            ->assertJsonPath('widgets.drafts', null)
            ->assertJsonCount(0, 'widgets.audience')
            ->assertJsonCount(6, 'widgets.intents')
            ->assertJsonPath('widgets.intents.0.key', 'buy')
            ->assertJsonPath('widgets.intents.0.count', 0)
            ->assertJsonPath('widgets.intents.5.key', 'unclear')
            ->assertJsonPath('follow_up.enabled', false);
    }

    public function test_dashboard_follow_up_splits_overdue_today_and_upcoming(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'List 60',
            'key' => 'list60',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        $lead = ContactStatus::query()->where('name', 'Lead')->firstOrFail();
        $client = ContactStatus::query()->where('name', 'Cliente')->firstOrFail();

        $overdue = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
            'status_id' => $lead->id,
        ]);
        $today = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Luis',
            'surname' => 'Paz',
            'status_id' => $lead->id,
        ]);
        $later = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Marta',
            'surname' => 'Ríos',
            'status_id' => $client->id,
        ]);

        $otherTeam = Team::factory()->create(['user_id' => $user->id]);
        $other = Contact::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Otra',
            'surname' => 'Empresa',
        ]);

        $called = List60Status::query()->where('name', '1 Contacto')->firstOrFail();
        $silent = List60Status::query()->where('name', 'Sin respuesta')->firstOrFail();

        List60::query()->create([
            'contact_id' => $overdue->id,
            'type_id' => 1,
            'date_next' => now()->startOfWeek()->subDay()->toDateString(),
            'status_id' => $called->id,
            'responsible_id' => $user->id,
        ]);
        List60::query()->create([
            'contact_id' => $today->id,
            'type_id' => 1,
            'date_next' => now()->toDateString(),
            'status_id' => $silent->id,
            'responsible_id' => $user->id,
        ]);
        List60::query()->create([
            'contact_id' => $later->id,
            'type_id' => 1,
            'date_next' => now()->startOfWeek()->addWeek()->toDateString(),
            'status_id' => $called->id,
            'responsible_id' => $user->id,
        ]);
        $sentimentId = DB::table('contact_sentiments')->value('id');

        if ($sentimentId === null)
        {
            $sentimentId = DB::table('contact_sentiments')->insertGetId(['name' => 'Neutral']);
        }

        ContactSentimentHistory::query()->create([
            'contact_id' => $overdue->id,
            'sentiment_id' => $sentimentId,
            'intent_id' => ContactIntent::query()->where('key', 'unclear')->value('id'),
            'notes' => 'Poco clara',
        ]);
        ContactSentimentHistory::query()->create([
            'contact_id' => $today->id,
            'sentiment_id' => $sentimentId,
            'intent_id' => ContactIntent::query()->where('key', 'buy')->value('id'),
            'notes' => 'Quiere comprar',
        ]);
        ContactSentimentHistory::query()->create([
            'contact_id' => $today->id,
            'sentiment_id' => $sentimentId,
            'intent_id' => ContactIntent::query()->where('key', 'unclear')->value('id'),
            'notes' => 'La última manda',
        ]);
        ContactSentimentHistory::query()->create([
            'contact_id' => $later->id,
            'sentiment_id' => $sentimentId,
            'intent_id' => ContactIntent::query()->where('key', 'buy')->value('id'),
            'notes' => 'Quiere comprar',
        ]);

        List60::query()->create([
            'contact_id' => $other->id,
            'type_id' => 1,
            'date_next' => now()->toDateString(),
            'status_id' => $called->id,
            'responsible_id' => $user->id,
        ]);

        $token = $user->createToken('dashboard-follow-up')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('follow_up.enabled', true)
            ->assertJsonPath('follow_up.counts.overdue', 1)
            ->assertJsonPath('follow_up.counts.today', 1)
            ->assertJsonPath('follow_up.counts.upcoming', 1)
            ->assertJsonPath('follow_up.counts.total', 3)
            ->assertJsonPath('follow_up.due.0.contact.name', 'Ana García')
            ->assertJsonPath('follow_up.due.0.when', 'overdue')
            ->assertJsonPath('follow_up.due.1.contact.name', 'Luis Paz')
            ->assertJsonPath('follow_up.due.1.when', 'today')
            ->assertJsonPath('follow_up.upcoming.0.contact.name', 'Marta Ríos')
            ->assertJsonPath('follow_up.statuses.0.name', '1 Contacto')
            ->assertJsonPath('follow_up.statuses.0.count', 2)
            ->assertJsonPath('follow_up.statuses.1.name', 'Sin respuesta')
            ->assertJsonPath('follow_up.statuses.1.count', 1)
            ->assertJsonCount(8, 'follow_up.weeks')
            ->assertJsonPath('follow_up.weeks.0.count', 1)
            ->assertJsonPath('follow_up.weeks.1.count', 1)
            ->assertJsonCount(2, 'widgets.audience')
            ->assertJsonPath('widgets.audience.0.name', 'Lead')
            ->assertJsonPath('widgets.audience.0.count', 2)
            ->assertJsonPath('widgets.audience.0.label_class', 'bg-label-success')
            ->assertJsonPath('widgets.audience.1.name', 'Cliente')
            ->assertJsonPath('widgets.audience.1.count', 1)
            ->assertJsonPath('widgets.audience.1.label_class', 'bg-label-primary')
            ->assertJsonPath('widgets.intents.0.key', 'buy')
            ->assertJsonPath('widgets.intents.0.count', 1)
            ->assertJsonPath('widgets.intents.5.key', 'unclear')
            ->assertJsonPath('widgets.intents.5.count', 2);
    }

    public function test_draft_widget_links_the_billed_team_to_its_enterprise(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('root');

        $billed = Team::factory()->create([
            'name' => 'REVISION ALPHA S.L.',
            'stripe_id' => 'cus_revision',
        ]);
        $named = Team::factory()->create([
            'name' => 'IDONEO',
            'stripe_id' => null,
        ]);
        $stripeEnterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Otra razón social',
            'code' => 'cus_revision',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $idoneo = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Idoneo',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        TeamUsageInvoice::factory()->create([
            'team_id' => $billed->id,
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 1500,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_draft_link',
            'period_to' => now()->startOfMonth(),
        ]);
        TeamUsageInvoice::factory()->create([
            'team_id' => $named->id,
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 700,
            'currency' => 'EUR',
            'stripe_invoice_id' => 'in_draft_name',
            'period_to' => now()->subMonth()->startOfMonth(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('dashboard')->plainTextToken)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonFragment([
                'team_name' => 'REVISION ALPHA S.L.',
                'enterprise_id' => $stripeEnterprise->id,
            ])
            ->assertJsonFragment([
                'team_name' => 'IDONEO',
                'enterprise_id' => $idoneo->id,
            ]);
    }

    public function test_dashboard_includes_today_performance_insight_for_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'performance_insights',
            'key' => 'performance_insights',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        UserDailyPerformanceInsight::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'insight_date' => now()->toDateString(),
            'headline' => 'Impulso',
            'focus' => 'Prioridad de hoy buzón',
            'message' => 'Actualiza tu dossier comercial con un seguimiento concreto antes del mediodía.',
            'performance_ratio' => 0.42,
            'context_snapshot' => [
                'highlights' => ['99 correos sin leer en el buzón'],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('dashboard')->plainTextToken)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('performance_insight.headline', 'Impulso')
            ->assertJsonPath('performance_insight.focus', 'Prioridad de hoy buzón')
            ->assertJsonPath('performance_insight.actions.0.label', '99 correos sin leer en el buzón');
    }

    public function test_dashboard_crm_scope_skips_heavy_sections(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['calendar', 'mailer', 'list60', 'paid_ads'] as $key)
        {
            $module = Module::query()->create([
                'name' => $key,
                'key' => $key,
                'icon' => null,
                'description' => null,
                'is_core' => false,
                'group' => null,
                'order' => 0,
                'status' => 1,
            ]);
            $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$user->createToken('dashboard')->plainTextToken)
            ->getJson('/api/dashboard?scope=crm')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('scope', 'crm')
            ->assertJsonMissingPath('mail')
            ->assertJsonMissingPath('chat')
            ->assertJsonMissingPath('contact_stats')
            ->assertJsonMissingPath('today_contacts')
            ->assertJsonMissingPath('notifications')
            ->assertJsonMissingPath('widgets.projects')
            ->assertJsonMissingPath('widgets.mailer')
            ->assertJsonMissingPath('widgets.meetings')
            ->assertJsonMissingPath('widgets.ads')
            ->assertJsonMissingPath('widgets.expiring')
            ->assertJsonMissingPath('widgets.payments')
            ->assertJsonStructure([
                'widgets' => ['income', 'analytics', 'drafts', 'sentiment', 'audience', 'intents'],
                'follow_up' => ['enabled', 'counts'],
                'performance_insight',
            ]);

        $this->assertSame([], $response->json('follow_up.due'));
        $this->assertSame([], $response->json('follow_up.upcoming'));
    }

    public function test_dashboard_falls_back_to_latest_performance_insight_when_today_missing(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'performance_insights',
            'key' => 'performance_insights',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        UserDailyPerformanceInsight::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'insight_date' => now()->subDays(3)->toDateString(),
            'headline' => 'Impulso',
            'focus' => 'Prioridad de hoy buzón',
            'message' => 'Actualiza tu dossier comercial con un seguimiento concreto antes del mediodía.',
            'performance_ratio' => 0.40,
            'context_snapshot' => [
                'highlights' => ['99 correos sin leer en el buzón'],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('dashboard')->plainTextToken)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('performance_insight.headline', 'Impulso')
            ->assertJsonPath('performance_insight.focus', 'Prioridad de hoy buzón')
            ->assertJsonPath('performance_insight.message', 'Actualiza tu dossier comercial con un seguimiento concreto antes del mediodía.');
    }
}
