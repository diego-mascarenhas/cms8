<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Support\AssistantCreatedMessageRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantCreatedMessageRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_message_id_from_plain_string_tool_result(): void
    {
        $payload = "Campaign message created: Promo (id: 42). Channel: email.\n"
            .AssistantCreatedMessageRedirect::SENTINEL_LINE_PREFIX.'42';
        $id = AssistantCreatedMessageRedirect::extractCreatedMessageIdFromToolResults([$payload]);
        $this->assertSame(42, $id);
    }

    public function test_extracts_message_id_from_array_shaped_tool_result(): void
    {
        $text = 'Done.'."\n".AssistantCreatedMessageRedirect::SENTINEL_LINE_PREFIX.'7';
        $id = AssistantCreatedMessageRedirect::extractCreatedMessageIdFromToolResults([
            ['result' => $text],
        ]);
        $this->assertSame(7, $id);
    }

    public function test_resolve_returns_null_when_message_belongs_to_other_team(): void
    {
        Permission::firstOrCreate(['name' => 'message.edit']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('message.edit');

        $user = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $user->id]);
        $teamB = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($teamA->id, ['role' => 'admin']);
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $teamA->id])->save();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $teamB->id,
            'name' => 'Other team',
            'type_id' => 1,
            'text' => 'x',
        ]);

        $url = AssistantCreatedMessageRedirect::resolveMessageEditUrlForUser($user->fresh(), (int) $message->id);
        $this->assertNull($url);
    }

    public function test_resolve_returns_edit_url_when_team_matches_and_user_can_edit(): void
    {
        Permission::firstOrCreate(['name' => 'message.edit']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('message.edit');

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'News',
            'type_id' => 1,
            'text' => 'Hello',
        ]);

        $url = AssistantCreatedMessageRedirect::resolveMessageEditUrlForUser($user->fresh(), (int) $message->id);
        $this->assertIsString($url);
        $this->assertStringEndsWith('/message/'.$message->id.'/edit', $url);
    }
}
