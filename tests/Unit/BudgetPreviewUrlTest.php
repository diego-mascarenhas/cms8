<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Support\BudgetPreviewUrl;
use Illuminate\Http\Request;
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

    public function test_prefers_the_project_frontend_over_the_global_default(): void
    {
        config(['projects.budget_preview_base_url' => 'https://estimator.idoneo.dev']);

        $project = new Project([
            'data' => ['budget_preview_base_url' => 'https://presu.humano.app'],
        ]);

        $this->assertSame(
            'https://presu.humano.app/p/budget/tok-1',
            BudgetPreviewUrl::forToken('tok-1', $project),
        );
    }

    public function test_rejects_the_cms8_admin_origin(): void
    {
        config(['app.url' => 'https://admin.idoneo.dev']);

        $this->assertFalse(BudgetPreviewUrl::isAllowedOrigin('https://admin.idoneo.dev'));
        $this->assertTrue(BudgetPreviewUrl::isAllowedOrigin('https://presu.humano.app'));
        $this->assertTrue(BudgetPreviewUrl::isAllowedOrigin('https://estimator.idoneo.dev'));
    }

    public function test_reads_the_estimator_origin_from_the_request(): void
    {
        $request = Request::create('https://admin.idoneo.dev/api/projects/1', 'GET', [], [], [], [
            'HTTP_ORIGIN' => 'https://presu.humano.app',
        ]);

        $this->assertSame('https://presu.humano.app', BudgetPreviewUrl::originFromRequest($request));
    }
}
