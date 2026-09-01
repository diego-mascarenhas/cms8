<?php

namespace Tests\Unit;

use App\Support\BudgetPreviewUrl;
use Tests\TestCase;

class BudgetPreviewUrlTest extends TestCase
{
    public function test_falls_back_to_humano_route(): void
    {
        config(['projects.budget_preview_base_url' => null]);

        $this->assertSame(
            route('project.budget-preview', 'abc123', true),
            BudgetPreviewUrl::forToken('abc123'),
        );
    }

    public function test_uses_frontend_base_url_when_configured(): void
    {
        config(['projects.budget_preview_base_url' => 'https://estimator.idoneo.dev/']);

        $this->assertSame(
            'https://estimator.idoneo.dev/p/budget/tok-1',
            BudgetPreviewUrl::forToken('tok-1'),
        );
        $this->assertSame(
            'https://estimator.idoneo.dev/p/budget/tok-1?download=1',
            BudgetPreviewUrl::pair('tok-1')['download_url'],
        );
    }
}
