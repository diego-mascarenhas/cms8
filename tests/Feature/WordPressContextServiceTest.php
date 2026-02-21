<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\WordPressSyncPage;
use App\Models\WordPressSyncPost;
use App\Services\WordPressContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_context_returns_content_from_sync_tables_when_data_exists(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('wordpress_url', 'https://example.test');
        $team->setSetting('wordpress_username', 'user');
        $team->setSetting('wordpress_application_password', 'xxxx');

        WordPressSyncPage::withoutGlobalScope('team')->create([
            'team_id'   => $team->id,
            'wp_id'     => 1,
            'title'     => 'Test Page Title',
            'content'   => '<p>Body</p>',
            'status'    => 'publish',
            'synced_at' => now(),
        ]);
        WordPressSyncPost::withoutGlobalScope('team')->create([
            'team_id'   => $team->id,
            'wp_id'     => 10,
            'title'     => 'Test Post',
            'content'   => '<p>Post body</p>',
            'status'    => 'publish',
            'synced_at' => now(),
        ]);

        $service = WordPressContextService::forTeam($team);
        $context = $service->buildContext();

        $this->assertStringContainsString('Test Page Title', $context);
        $this->assertStringContainsString('Test Post', $context);
        $this->assertStringContainsString('Páginas', $context);
        $this->assertStringContainsString('Entradas', $context);
        $this->assertStringContainsString('example.test', $context);
    }

    public function test_build_context_returns_not_configured_message_when_team_has_no_wordpress_settings(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $service = WordPressContextService::forTeam($team);
        $context = $service->buildContext();

        $this->assertStringContainsString('WordPress no está configurado', $context);
    }
}
