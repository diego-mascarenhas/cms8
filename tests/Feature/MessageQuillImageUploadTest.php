<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageQuillImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_message_create_includes_quill_image_upload_config(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('humaMessageTemplateQuillUploadUrl', $html);
        $this->assertStringContainsString('laravel-grapesjs\/asset\/store', $html);
        $this->assertStringContainsString('humaBindMessageTemplateQuillImageUpload', $html);
        $this->assertStringContainsString('humaBindMessageTemplateQuillImageUrlHandler', $html);
        $this->assertStringContainsString("addHandler('image'", $html);
        $this->assertStringContainsString("['link', 'image']", $html);
        $this->assertStringContainsString('imageUrlPrompt', $html);
    }

    public function test_grapesjs_asset_store_uploads_image_to_template_storage_path(): void
    {
        Storage::fake('public');

        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->post(route('laravel-grapesjs.asset.store'), [
            'file' => [
                UploadedFile::fake()->image('hero-banner.jpg'),
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data']);

        $url = $response->json('data.0');
        $this->assertIsString($url);

        // TeamAssetRepository uses templates/default while runningInConsole() (PHPUnit).
        Storage::disk('public')->assertExists('templates/default/hero-banner.jpg');
    }
}
