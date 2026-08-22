<?php

namespace Tests\Feature\Api;

use App\Jobs\LoadTeamBusinessInsightsJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessProfileApiTest extends TestCase
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

        return [$user, $team->fresh(), $user->createToken('idoneo-business')->plainTextToken];
    }

    public function test_guest_cannot_read_business_profile(): void
    {
        $this->getJson('/api/team/business-profile')->assertUnauthorized();
    }

    public function test_returns_empty_profile_when_not_configured(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/business-profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.business_name', null)
            ->assertJsonPath('data.logo', null)
            ->assertJsonPath('data.images', []);
    }

    public function test_updates_public_fields_and_preserves_internal_keys(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $team->setSetting('business_config', [
            'business_name' => 'Vieja',
            'business_challenge' => 'No mostrar',
            '_insights' => ['potential_clients_summary' => 'interno'],
            'birth_date' => '1990-01-01',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/team/business-profile', [
                'business_name' => 'Assistant',
                'business_industry' => 'Software',
                'business_tagline' => 'WhatsApp que responde solo',
                'business_website' => 'https://assistant.idoneo.dev',
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.business_name', 'Assistant')
            ->assertJsonPath('data.business_website', 'https://assistant.idoneo.dev');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/business-profile')
            ->assertOk()
            ->json('data');
        $this->assertArrayNotHasKey('_insights', $response);
        $this->assertSame('No mostrar', $response['business_challenge']);

        $saved = $team->fresh()->getDecodedBusinessConfig();
        $this->assertSame('Assistant', $saved['business_name']);
        $this->assertSame('No mostrar', $saved['business_challenge']);
        $this->assertSame('1990-01-01', $saved['birth_date']);
        $this->assertSame('interno', $saved['_insights']['potential_clients_summary']);
    }

    public function test_can_upload_logo_and_brand_image(): void
    {
        Storage::fake('public');

        [, $team, $token] = $this->adminWithToken();

        $logo = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/team/business-profile/assets', [
                'role' => 'logo',
                'file' => UploadedFile::fake()->image('logo.png', 256, 256),
            ], ['Accept' => 'application/json']);

        $logo->assertCreated()
            ->assertJsonPath('success', true);
        $logoPath = (string) $logo->json('data.path');
        $this->assertStringStartsWith('business/'.$team->id.'/', $logoPath);
        $this->assertNotEmpty($logo->json('data.data_url'));
        Storage::disk('public')->assertExists($logoPath);

        $photo = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/team/business-profile/assets', [
                'role' => 'image',
                'file' => UploadedFile::fake()->image('local.jpg', 800, 600),
            ], ['Accept' => 'application/json']);

        $photo->assertCreated();
        $photoPath = (string) $photo->json('data.path');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/business-profile')
            ->assertOk()
            ->assertJsonPath('data.logo.path', $logoPath)
            ->assertJsonPath('data.images.0.path', $photoPath);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/team/business-profile/assets?path='.urlencode($logoPath))
            ->assertOk();
    }

    public function test_cannot_download_other_team_asset(): void
    {
        Storage::fake('public');

        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/team/business-profile/assets?path=business/999/secret.png')
            ->assertUnprocessable();
    }

    public function test_rejects_fourth_brand_image(): void
    {
        Storage::fake('public');

        [, , $token] = $this->adminWithToken();

        for ($i = 0; $i < 3; $i++)
        {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->post('/api/team/business-profile/assets', [
                    'role' => 'image',
                    'file' => UploadedFile::fake()->image('ref-'.$i.'.jpg', 400, 400),
                ], ['Accept' => 'application/json'])
                ->assertCreated();
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/team/business-profile/assets', [
                'role' => 'image',
                'file' => UploadedFile::fake()->image('extra.jpg', 400, 400),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_saves_wizard_personal_and_challenge_fields(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/team/business-profile', [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'business_challenge' => 'Quiero más consultas calificadas.',
                'wants_to_deepen' => 'si',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ada')
            ->assertJsonPath('data.business_challenge', 'Quiero más consultas calificadas.')
            ->assertJsonPath('data.wants_to_deepen', 'si');

        $saved = $team->fresh()->getDecodedBusinessConfig();
        $this->assertSame('Ada', $saved['first_name']);
        $this->assertSame('Quiero más consultas calificadas.', $saved['business_challenge']);
    }

    public function test_queues_market_insights_report(): void
    {
        Bus::fake();

        [, $team, $token] = $this->adminWithToken();
        $team->setSetting('business_config', [
            'business_name' => 'Assistant',
            'business_industry' => 'Software',
            'business_description' => 'Automatizamos WhatsApp',
            'business_tagline' => 'WhatsApp que responde solo',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/team/business-profile/insights')
            ->assertOk()
            ->assertJsonPath('data.insights_loading', true);

        Bus::assertDispatched(LoadTeamBusinessInsightsJob::class);
    }
}
