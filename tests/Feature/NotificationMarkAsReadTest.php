<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationMarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_recipient_can_mark_notification_as_read_from_navbar(): void
    {
        [$user, $notification] = $this->createRecipientNotification();

        $this->actingAs($user)
            ->patchJson(route('notification.mark-as-read', $notification))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['read_at', 'read_at_formatted']);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_non_recipient_cannot_mark_notification_as_read(): void
    {
        [$user, $notification] = $this->createRecipientNotification();

        $otherUser = User::factory()->create();
        $otherUser->teams()->attach($user->current_team_id, ['role' => 'admin']);
        $otherUser->forceFill(['current_team_id' => $user->current_team_id])->save();

        $this->actingAs($otherUser)
            ->patchJson(route('notification.mark-as-read', $notification))
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_recipient_can_mark_all_notifications_as_read(): void
    {
        [$user, $notification] = $this->createRecipientNotification();

        $this->actingAs($user)
            ->postJson(route('notification.mark-all-as-read'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('marked_count', 1);

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_show_page_marks_notification_as_read_for_recipient(): void
    {
        [$user, $notification] = $this->createRecipientNotification();

        $this->actingAs($user)
            ->get(route('notification.show', $notification))
            ->assertOk();

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    /**
     * @return array{0: User, 1: Notification}
     */
    private function createRecipientNotification(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'email' => $user->email,
            'name' => 'Test',
            'surname' => 'User',
            'phone' => '34600000001',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'user_id' => $user->id,
        ]);

        $type = NotificationType::query()->create([
            'name' => 'General',
            'is_active' => true,
        ]);

        $notification = Notification::query()->create([
            'team_id' => $team->id,
            'type_id' => $type->id,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Test notification',
            'message' => 'Hello',
            'is_read' => false,
        ]);

        return [$user, $notification];
    }
}
