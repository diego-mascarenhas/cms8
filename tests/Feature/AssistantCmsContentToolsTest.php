<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantCmsContentToolsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUserWithTeam(): array
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        return [$user, $team];
    }

    private function memberUserWithTeam(Team $team): User
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'member']);

        return $user;
    }

    public function test_admin_can_create_list_get_update_and_set_status(): void
    {
        Bus::fake();
        [$user, $team] = $this->adminUserWithTeam();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id);

        $created = $service->execute('create_cms_content', [
            'type' => 'post',
            'title' => 'Guía de bienvenida',
            'content' => '<p>Hola</p>',
            'status' => 'publish',
        ]);
        $this->assertStringContainsString('Se creó la entrada', $created);

        $post = Post::withoutGlobalScopes()->where('team_id', $team->id)->firstWhere('post_title', 'Guía de bienvenida');
        $this->assertNotNull($post);
        $this->assertSame('guia-de-bienvenida', $post->post_name);

        $list = $service->execute('list_cms_content', ['type' => 'post']);
        $this->assertStringContainsString('Guía de bienvenida', $list);

        $get = $service->execute('get_cms_content', ['slug' => 'guia-de-bienvenida']);
        $this->assertStringContainsString('Hola', $get);

        $updated = $service->execute('update_cms_content', ['id' => $post->id, 'title' => 'Guía actualizada']);
        $this->assertStringContainsString('Se actualizó la entrada', $updated);
        $this->assertSame('Guía actualizada', $post->fresh()->post_title);

        $status = $service->execute('set_cms_content_status', ['id' => $post->id, 'status' => 'draft']);
        $this->assertStringContainsString('en borrador', $status);
        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->post_status);

        // Tool outputs are recorded so the chat can fall back to them when the model returns no text.
        $outputs = $service->getToolOutputsInRequest();
        $this->assertNotEmpty($outputs);
        $this->assertStringContainsString('en borrador', (string) end($outputs));
    }

    public function test_member_reads_only_published_and_cannot_manage(): void
    {
        Bus::fake();
        [$admin, $team] = $this->adminUserWithTeam();
        $member = $this->memberUserWithTeam($team);

        Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Publicado visible',
            'post_name' => 'publicado-visible',
            'post_content' => '<p>Público</p>',
        ]);
        $draft = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_DRAFT,
            'post_title' => 'Borrador oculto',
            'post_name' => 'borrador-oculto',
            'post_content' => '<p>Secreto</p>',
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($member->id, $team->id);

        $list = $service->execute('list_cms_content', []);
        $this->assertStringContainsString('Publicado visible', $list);
        $this->assertStringNotContainsString('Borrador oculto', $list);

        $getDraft = $service->execute('get_cms_content', ['id' => $draft->id]);
        $this->assertStringContainsString('No se encontró el contenido', $getDraft);

        $create = $service->execute('create_cms_content', ['title' => 'Intento']);
        $this->assertStringContainsString('No disponible', $create);
        $this->assertDatabaseMissing('posts', ['team_id' => $team->id, 'post_title' => 'Intento']);
    }

    public function test_admin_can_filter_drafts_with_status(): void
    {
        Bus::fake();
        [$user, $team] = $this->adminUserWithTeam();

        Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_DRAFT,
            'post_title' => 'Mi borrador',
            'post_name' => 'mi-borrador',
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id);

        $list = $service->execute('list_cms_content', ['status' => 'draft']);
        $this->assertStringContainsString('Mi borrador', $list);
    }
}
