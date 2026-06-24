<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoDashboardRichDataSeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoDashboardRichDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dashboard_rich_data_seeder_populates_dashboard_metrics(): void
    {
        $this->seed([
            ContactSentimentSeeder::class,
            ContactStatusSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => 'admin@humano.app',
            'name' => 'Idóneo',
        ]);
        $admin->assignRole('admin');

        $team = $admin->ownedTeams()->create([
            'name' => 'Demo',
            'personal_team' => false,
        ]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        foreach (['contacts', 'performance_insights'] as $key)
        {
            Module::firstOrCreate(
                ['key' => $key],
                ['name' => ucfirst($key), 'is_core' => false],
            );
            $team->enableModule($key);
        }

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'email' => 'solo@cliente.demo',
            'name' => 'Solo',
            'surname' => 'Contact',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);

        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'IDONEO',
            'email' => 'hola@idoneo.dev',
            'type_id' => 1,
            'status_id' => 2,
        ]);

        $this->seed(DemoDashboardRichDataSeeder::class);

        $this->assertGreaterThanOrEqual(120, Contact::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertGreaterThanOrEqual(280, ContactInteraction::query()->whereHas('contact', fn ($q) => $q->where('team_id', $team->id))->count());
        $this->assertGreaterThanOrEqual(100, ContactSentimentHistory::query()->whereHas('contact', fn ($q) => $q->where('team_id', $team->id))->count());

        $contactsWithoutCategory = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereDoesntHave('categories')
            ->count();

        $this->assertSame(0, $contactsWithoutCategory);
        $this->assertGreaterThanOrEqual(4, Category::query()->where('team_id', $team->id)->count());

        $insight = UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $admin->id)
            ->whereDate('insight_date', now()->toDateString())
            ->first();

        $this->assertNotNull($insight);
        $this->assertNotSame('', trim((string) $insight->headline));
        $this->assertNotSame('', trim((string) $insight->message));

        $dailyCounts = [];
        for ($dayOffset = 29; $dayOffset >= 0; $dayOffset--)
        {
            $dayStart = now()->subDays($dayOffset)->startOfDay();
            $dayEnd = $dayStart->copy()->addDay();
            $dailyCounts[] = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('status_id', 1)
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->count();
        }

        $this->assertContains(0, $dailyCounts);
        $this->assertGreaterThanOrEqual(10, max($dailyCounts));

        $recentWeekSum = array_sum(array_slice($dailyCounts, -8));
        $olderWeekSum = array_sum(array_slice($dailyCounts, 0, 8));
        $this->assertGreaterThan($olderWeekSum, $recentWeekSum);

        $finalizadoId = \App\Models\ContactStatus::query()->where('name', 'Finalizado')->value('id') ?? 6;

        $this->assertGreaterThanOrEqual(
            7,
            Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('status_id', $finalizadoId)
                ->count(),
        );

        $clienteId = ContactStatus::query()->where('name', 'Cliente')->value('id') ?? 5;

        $clienteCount = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteId)
            ->count();

        $this->assertLessThanOrEqual(10, $clienteCount);
        $this->assertGreaterThanOrEqual(1, $clienteCount);

        $clientesSinEmpresa = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteId)
            ->whereNull('current_enterprise_id')
            ->count();

        $this->assertSame(0, $clientesSinEmpresa);
    }
}
