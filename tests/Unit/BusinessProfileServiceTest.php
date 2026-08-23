<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Services\Business\BusinessProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_appendix_is_empty_without_profile(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $this->assertSame('', app(BusinessProfileService::class)->promptAppendix($team));
    }

    public function test_prompt_appendix_includes_public_brand_fields(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Assistant',
            'business_industry' => 'Software',
            'business_tagline' => 'Automatizá tu WhatsApp',
            'business_challenge' => 'secreto interno',
            'birth_date' => '1990-01-01',
            '_logo' => ['path' => 'business/1/logo.png'],
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $appendix = app(BusinessProfileService::class)->promptAppendix($team);

        $this->assertStringContainsString('Marca: Assistant', $appendix);
        $this->assertStringContainsString('Software', $appendix);
        $this->assertStringContainsString('logo de marca', $appendix);
        $this->assertStringNotContainsString('secreto interno', $appendix);
        $this->assertStringNotContainsString('1990-01-01', $appendix);
    }

    public function test_update_clears_empty_fields_without_dropping_insights(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Assistant',
            'business_website' => 'https://old.test',
            '_insights' => ['ok' => true],
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $payload = app(BusinessProfileService::class)->update($team, [
            'business_website' => '',
            'business_industry' => 'SaaS',
        ]);

        $this->assertNull($payload['business_website']);
        $this->assertSame('SaaS', $payload['business_industry']);
        $this->assertSame('Assistant', $payload['business_name']);
        $this->assertTrue($team->fresh()->getDecodedBusinessConfig()['_insights']['ok']);
    }
}
