<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Email;
use App\Models\Module;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoPerformanceInsightsSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoPerformanceInsightsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_performance_insights_seeder_creates_emails_insight_and_notification(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => 'admin@humano.app',
            'name' => 'Admin Demo',
        ]);
        $admin->assignRole('admin');

        $team = $admin->ownedTeams()->create([
            'name' => 'Demo',
            'personal_team' => false,
        ]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'email' => 'admin@humano.app',
            'name' => 'Admin',
            'surname' => 'Demo',
            'phone' => '34613194131',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $admin->id,
        ]);

        Module::firstOrCreate(
            ['key' => 'performance_insights'],
            ['name' => 'Team performance insights', 'is_core' => false],
        );
        Module::firstOrCreate(
            ['key' => 'mailbox'],
            ['name' => 'Mailbox', 'is_core' => false],
        );
        Module::firstOrCreate(
            ['key' => 'chat'],
            ['name' => 'Chat', 'is_core' => false],
        );

        $this->seed(DemoPerformanceInsightsSeeder::class);

        $team->refresh();

        $this->assertTrue($team->hasModule('performance_insights'));
        $this->assertTrue($team->hasModule('mailbox'));
        $this->assertGreaterThanOrEqual(8, Email::query()->where('team_id', $team->id)->count());

        $insight = UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $admin->id)
            ->whereDate('insight_date', now()->toDateString())
            ->first();

        $this->assertNotNull($insight);
        $this->assertNotSame('', trim((string) $insight->headline));
        $this->assertIsArray($insight->context_snapshot);
        $this->assertArrayHasKey('highlights', $insight->context_snapshot);

        $notification = Notification::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('reference', $insight->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame((string) $insight->headline, $notification->subject);
        $this->assertArrayHasKey('action_url', $notification->metadata ?? []);
    }
}
