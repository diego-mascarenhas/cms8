<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use App\Services\Mail\MailerNewsGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MessageGenerateApiTest extends TestCase
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
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'mail',
                'description' => 'Mailer',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('mailer');

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-news')->plainTextToken];
    }

    /**
     * @return array<string, string>
     */
    private function validBrief(): array
    {
        return [
            'goal_type' => 'promo',
            'goal' => 'Conseguir turnos para una demo del producto esta semana.',
            'cta' => 'Agendar una demo',
            'audience' => 'Dueños de pymes que todavía anotan pedidos a mano.',
            'offer' => 'Auditoría gratuita de 20 minutos para ordenar el WhatsApp comercial.',
            'benefits' => 'Ahorro de tiempo y menos pedidos perdidos.',
            'urgency' => 'Quedan 8 lugares esta semana.',
            'url' => 'https://example.com/demo',
            'tone' => 'close',
            'avoid' => 'No hablar de descuentos inventados.',
        ];
    }

    public function test_generates_news_from_brief(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->mock(MailerNewsGenerationService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'name' => '¿Agendamos tu demo?',
                    'text' => '20 minutos para ordenar tu WhatsApp comercial.',
                    'html' => '<h2>Hola {{name}}</h2><p>Agendá tu demo.</p>',
                    'css' => '',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/generate', $this->validBrief())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', '¿Agendamos tu demo?')
            ->assertJsonPath('data.text', '20 minutos para ordenar tu WhatsApp comercial.');
    }

    public function test_brief_requires_agency_fields(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/message/generate', [
                'goal_type' => 'promo',
                'goal' => 'corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['goal', 'cta', 'audience', 'offer', 'tone']);
    }

    public function test_guest_cannot_generate_news(): void
    {
        $this->postJson('/api/message/generate', $this->validBrief())->assertUnauthorized();
    }

    public function test_parse_limits_subject_and_preview(): void
    {
        $service = app(MailerNewsGenerationService::class);

        $parsed = $service->parse(<<<'JSON'
{"name":"Este asunto es demasiado largo para el campo de cincuenta caracteres del News","text":"Vista previa","html":"<p>Hola</p>","css":""}
JSON);

        $this->assertSame(50, mb_strlen($parsed['name']));
        $this->assertSame('Vista previa', $parsed['text']);
        $this->assertSame('<p>Hola</p>', $parsed['html']);
    }
}
