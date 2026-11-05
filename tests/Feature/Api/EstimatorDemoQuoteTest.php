<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateEstimatorDemoQuoteJob;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Services\EstimatorProductQuoteService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Features;
use RuntimeException;
use Tests\TestCase;

class EstimatorDemoQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
    }

    public function test_a_pasted_brief_is_quoted_without_a_queue_worker(): void
    {
        $this->createFunnelTeam();
        $this->mock(EstimatorProductQuoteService::class, function ($mock): void
        {
            $mock->shouldReceive('quote')->once()->andReturn([
                'name' => 'Aceites',
                'summary' => 'Dos aceites minerales.',
                'lines' => [
                    [
                        'name' => 'Aceite mineral',
                        'code' => 'ACM-1',
                        'quantity' => 2,
                        'unit' => 'u',
                        'detail' => '',
                        'matched' => true,
                        'unit_price' => '5.488,00 ARS',
                        'line_total' => '10.976,00 ARS',
                    ],
                ],
                'totals' => ['total' => '10.976,00 ARS'],
            ]);
        });

        $response = $this->postJson('/api/projects/funnel/demo', [
            'brief' => '2 aceites minerales de 1 litro y 1 filtro de aceite.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'processing');
        $token = (string) $response->json('poll_token');
        $this->assertNotSame('', $token);

        $state = Cache::get(GenerateEstimatorDemoQuoteJob::cacheKey($token));
        $this->assertSame('ready', $state['status'] ?? null);
        $this->assertSame('Aceite mineral', $state['data']['lines'][0]['name'] ?? null);
        $this->assertSame(0, Project::withoutGlobalScopes()->count());
    }

    public function test_the_demo_returns_the_interpreted_budget(): void
    {
        $team = $this->createFunnelTeam();
        $this->partialMock(EstimatorProductQuoteService::class, function ($mock): void
        {
            $mock->shouldReceive('quote')->once()->andReturn([
                'name' => 'Aceites y filtro',
                'summary' => 'Dos aceites minerales y un filtro.',
                'lines' => [
                    [
                        'name' => 'Aceite mineral',
                        'code' => 'ACM-1',
                        'quantity' => 2,
                        'unit' => 'u',
                        'detail' => '',
                        'matched' => true,
                        'unit_price' => '5.488,00 ARS',
                        'line_total' => '10.976,00 ARS',
                    ],
                ],
                'totals' => ['total' => '10.976,00 ARS'],
            ]);
        });

        $token = 'demo-quote-token';
        (new GenerateEstimatorDemoQuoteJob(
            $token,
            $team->id,
            '2 aceites minerales de 1 litro y 1 filtro de aceite.',
        ))->handle(app(EstimatorProductQuoteService::class));

        $response = $this->getJson('/api/projects/funnel/demo/status?poll_token='.$token);

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('data.summary', 'Dos aceites minerales y un filtro.')
            ->assertJsonPath('data.lines.0.name', 'Aceite mineral')
            ->assertJsonPath('data.lines.0.line_total', '10.976,00 ARS')
            ->assertJsonMissingPath('data.rows')
            ->assertJsonMissingPath('data.dimension');
        $this->assertSame(0, Project::withoutGlobalScopes()->count());
    }

    public function test_a_catalog_product_sets_the_line_price(): void
    {
        $team = $this->createFunnelTeam();
        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Aceite mineral',
            'code' => 'ACM-1',
            'price' => 5488,
            'cost' => null,
            'markup_percent' => null,
        ]);

        $quote = app(EstimatorProductQuoteService::class)->present($team, [
            'title' => 'Pedido de aceites',
            'summary' => 'Un aceite mineral.',
            'lines' => [
                ['name' => 'otro nombre', 'code' => 'ACM-1', 'quantity' => 2, 'unit' => 'u', 'detail' => ''],
            ],
        ]);

        $this->assertSame('Aceite mineral', $quote['lines'][0]['name']);
        $this->assertSame($product->currentSellingPrice(), 5488.0);
        $this->assertSame('5.488,00 ARS', $quote['lines'][0]['unit_price']);
        $this->assertSame('10.976,00 ARS', $quote['totals']['total']);
    }

    public function test_a_code_that_was_not_in_the_request_does_not_pick_a_catalog_product(): void
    {
        $team = $this->createFunnelTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Filtro de habitáculo',
            'code' => 'FIL-020',
            'price' => 7900,
            'cost' => null,
            'markup_percent' => null,
        ]);

        $quote = app(EstimatorProductQuoteService::class)->present($team, [
            'title' => 'Filtro',
            'summary' => 'Un filtro de aceite.',
            'lines' => [
                ['name' => 'Filtro de aceite', 'code' => 'FIL-020', 'quantity' => 1, 'unit' => 'u', 'detail' => ''],
            ],
        ], '1 filtro de aceite');

        $this->assertSame('Filtro de aceite', $quote['lines'][0]['name']);
        $this->assertSame('', $quote['lines'][0]['code']);
        $this->assertFalse($quote['lines'][0]['matched']);
        $this->assertNull($quote['totals']['total']);
    }

    public function test_the_product_prompt_does_not_ask_for_a_project_plan(): void
    {
        $prompt = app(EstimatorProductQuoteService::class)->defaultPrompt();

        $this->assertStringContainsString('"lines"', $prompt);
        $this->assertStringNotContainsString('dossier', strtolower($prompt));
        $this->assertStringNotContainsString('suggested_tasks', $prompt);
    }

    public function test_a_provider_failure_stays_off_the_public_response(): void
    {
        $team = $this->createFunnelTeam();
        $this->partialMock(EstimatorProductQuoteService::class, function ($mock): void
        {
            $mock->shouldReceive('quote')->once()->andThrow(
                new RuntimeException('Anthropic Error [401]: authentication_error - invalid x-api-key'),
            );
        });

        $token = 'demo-quote-failed';
        (new GenerateEstimatorDemoQuoteJob($token, $team->id, '2 aceites minerales.'))
            ->handle(app(EstimatorProductQuoteService::class));

        $response = $this->getJson('/api/projects/funnel/demo/status?poll_token='.$token);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringNotContainsString('401', (string) $response->json('message'));
        $this->assertStringNotContainsString('x-api-key', (string) $response->json('message'));
    }

    private function createFunnelTeam(): \App\Models\Team
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        config(['projects.funnel_team_id' => $team->id]);

        return $team;
    }
}
