<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Assistant\AssistantActorContextService;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantActorContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_with_collaborator_policy_gets_full_assistant_on_whatsapp_channel(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $user = User::factory()->create();
        $user->teams()->attach($team->id, ['role' => 'client']);
        $user->assignRole('collaborator');
        $user->forceFill(['current_team_id' => $team->id])->save();

        $context = app(AssistantActorContextService::class)->resolveForUser(
            $user,
            (int) $team->id,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertFalse($context->limitedToolset);
        $this->assertFalse($context->whatsappInboundCustomerPrompts);

        $tools = app(AssistantToolsService::class);
        $tools->clearRequestContext();
        $tools->setRequestContext($user->id, $team->id, '5491100000001');

        $out = $tools->execute('search_contacts', ['query' => 'test']);
        $this->assertStringNotContainsString('No disponible para tu rol', $out);
    }

    public function test_client_with_calendar_access_uses_operational_prompts_on_whatsapp(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $user = User::factory()->create();
        $user->teams()->attach($team->id, ['role' => 'client']);
        $user->assignRole('client');
        $user->forceFill(['current_team_id' => $team->id])->save();

        $context = app(AssistantActorContextService::class)->resolveForUser(
            $user,
            (int) $team->id,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertFalse($context->limitedToolset);
        $this->assertFalse($context->whatsappInboundCustomerPrompts);
    }

    public function test_admin_on_whatsapp_uses_full_profile_without_customer_prompts(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole('admin');

        $context = app(AssistantActorContextService::class)->resolveForUser(
            $user,
            (int) $team->id,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertFalse($context->limitedToolset);
        $this->assertFalse($context->whatsappInboundCustomerPrompts);
    }

    public function test_guest_without_calendar_gets_customer_assistant_prompts(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $guest = User::factory()->create();
        $guest->teams()->attach($team->id, ['role' => 'guest']);
        $guest->forceFill(['current_team_id' => $team->id])->save();

        $this->assertFalse($guest->can('create', \App\Models\CalendarEvent::class));

        $context = app(AssistantActorContextService::class)->resolveForUser(
            $guest,
            (int) $team->id,
            AssistantActorContextService::CHANNEL_WEB,
        );

        $this->assertTrue($context->limitedToolset);
    }
}
