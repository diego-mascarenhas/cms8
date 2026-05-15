<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use App\View\Components\TaskNotifications;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavbarNotificationsDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_navbar_shows_only_notifications_for_authenticated_user_contact(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $otherUser = User::factory()->create();
        $otherUser->teams()->attach($team->id, ['role' => 'admin']);

        $myContact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $otherContact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $otherUser->id,
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
        ]);

        $type = NotificationType::query()->create([
            'name' => 'General',
            'is_active' => true,
        ]);

        $mine = Notification::query()->create([
            'team_id' => $team->id,
            'type_id' => $type->id,
            'contact_id' => $myContact->id,
            'user_id' => $otherUser->id,
            'subject' => 'Notificación para mí',
            'message' => 'Mensaje personal',
        ]);

        Notification::query()->create([
            'team_id' => $team->id,
            'type_id' => $type->id,
            'contact_id' => $otherContact->id,
            'user_id' => $user->id,
            'subject' => 'Notificación de otro',
            'message' => 'No debe aparecer',
        ]);

        $this->actingAs($user);

        $component = new TaskNotifications;
        $this->assertCount(1, $component->notifications);
        $this->assertSame($mine->id, $component->notifications->first()->id);
        $this->assertSame(1, $component->unreadCount);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Notificación para mí', false);
        $response->assertDontSee('Notificación de otro', false);
        $response->assertDontSee('Diseño de vistas y componentes', false);
    }
}
