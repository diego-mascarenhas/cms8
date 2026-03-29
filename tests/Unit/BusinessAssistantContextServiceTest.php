<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Services\BusinessAssistantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAssistantContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_without_team_id(): void
    {
        $service = app(BusinessAssistantContextService::class);

        $this->assertSame('', $service->buildMarkdownAppendix(null));
        $this->assertSame('', $service->buildMarkdownAppendix(0));
    }

    public function test_returns_empty_when_no_business_config(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $service = app(BusinessAssistantContextService::class);

        $this->assertSame('', $service->buildMarkdownAppendix($team->id));
    }

    public function test_includes_configured_fields_in_markdown(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Acme SA']);
        $team->setSetting('business_config', [
            'business_name' => 'Mi Marca',
            'business_industry' => 'Retail',
            'business_email' => 'hola@example.com',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $service = app(BusinessAssistantContextService::class);
        $markdown = $service->buildMarkdownAppendix($team->id);

        $this->assertStringContainsString('Acme SA', $markdown);
        $this->assertStringContainsString('Mi Marca', $markdown);
        $this->assertStringContainsString('Retail', $markdown);
        $this->assertStringContainsString('hola@example.com', $markdown);
        $this->assertStringContainsString('Nombre del negocio', $markdown);
    }
}
