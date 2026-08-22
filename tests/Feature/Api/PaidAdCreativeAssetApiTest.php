<?php

namespace Tests\Feature\Api;

use App\Enums\AdCreativeFormat;
use App\Enums\PaidAdObjective;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdCreativeAssetApiTest extends TestCase
{
    use RefreshDatabase;

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

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-assets')->plainTextToken];
    }

    public function test_can_upload_and_attach_creative_asset(): void
    {
        Storage::fake('public');

        [, $team, $token] = $this->adminWithToken();

        $upload = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/paid-ads/assets', [
                'format' => AdCreativeFormat::Square->value,
                'file' => UploadedFile::fake()->image('feed.jpg', 1080, 1080),
            ], ['Accept' => 'application/json']);

        $upload->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', AdCreativeFormat::Square->value);

        $path = (string) $upload->json('data.path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('paid-ads/'.$team->id.'/', $path);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads', [
                'name' => 'Campaña con pieza',
                'objective' => PaidAdObjective::Traffic->value,
                'budget_type' => 'daily',
                'currency' => 'EUR',
                'creative' => [
                    'headline' => 'Oferta',
                    'assets' => [
                        [
                            'format' => $upload->json('data.format'),
                            'path' => $path,
                            'url' => $upload->json('data.url'),
                            'width' => $upload->json('data.width'),
                            'height' => $upload->json('data.height'),
                        ],
                    ],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.creative.assets.0.path', $path)
            ->assertJsonPath('data.creative.assets.0.format', AdCreativeFormat::Square->value);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/paid-ads/assets', ['path' => $path])
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_lookups_include_creative_formats(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/lookups')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'formats' => [
                        ['key', 'label', 'ratio', 'width', 'height', 'platforms'],
                    ],
                ],
            ]);
    }

    public function test_cannot_delete_asset_from_another_team(): void
    {
        Storage::fake('public');

        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/paid-ads/assets', ['path' => 'paid-ads/999/secret.jpg'])
            ->assertStatus(422);
    }

    public function test_upload_requires_an_image(): void
    {
        Storage::fake('public');

        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/paid-ads/assets', [
                'format' => AdCreativeFormat::Story->value,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
