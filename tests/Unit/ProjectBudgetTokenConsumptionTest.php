<?php

namespace Tests\Unit;

use App\Services\ProjectBudgetSpecService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectBudgetTokenConsumptionTest extends TestCase
{
    #[Test]
    public function it_builds_one_token_line_per_included_labor(): void
    {
        $service = new ProjectBudgetSpecService;

        $payload = $service->buildTokenConsumption([
            [
                'title' => 'Idioma base (core)',
                'estimated_hours' => 8,
                'estimated_tokens' => 160000,
                'included' => true,
            ],
            [
                'title' => 'Estado Candidatura',
                'estimated_hours' => 10,
                'estimated_tokens' => 200000,
                'included' => true,
            ],
            [
                'title' => 'Excluded labor',
                'estimated_hours' => 5,
                'estimated_tokens' => 100000,
                'included' => false,
            ],
        ]);

        $this->assertSame(
            "Idioma base (core): 160,0 K\nEstado Candidatura: 200,0 K",
            $payload['notes'],
        );
        $this->assertSame(360000, $payload['total_tokens']);
        $this->assertSame(252000, $payload['input_tokens']);
        $this->assertSame(108000, $payload['output_tokens']);
        $this->assertSame(57.0, $payload['savings_percent']);
        $this->assertSame('EUR', $payload['currency']);
        $this->assertGreaterThan(0, $payload['cost_euros']);
        $this->assertGreaterThan($payload['cost_euros'], $payload['billable_euros']);
    }

    #[Test]
    public function it_estimates_tokens_from_hours_when_missing(): void
    {
        $service = new ProjectBudgetSpecService;

        $tasks = $service->normalizeSuggestedTasks([
            [
                'title' => 'Discovery',
                'estimated_hours' => 8,
                'resource_level' => 'Senior',
                'unit_price' => 1200,
            ],
        ]);

        $this->assertSame(160000, $tasks[0]['estimated_tokens']);
        $this->assertSame(
            'Discovery: 160,0 K',
            $service->buildTokenConsumptionNotes($tasks),
        );
    }

    #[Test]
    public function it_normalizes_legacy_object_and_keeps_notes_editable(): void
    {
        $service = new ProjectBudgetSpecService;

        $normalized = $service->normalizeTokenConsumption([
            'notes' => '',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'cost_euros' => 0,
            'savings_percent' => 57,
            'billable_euros' => 0,
            'currency' => 'EUR',
        ], [
            [
                'title' => 'Discovery',
                'estimated_hours' => 8,
                'estimated_tokens' => 160000,
                'included' => true,
            ],
        ]);

        $this->assertSame('Discovery: 160,0 K', $normalized['notes']);
        $this->assertSame(160000, $normalized['total_tokens']);
        $this->assertSame('EUR', $normalized['currency']);
    }

    #[Test]
    public function merge_client_edits_rebuilds_token_consumption(): void
    {
        $service = new ProjectBudgetSpecService;

        $merged = $service->mergeClientTaskEdits(
            [
                'suggested_tasks' => [
                    [
                        'title' => 'Discovery',
                        'description' => 'Analysis',
                        'category_name' => 'Análisis',
                        'estimated_hours' => 8,
                        'resource_level' => 'Senior',
                        'estimated_tokens' => 160000,
                        'unit_price' => 1200,
                        'included' => true,
                    ],
                ],
            ],
            [
                [
                    'title' => 'Discovery',
                    'estimated_hours' => 10,
                    'included' => true,
                ],
            ],
        );

        $this->assertSame(160000, $merged['suggested_tasks'][0]['estimated_tokens']);
        $this->assertIsArray($merged['token_consumption']);
        $this->assertSame('Discovery: 160,0 K', $merged['token_consumption']['notes']);
    }

    #[Test]
    public function it_strips_legacy_tokens_ai_prefix_from_notes(): void
    {
        $service = new ProjectBudgetSpecService;

        $this->assertSame(
            "Discovery: 160,0 K\nEstado: 200,0 K",
            $service->stripTokenConsumptionPrefix("Tokens AI — Discovery: 160,0 K\nTokens AI - Estado: 200,0 K"),
        );
    }

    #[Test]
    public function it_reduces_labor_value_by_planned_ai_usage_percent(): void
    {
        $service = new ProjectBudgetSpecService;

        $this->assertSame(225.0, $service->laborValueAfterAi(225, 0));
        $this->assertSame(67.5, $service->laborValueAfterAi(225, 70));
        $this->assertSame(0.0, $service->laborValueAfterAi(225, 100));
        $this->assertNull($service->laborValueAfterAi(null, 50));
        $this->assertSame(57.0, $service->normalizeAiUsagePercent(57));
        $this->assertSame(0.0, $service->normalizeAiUsagePercent(-10));
        $this->assertSame(100.0, $service->normalizeAiUsagePercent(150));
    }
}
