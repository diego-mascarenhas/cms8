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
        $this->assertSame(ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT, $service->normalizeAiUsagePercent(null));
        $this->assertSame(70.0, ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT);
    }

    #[Test]
    public function it_shifts_labor_euros_onto_tokens_for_commercial_balance(): void
    {
        $service = new ProjectBudgetSpecService;

        // 3 h Senior @ 225 € total, 60K base tokens, 70% → 0.9 h / 67.50 €
        // Total target ≈ original × (1 − 0.30×0.70) = original × 0.79
        $balanced = $service->applyHoursTokensBalance(225, 3, 60000, 57, 70);

        $this->assertSame(0.9, $balanced['hours']);
        $this->assertSame(2.1, $balanced['transferred_hours']);
        $this->assertSame(67.5, $balanced['labor']);
        $expectedTarget = round($balanced['original_total'] * (1 - 0.30 * 0.70), 2);
        $this->assertEqualsWithDelta($expectedTarget, $balanced['target_total'], 0.01);
        $this->assertEqualsWithDelta(
            $balanced['target_total'],
            ($balanced['labor'] ?? 0) + $balanced['token_billable'],
            1.5,
        );
    }

    #[Test]
    public function high_token_balance_approaches_thirty_percent_discount_and_cuts_hours(): void
    {
        $service = new ProjectBudgetSpecService;

        $atZero = $service->applyHoursTokensBalance(225, 3, 60000, 57, 0);
        $atFull = $service->applyHoursTokensBalance(225, 3, 60000, 57, 100);

        $this->assertSame(3.0, $atZero['hours']);
        $this->assertSame(0.0, $atFull['hours']);
        $this->assertSame(225.0, $atZero['labor']);
        $this->assertSame(0.0, $atFull['labor']);

        $totalZero = ($atZero['labor'] ?? 0) + $atZero['token_billable'];
        $totalFull = ($atFull['labor'] ?? 0) + $atFull['token_billable'];

        $this->assertEqualsWithDelta($atZero['original_total'], $totalZero, 1.5);
        $this->assertEqualsWithDelta($atFull['original_total'] * 0.70, $totalFull, 1.5);
        $this->assertLessThan($totalZero, $totalFull);
    }

    #[Test]
    public function it_computes_quote_totals_with_discount_and_price_fallback(): void
    {
        $service = new ProjectBudgetSpecService;

        $project = new \App\Models\Project([
            'discount' => 10,
            'price' => null,
            'data' => [
                'ai_usage_percent' => 0,
                'token_consumption' => ['savings_percent' => 57],
                'suggested_tasks' => [
                    [
                        'title' => 'Module A',
                        'included' => true,
                        'estimated_hours' => 1,
                        'unit_price' => 1000,
                        'estimated_tokens' => 0,
                    ],
                    [
                        'title' => 'Excluded',
                        'included' => false,
                        'estimated_hours' => 1,
                        'unit_price' => 500,
                        'estimated_tokens' => 0,
                    ],
                ],
            ],
        ]);

        $totals = $service->computeQuoteTotals($project);

        $this->assertSame(1000, $totals['grand_total']);
        $this->assertSame(10.0, $totals['discount_percent']);
        $this->assertSame(900, $totals['discounted_total']);
        $this->assertSame(900, $totals['payable_total']);

        $fallback = $service->computeQuoteTotals(new \App\Models\Project([
            'discount' => 0,
            'price' => 2500,
            'data' => ['suggested_tasks' => []],
        ]));

        $this->assertSame(2500, $fallback['grand_total']);
        $this->assertSame(2500, $fallback['payable_total']);
    }
}
