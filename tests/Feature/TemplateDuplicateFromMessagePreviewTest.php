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
        $this->assertTrue((bool) $copy->status_id);
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

        $response->assertRedirect(route('message.edit', $message->id));
        $copy = Template::query()->where('name', 'Linked copy tpl')->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($source->id, $copy->id);

        $message->refresh();
        $this->assertSame($copy->id, (int) $message->template_id);
        $this->assertTrue((bool) $copy->status_id);
    }

    public function test_duplicate_with_message_id_ignores_untrusted_return_url_and_redirects_to_message_edit(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Tpl unsafe return',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Broadcast',
            'type_id' => 1,
            'text' => 'Alt',
            'team_id' => (int) $team->id,
            'template_id' => $source->id,
            'status_id' => false,
        ]);

        $response = $this->post(route('template.duplicate', $source->getHashedId()), [
            '_token' => csrf_token(),
            'duplicate_template_name' => 'Linked safe return',
            'message_id' => $message->id,
            'return_url' => 'https://evil.example/phish',
        ]);

        $response->assertRedirect(route('message.edit', $message->id));
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/template/', $location);
    }

    public function test_duplicate_merges_template_id_into_message_create_return_url(): void
    {
        Permission::firstOrCreate(['name' => 'template.store', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.store');
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Tpl for create return',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $createUrl = url('/message/create');
        $response = $this->post(route('template.duplicate', $source->getHashedId()), [
            '_token' => csrf_token(),
            'duplicate_template_name' => 'Copy for create flow',
            'return_url' => $createUrl,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $editorQuery);
        $this->assertArrayHasKey('return_url', $editorQuery);
        $decodedReturn = urldecode((string) $editorQuery['return_url']);
        $this->assertStringContainsString('template_id=', $decodedReturn);
        $copy = Template::query()->where('name', 'Copy for create flow')->first();
        $this->assertNotNull($copy);
        $this->assertStringContainsString('template_id='.$copy->id, $decodedReturn);
    }

    public function test_duplicate_redirect_appends_valid_return_url_to_editor(): void
    {
        Permission::firstOrCreate(['name' => 'template.store', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.store');
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Tpl with return',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $response = $this->post(route('template.duplicate', $source->getHashedId()), [
            '_token' => csrf_token(),
            'duplicate_template_name' => 'Tpl copy return',
            'return_url' => '/message/55/edit',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $parts = parse_url($location);
        parse_str($parts['query'] ?? '', $query);
        $this->assertSame('/message/55/edit', $query['return_url'] ?? null);
    }

    public function test_duplicate_with_template_html_saves_content_and_returns_to_return_url(): void
    {
        Permission::firstOrCreate(['name' => 'template.store', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.store');
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Tpl source',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>Original</p>'],
        ]);

        $createUrl = url('/message/create');
        $customHtml = '<h1>From Quill</h1><p>Custom body</p>';

        $response = $this->post(route('template.duplicate', $source->getHashedId()), [
            '_token' => csrf_token(),
            'duplicate_template_name' => 'Quill copy tpl',
            'template_html' => $customHtml,
            'return_url' => $createUrl,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/template/', $location);
        $this->assertStringContainsString('template_id=', $location);

        $copy = Template::query()->where('name', 'Quill copy tpl')->first();
        $this->assertNotNull($copy);
        $this->assertSame($customHtml, $copy->gjs_data['html'] ?? null);
    }

    public function test_duplicate_redirect_ignores_untrusted_return_url(): void
    {
        Permission::firstOrCreate(['name' => 'template.store', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.store');
        $this->actingAs($user->fresh());

        $source = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Tpl no bad return',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $response = $this->post(route('template.duplicate', $source->getHashedId()), [
            '_token' => csrf_token(),
            'duplicate_template_name' => 'Tpl copy safe',
            'return_url' => 'https://evil.example/phish',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('return_url=', $location);
    }
}
