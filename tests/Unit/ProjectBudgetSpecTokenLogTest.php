<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\ProjectBudgetSpecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ProjectBudgetSpecTokenLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_tokens_to_passed_team_without_authenticated_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $module = Module::query()->firstOrCreate(
            ['key' => 'projects'],
            ['name' => 'Projects', 'is_core' => true, 'order' => 10, 'status' => 1],
        );

        $service = new ProjectBudgetSpecService;
        $method = new ReflectionMethod(ProjectBudgetSpecService::class, 'logAiUsage');
        $method->setAccessible(true);
        $method->invoke(
            $service,
            $team,
            null,
            'ProjectBudgetSpecService::chatTurn',
            (object) [
                'promptTokens' => 40,
                'completionTokens' => 80,
            ],
            120,
            60,
        );

        $log = TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('service', 'ProjectBudgetSpecService::chatTurn')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($module->id, (int) $log->module_id);
        $this->assertSame(120, (int) $log->json_tokens);
        $this->assertSame(120, (int) $log->json_size);
        $this->assertSame(60, (int) $log->toon_size);
    }

    public function test_skips_log_when_no_team_is_available(): void
    {
        $service = new ProjectBudgetSpecService;
        $method = new ReflectionMethod(ProjectBudgetSpecService::class, 'logAiUsage');
        $method->setAccessible(true);
        $method->invoke(
            $service,
            null,
            null,
            'ProjectBudgetSpecService::generate',
            (object) [
                'promptTokens' => 10,
                'completionTokens' => 10,
            ],
        );

        $this->assertSame(0, TokenUsageLog::withoutGlobalScopes()->count());
    }
}
