<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use App\View\Components\TaskNotifications;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemoNotificationsSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoNotificationsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_notifications_seeder_links_contacts_and_seeds_navbar_notifications(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

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
        ]);

        $employee = User::factory()->create([
            'email' => 'sarah.johnson@humano.app',
            'name' => 'Sarah Johnson',
        ]);
        $employee->assignRole('employee');
        $employee->teams()->attach($team->id, ['role' => 'employee']);

        $this->seed(DemoNotificationsSeeder::class);

        $adminContact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'admin@humano.app')
            ->first();

        $this->assertNotNull($adminContact);
        $this->assertSame($admin->id, $adminContact->user_id);

        $sarahContact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'sarah.johnson@humano.app')
            ->first();

        $this->assertNotNull($sarahContact);
        $this->assertSame($employee->id, $sarahContact->user_id);

        $adminNotifications = Notification::withoutGlobalScopes()
            ->forRecipientUser($admin->id)
            ->count();

        $this->assertGreaterThanOrEqual(3, $adminNotifications);

        $this->actingAs($admin);

        $component = new TaskNotifications;
        $this->assertGreaterThanOrEqual(3, $component->notifications->count());
        $this->assertGreaterThanOrEqual(2, $component->unreadCount);
        $this->assertTrue(
            $component->notifications->contains('subject', 'Bienvenido al equipo Demo'),
        );
    }
}
