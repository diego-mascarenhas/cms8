<?php

namespace Tests\Feature\Cms;

use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('admin');

        return $user->refresh();
    }

    public function test_upload_creates_attachment_post_and_stores_file(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = $this->adminWithTeam();

        $response = $this->actingAs($user)->postJson(route('cms.media.store'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('posts', [
            'team_id' => $user->current_team_id,
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg',
        ]);

        $attachment = Post::withoutGlobalScopes()->where('post_type', 'attachment')->first();
        $this->assertNotNull($attachment->getMeta('_humano_file_path'));
        Storage::disk('public')->assertExists($attachment->getMeta('_humano_file_path'));
    }

    public function test_pull_media_item_creates_attachment(): void
    {
        Bus::fake();
        $user = $this->adminWithTeam();
        $team = $user->currentTeam;
        $team->setSetting('wordpress_url', 'https://wp.test');
        $team->setSetting('wordpress_username', 'admin');
        $team->setSetting('wordpress_application_password', 'pass word');

        $service = WordPressContentSyncService::make($team->refresh());

        $applied = $service->pullMediaItem([
            'id' => 321,
            'slug' => 'foto',
            'status' => 'inherit',
            'title' => ['rendered' => 'Foto'],
            'mime_type' => 'image/png',
            'source_url' => 'https://wp.test/wp-content/uploads/foto.png',
            'media_details' => ['sizes' => ['thumbnail' => ['source_url' => 'https://wp.test/wp-content/uploads/foto-150.png']]],
            'modified_gmt' => '2026-06-18T10:00:00',
        ]);

        $this->assertTrue($applied);
        $this->assertDatabaseHas('posts', [
            'team_id' => $team->id,
            'wp_id' => 321,
            'post_type' => 'attachment',
            'post_mime_type' => 'image/png',
        ]);

        $attachment = Post::withoutGlobalScopes()->where('wp_id', 321)->first();
        $this->assertSame('https://wp.test/wp-content/uploads/foto.png', $attachment->guid);
        $this->assertSame('https://wp.test/wp-content/uploads/foto-150.png', $attachment->getMeta('_humano_thumb_url'));
    }

    public function test_push_attachment_uploads_to_wordpress(): void
    {
        Bus::fake();
        Storage::fake('public');
        Http::fake([
            'wp.test/wp-json/' => Http::response(['namespaces' => ['wp/v2']], 200),
            'wp.test/wp-json/wp/v2/media' => Http::response([
                'id' => 999,
                'source_url' => 'https://wp.test/wp-content/uploads/local.png',
                'modified_gmt' => '2026-06-18T12:00:00',
            ], 201),
        ]);

        $user = $this->adminWithTeam();
        $team = $user->currentTeam;
        $team->setSetting('wordpress_url', 'https://wp.test');
        $team->setSetting('wordpress_username', 'admin');
        $team->setSetting('wordpress_application_password', 'pass word');
        $team->setSetting('wordpress_cms_sync_enabled', '1');

        Storage::disk('public')->put('cms-media/'.$team->id.'/local.png', 'binary-data');

        $attachment = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_title' => 'Local',
            'post_mime_type' => 'image/png',
        ]);
        $attachment->setMeta('_humano_file_path', 'cms-media/'.$team->id.'/local.png');

        $service = WordPressContentSyncService::make($team->refresh());
        $this->assertTrue($service->pushAttachment($attachment->fresh('meta')));

        $this->assertSame(999, (int) $attachment->fresh()->wp_id);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/wp-json/wp/v2/media'));
    }

    public function test_destroy_removes_attachment_and_local_file(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = $this->adminWithTeam();

        Storage::disk('public')->put('cms-media/x.png', 'data');
        $attachment = Post::create([
            'team_id' => $user->current_team_id,
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_title' => 'X',
        ]);
        $attachment->setMeta('_humano_file_path', 'cms-media/x.png');

        $this->actingAs($user)->deleteJson(route('cms.media.destroy', $attachment->id))->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing('cms-media/x.png');
    }
}
