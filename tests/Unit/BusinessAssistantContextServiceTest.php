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

    public function test_falls_back_to_team_name_when_no_business_config(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => "REVISION ALPHA's Team"]);
        $service = app(BusinessAssistantContextService::class);
        $markdown = $service->buildMarkdownAppendix($team->id);

        $this->assertStringContainsString("REVISION ALPHA's Team", $markdown);
        $this->assertStringContainsString('Nombre del negocio', $markdown);
        $this->assertStringNotContainsString('en Humano', $markdown);
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

    public function test_excludes_first_name_and_birth_date_from_assistant_context(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Tienda SA',
            'first_name' => 'NombreSecretoTitular',
            'last_name' => 'ApellidoVisible',
            'birth_date' => '1990-01-01',
            'city' => 'Rosario',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $markdown = app(BusinessAssistantContextService::class)->buildMarkdownAppendix($team->id);

        $this->assertStringContainsString('Tienda SA', $markdown);
        $this->assertStringContainsString('ApellidoVisible', $markdown);
        $this->assertStringContainsString('Rosario', $markdown);
        $this->assertStringNotContainsString('NombreSecretoTitular', $markdown);
        $this->assertStringNotContainsString('1990-01-01', $markdown);
        $this->assertStringNotContainsString('Nombre (titular)', $markdown);
        $this->assertStringNotContainsString('Fecha de nacimiento', $markdown);
    }

    public function test_excludes_business_challenge_from_assistant_context(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Visible SA',
            'business_challenge' => 'Secreto interno que no debe ir al asistente general',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $markdown = app(BusinessAssistantContextService::class)->buildMarkdownAppendix($team->id);

        $this->assertStringContainsString('Visible SA', $markdown);
        $this->assertStringNotContainsString('Secreto interno', $markdown);
    }

    public function test_compact_appendix_omits_email_and_owner(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Acme SA']);
        $team->setSetting('business_config', [
            'business_name' => 'Mi Marca',
            'business_industry' => 'Retail',
            'business_email' => 'hola@example.com',
            'last_name' => 'ApellidoVisible',
            'instagram' => '@acme',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $markdown = app(BusinessAssistantContextService::class)->buildMarkdownAppendix($team->id, true);

        $this->assertStringContainsString('Mi Marca', $markdown);
        $this->assertStringContainsString('Retail', $markdown);
        $this->assertStringNotContainsString('hola@example.com', $markdown);
        $this->assertStringNotContainsString('ApellidoVisible', $markdown);
        $this->assertStringNotContainsString('@acme', $markdown);
    }
}
