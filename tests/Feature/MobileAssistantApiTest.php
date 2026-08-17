<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Email;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Message;
use App\Models\Module;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\TeamModulesByPricingPlanSyncer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileAssistantApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedModulesFromPricingConfig(): void
    {
        $keys = array_values(array_unique(array_merge(
            config('humano_pricing.plan_team_modules.assistant', []),
            config('humano_pricing.plan_team_modules.business', []),
            config('humano_pricing.plan_team_modules.mentor', []),
        )));

        foreach ($keys as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst(str_replace('-', ' ', $key)),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }
    }

    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seedModulesFromPricingConfig();
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'assistant');

        $token = $user->createToken('mobile-test')->plainTextToken;

        return [$user, $team, $token];
    }

    public function test_menu_returns_enabled_modules_for_assistant_team(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        app()->setLocale('es');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/menu');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $enabled = $response->json('enabled_modules');
        $this->assertIsArray($enabled);
        $this->assertContains('today', $enabled);
        $this->assertContains('contacts', $enabled);
        $this->assertNotContains('clients', $enabled);
        $this->assertContains('tasks', $enabled);
        $this->assertContains('chat', $enabled);
        $this->assertNotContains('projects', $enabled);
        $this->assertNotContains('invoices', $enabled);

        $menuNames = collect($response->json('menu'))
            ->where('type', 'item')
            ->pluck('name')
            ->all();
        $this->assertContains('Panel', $menuNames);
        $this->assertContains('Hoy', $menuNames);
        $this->assertContains('Contactos', $menuNames);
        $this->assertContains('Tareas', $menuNames);
    }

    public function test_auth_user_returns_role_label(): void
    {
        [$user, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/user');

        $response->assertOk();
        $response->assertJsonPath('role', 'Admin');
        $response->assertJsonPath('current_team.is_owner', true);
        $response->assertJsonPath('current_team.can_manage', true);
    }

    public function test_auth_user_marks_non_owner_team_member(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('current_team.is_owner', true);

        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('id', $member->id)
            ->assertJsonPath('current_team.id', $team->id)
            ->assertJsonPath('current_team.is_owner', false)
            ->assertJsonPath('current_team.can_manage', true);
    }

    public function test_auth_user_marks_collaborator_as_unable_to_manage_team(): void
    {
        [, $team] = $this->assistantUserWithToken();
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('collaborator');

        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('current_team.is_owner', false)
            ->assertJsonPath('current_team.can_manage', false);
    }

    public function test_today_endpoint_returns_events_and_tasks(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Morning standup',
            'start' => now()->setTime(9, 0),
            'end' => now()->setTime(9, 30),
            'all_day' => false,
            'label' => 'Business',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/today');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['date', 'events', 'tasks', 'running_task']);
        $this->assertNotEmpty($response->json('events'));

        $dated = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/today?date='.now()->toDateString());

        $dated->assertOk();
        $dated->assertJsonPath('success', true);
        $dated->assertJsonPath('date', now()->toDateString());
    }

    public function test_clients_endpoint_requires_clients_module(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $team->disableModule('clients');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/clients');

        $response->assertStatus(403);
    }

    public function test_emails_endpoint_returns_inbox_messages(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        Email::factory()->create([
            'team_id' => $team->id,
            'subject' => 'Welcome inbox',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/emails?folder=inbox');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));
        $response->assertJsonStructure(['folder_counts', 'pagination']);
    }

    public function test_message_endpoint_returns_campaign_list_like_web(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Newsletter Mayo',
            'type_id' => 1,
            'text' => 'Hola',
            'status_id' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message?search=Mayo');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'name',
                    'status' => ['key', 'label'],
                    'progress',
                ],
            ],
            'pagination',
        ]);
        $this->assertSame('Newsletter Mayo', $response->json('data.0.name'));
        $this->assertSame('paused', $response->json('data.0.status.key'));
    }

    public function test_message_show_returns_campaign_detail(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Detalle campaña',
            'type_id' => 1,
            'text' => 'Cuerpo',
            'status_id' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/message/'.$message->id);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Detalle campaña');
        $response->assertJsonStructure([
            'data' => [
                'stats' => [
                    'subscribers',
                    'sent',
                    'delivered',
                    'opened',
                    'clicks',
                    'failed',
                    'open_rate',
                ],
                'sender' => ['from_name', 'from_address', 'configured'],
                'category_label',
                'contact_status_label',
                'stats_updated_at_label',
                'status',
            ],
        ]);
    }

    public function test_dashboard_returns_today_contacts_when_list60_enabled(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'List 60',
                'icon' => 'list',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('list60');
        $this->seed([
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'García',
        ]);

        $status = List60Status::query()->first();

        List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->startOfDay(),
            'status_id' => $status->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('today_contacts.count', 1);
        $response->assertJsonPath('today_contacts.items.0.contact.name', 'Ana García');
    }

    public function test_notifications_inbox_for_linked_contact_user(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $type = NotificationType::query()->firstOrCreate(
            ['name' => 'General Message'],
            ['description' => 'Test', 'status' => 1],
        );

        Notification::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => $type->id,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => 'Mobile alert',
            'message' => 'Hello from Humano',
            'is_sent' => true,
            'sent_at' => now(),
            'is_read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(1, $response->json('unread_count'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_auth_user_includes_phone_and_can_update_profile(): void
    {
        [$user, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/profile', [
                'name' => 'Mobile Updated',
                'email' => $user->email,
                'phone' => '600111222',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.name', 'Mobile Updated');
        $response->assertJsonPath('user.phone', '600111222');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Mobile Updated',
        ]);
    }

    public function test_auth_user_can_update_password(): void
    {
        [$user, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password-9',
                'password_confirmation' => 'new-password-9',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password-9', $user->fresh()->password),
        );
    }

    public function test_auth_user_can_update_password_without_current_password(): void
    {
        [$user, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/password', [
                'password' => 'new-password-9',
                'password_confirmation' => 'new-password-9',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password-9', $user->fresh()->password),
        );
    }

    public function test_billing_show_returns_plan_and_usage_for_team(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();

        $team->setSetting('email_plan', 'free', ['type' => 'string', 'group' => 'email']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/billing');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.team.id', $team->id);
        $response->assertJsonPath('data.email_plan.key', 'free');
        $response->assertJsonStructure([
            'data' => [
                'team',
                'email_plan',
                'usage',
                'billing',
            ],
        ]);
    }
}
