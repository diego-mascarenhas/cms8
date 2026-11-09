<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Module;
use App\Models\PaymentSync;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('widgets.sentiment.0.emoji', '😡')
            ->assertJsonPath('widgets.sentiment.4.emoji', '🥳')
            ->assertJsonCount(5, 'widgets.sentiment')
            ->assertJsonPath('widgets.mailer.sent_total', 0)
            ->assertJsonPath('widgets.mailer.period_label', 'Últimas 8 semanas')
            ->assertJsonCount(8, 'widgets.mailer.labels')
            ->assertJsonCount(8, 'widgets.mailer.dates')
            ->assertJsonCount(8, 'widgets.mailer.sent')
            ->assertJsonCount(7, 'widgets.ads.clicks')
            ->assertJsonCount(8, 'widgets.income.amounts')
            ->assertJsonPath('widgets.income.total', 0)
            ->assertJsonPath('widgets.income.currency', 'EUR')
            ->assertJsonPath('widgets.drafts', null)
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

        $overdue = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
        ]);
        $today = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Luis',
            'surname' => 'Paz',
        ]);
        $later = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Marta',
            'surname' => 'Ríos',
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
            'date_next' => now()->subDays(2)->toDateString(),
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
            'date_next' => now()->addDays(4)->toDateString(),
            'status_id' => $called->id,
            'responsible_id' => $user->id,
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
            ->assertJsonPath('follow_up.weeks.0.count', 2)
            ->assertJsonPath('follow_up.weeks.1.count', 1);
    }
}
