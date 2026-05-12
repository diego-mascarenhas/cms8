<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TemplateDuplicateFromMessagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_template_creates_copy_and_redirects_to_editor(): void
    {
        Permission::firstOrCreate(['name' => 'template.store', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.store');
        $user = $user->fresh();

        $this->actingAs($user);

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Newsletter original',
            'status_id' => true,
            'gjs_data' => [
                'html' => '<p>Hello</p>',
                'css' => '',
            ],
        ]);

        $response = $this->from('/')
            ->post(route('template.duplicate', $source->getHashedId()), [
                '_token' => csrf_token(),
                'duplicate_template_name' => 'Newsletter copy name',
            ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('#/template/[^/]+/editor#', $location);
        preg_match('#/template/([^/]+)/editor#', $location, $matches);
        $this->assertArrayHasKey(1, $matches);
        $opened = Template::findByHash($matches[1]);

        $copy = Template::query()->where('id', '!=', $source->id)->first();
        $this->assertNotNull($copy);
        $this->assertNotNull($opened);
        $this->assertSame($copy->id, $opened->id);

        $this->assertSame(2, Template::query()->count());
        $this->assertSame('Newsletter copy name', $copy->name);
        $this->assertSame('<p>Hello</p>', $copy->gjs_data['html'] ?? null);
    }

    public function test_duplicate_template_does_not_copy_other_team_template(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $ownerTeam = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $ownerTeam->id])->save();

        $this->actingAs($owner->fresh());

        $source = Template::create([
            'team_id' => (int) $ownerTeam->id,
            'name' => 'Other team only',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $hashedId = $source->getHashedId();

        $intruder = User::factory()->withPersonalTeam()->create();
        $intruderTeam = $intruder->ownedTeams()->first();
        $intruder->forceFill(['current_team_id' => $intruderTeam->id])->save();

        $this->actingAs($intruder->fresh());

        $response = $this->from('/')
            ->post(route('template.duplicate', $hashedId), [
                '_token' => csrf_token(),
                'duplicate_template_name' => 'Stolen copy',
            ]);

        $response->assertRedirect('/');
        $this->assertSame(1, Template::withoutGlobalScopes()->count());
    }

    public function test_duplicate_template_allowed_with_template_edit_only(): void
    {
        Permission::firstOrCreate(['name' => 'template.edit', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.edit');
        $user = $user->fresh();

        $this->actingAs($user);

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Editable only',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>Hi</p>'],
        ]);

        $response = $this->from('/')
            ->post(route('template.duplicate', $source->getHashedId()), [
                '_token' => csrf_token(),
                'duplicate_template_name' => 'Editable copy',
            ]);

        $response->assertRedirect();
        $this->assertSame(2, Template::query()->count());
    }

    public function test_duplicate_template_allowed_with_message_create_only(): void
    {
        Permission::firstOrCreate(['name' => 'message.create', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('message.create');
        $user = $user->fresh();

        $this->actingAs($user);

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'From composer',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>Body</p>'],
        ]);

        $response = $this->from('/')
            ->post(route('template.duplicate', $source->getHashedId()), [
                '_token' => csrf_token(),
                'duplicate_template_name' => 'Composer copy',
            ]);

        $response->assertRedirect();
        $this->assertSame(2, Template::query()->count());
    }

    public function test_duplicate_with_message_id_assigns_new_template_to_message(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Original tpl',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>Orig</p>'],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Broadcast',
            'type_id' => 1,
            'text' => 'Alt',
            'team_id' => (int) $team->id,
            'template_id' => $source->id,
            'status_id' => false,
        ]);

        $response = $this->from(route('message.edit', $message->id))
            ->post(route('template.duplicate', $source->getHashedId()), [
                '_token' => csrf_token(),
                'duplicate_template_name' => 'Linked copy tpl',
                'message_id' => $message->id,
            ]);

        $response->assertRedirect();
        $copy = Template::query()->where('name', 'Linked copy tpl')->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($source->id, $copy->id);

        $message->refresh();
        $this->assertSame($copy->id, (int) $message->template_id);
    }
}
