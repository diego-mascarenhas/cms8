<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use App\Services\PaidAds\PaidAdImageGenerationService;
use App\Support\AiTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdImageGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'paid_ads'],
            [
                'name' => 'Paid Ads',
                'icon' => 'target-arrow',
                'description' => 'Paid advertising campaigns',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('paid_ads');

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-generate')->plainTextToken];
    }

    public function test_generates_an_image_from_the_brief(): void
    {
        [, $team, $token] = $this->adminWithToken();
        Storage::fake('public');
        Image::fake([self::TINY_PNG]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/generate-image', [
                'scene' => 'Una mano sostiene un celular con un chat ordenado.',
                'hook' => 'WhatsApp que ya no se atiende a mano',
                'framing' => 'Cuadrado, sujeto al centro.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['path', 'url', 'original_name', 'data_url']]);

        $this->assertNotEmpty(Storage::disk('public')->allFiles('paid-ads/'.$team->id));
    }

    public function test_scene_is_required(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/generate-image', [
                'hook' => 'Corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scene']);
    }

    public function test_guest_cannot_generate_image(): void
    {
        $this->postJson('/api/paid-ads/generate-image', [
            'scene' => 'Una mano sostiene un celular con un chat ordenado.',
        ])->assertUnauthorized();
    }

    public function test_prompt_includes_scene_and_avoids_writing_the_headline(): void
    {
        $prompt = app(PaidAdImageGenerationService::class)->prompt([
            'scene' => 'Mesa con un celular y un café.',
            'headline' => 'Automatizá',
        ]);

        $this->assertStringContainsString('Mesa con un celular y un café.', $prompt);
        $this->assertStringContainsString('do not write it in the image', $prompt);
        $this->assertStringContainsString('No text', $prompt);
    }

    public function test_image_task_uses_openai_instead_of_unconfigured_gemini(): void
    {
        $this->assertSame('openai', AiTasks::provider('image'));
    }
}
