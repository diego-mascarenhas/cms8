<?php

namespace Tests\Feature\Api;

use App\Jobs\GeneratePaidAdVideoJob;
use App\Models\Module;
use App\Models\User;
use App\Services\PaidAds\PaidAdCreativeAssetService;
use App\Services\PaidAds\PaidAdVideoComposer;
use App\Services\PaidAds\PaidAdVideoGenerationService;
use App\Services\PaidAds\PaidAdVideoScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Laravel\Ai\Image;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdVideoApiTest extends TestCase
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

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-video')->plainTextToken];
    }

    /**
     * @return array{headline: string, body: string, voice: string, scenes: array<int, array{caption: string, spoken: string, visual: string}>}
     */
    private function payload(): array
    {
        return [
            'headline' => 'Seguí cada venta',
            'body' => 'Dejá de anotar seguimientos en un papel. Pedí una demo.',
            'voice' => 'female',
            'scenes' => [
                [
                    'caption' => '¿Cuánto ganaste?',
                    'spoken' => 'Si vendiste tres autos esta semana, ¿cuánto te quedó de verdad?',
                    'visual' => 'A salesperson in a bright dealership looking at a phone.',
                ],
                [
                    'caption' => 'Echó tres horas',
                    'spoken' => 'El seguimiento a mano se come la tarde y se pierde el cliente.',
                    'visual' => 'A cluttered desk with a notebook and a set of car keys.',
                ],
                [
                    'caption' => 'Lo vendiste',
                    'spoken' => 'Con el tablero al día, el próximo llamado sale solo. Pedí la demo.',
                    'visual' => 'A clean laptop dashboard in a bright car showroom.',
                ],
            ],
        ];
    }

    public function test_suggests_a_three_scene_script(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->mock(PaidAdVideoScriptService::class, function ($mock)
        {
            $mock->shouldReceive('suggest')
                ->once()
                ->andReturn([
                    'headline' => 'Seguí cada venta',
                    'body' => 'Pedí una demo.',
                    'scenes' => $this->payload()['scenes'],
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-video', [
                'goal' => 'Quiero un reel para concesionarios que pierden ventas por hacer el seguimiento a mano.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scenes.0.caption', '¿Cuánto ganaste?')
            ->assertJsonCount(3, 'data.scenes');
    }

    public function test_goal_is_required(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-video', [
                'goal' => 'corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['goal']);
    }

    public function test_guest_cannot_generate_a_video(): void
    {
        $this->postJson('/api/paid-ads/generate-video', $this->payload())
            ->assertUnauthorized();
    }

    public function test_generate_queues_a_story_reel(): void
    {
        [, $team, $token] = $this->adminWithToken();
        Bus::fake();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/generate-video', $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'queued');

        Bus::assertDispatchedAfterResponse(GeneratePaidAdVideoJob::class, function (GeneratePaidAdVideoJob $job) use ($team)
        {
            return $job->teamId === $team->id
                && $job->payload['scenes'][0]['caption'] === '¿Cuánto ganaste?';
        });
    }

    public function test_voice_id_must_look_like_an_elevenlabs_id(): void
    {
        [, , $token] = $this->adminWithToken();

        $payload = $this->payload();
        $payload['voice_id'] = 'no es un id';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/generate-video', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['voice_id']);
    }

    public function test_missing_video_returns_not_found(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/videos/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_parse_keeps_three_scenes_and_strips_the_caption_period(): void
    {
        $parsed = app(PaidAdVideoScriptService::class)->parse(<<<'JSON'
```json
{"headline":"Seguí cada venta.","body":"Pedí una demo.","scenes":[
  {"caption":"¿Cuánto ganaste?.","spoken":"Si vendiste tres autos esta semana, ¿cuánto te quedó de verdad?","visual":"A salesperson in a bright dealership looking at a phone."},
  {"caption":"Echó tres horas","spoken":"El seguimiento a mano se come la tarde y se pierde el cliente.","visual":"A cluttered desk with a notebook and a set of car keys."},
  {"caption":"Lo vendiste","spoken":"Con el tablero al día, el próximo llamado sale solo. Pedí la demo.","visual":"A clean laptop dashboard in a bright car showroom."},
  {"caption":"Sobra","spoken":"Esta escena no entra en el reel de quince segundos.","visual":"An extra scene that should be discarded from the script."}
]}
```
JSON);

        $this->assertSame('¿Cuánto ganaste?', $parsed['scenes'][0]['caption']);
        $this->assertCount(3, $parsed['scenes']);
        $this->assertSame('Seguí cada venta.', $parsed['headline']);
    }

    public function test_parse_rejects_a_short_script(): void
    {
        $this->expectException(\RuntimeException::class);

        app(PaidAdVideoScriptService::class)->parse(json_encode([
            'headline' => 'Hola',
            'body' => 'Un texto.',
            'scenes' => [
                [
                    'caption' => 'Solo',
                    'spoken' => 'Una sola frase no alcanza para el reel.',
                    'visual' => 'A single unfinished scene in a showroom.',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function test_render_stores_a_story_video_from_stills_and_voice(): void
    {
        [, $team] = $this->adminWithToken();
        Storage::fake('public');
        Image::fake([self::TINY_PNG, self::TINY_PNG, self::TINY_PNG]);
        Audio::fake();

        $videoId = (string) Str::uuid();
        $this->mock(PaidAdVideoComposer::class, function ($mock)
        {
            $mock->shouldReceive('compose')->once()->withArgs(function (array $scenes, string $output): bool
            {
                if (count($scenes) !== 3)
                {
                    return false;
                }

                foreach ($scenes as $scene)
                {
                    if (! is_file($scene['image']) || ! is_file($scene['audio']) || $scene['caption'] === '')
                    {
                        return false;
                    }
                }

                return file_put_contents($output, 'fake-mp4') !== false;
            });
        });

        app(PaidAdVideoGenerationService::class)->render($team->id, $videoId, $this->payload());

        $status = app(PaidAdVideoGenerationService::class)->status($team->id, $videoId);
        $this->assertSame('ready', $status['status']);
        $this->assertSame('story', $status['asset']['format']);
        $this->assertSame('video', $status['asset']['media']);
        $this->assertSame(1080, $status['asset']['width']);
        $this->assertSame(1920, $status['asset']['height']);
        Storage::disk('public')->assertExists($status['asset']['path']);
        $this->assertDirectoryDoesNotExist(sys_get_temp_dir().'/paid-ad-video-'.$videoId);
    }

    public function test_story_mp4_is_reported_as_video(): void
    {
        $formatted = app(PaidAdCreativeAssetService::class)->formatCreative([
            'assets' => [[
                'format' => 'story',
                'path' => 'paid-ads/1/reel.mp4',
                'width' => 1080,
                'height' => 1920,
            ]],
        ]);

        $this->assertSame('video', $formatted['assets'][0]['media']);
    }
}
