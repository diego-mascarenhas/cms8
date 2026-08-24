<?php

namespace Tests\Unit;

use App\Services\ProjectBudgetSpecService;
use PHPUnit\Framework\TestCase;

class ProjectBudgetSpecChatPromptTest extends TestCase
{
    public function test_default_prompt_exposes_placeholders_for_editors(): void
    {
        $prompt = (new ProjectBudgetSpecService)->getDefaultBudgetChatPrompt();

        $this->assertStringContainsString('{lead_name}', $prompt);
        $this->assertStringContainsString('{requirements_json}', $prompt);
        $this->assertStringContainsString('{intake}', $prompt);
        $this->assertStringContainsString('teléfono', $prompt);
        $this->assertStringContainsString('empresa', $prompt);
        $this->assertStringContainsString('no los vuelvas a preguntar', $prompt);
        $this->assertStringContainsString('assistant_message', $prompt);
    }

    public function test_interpolate_replaces_chat_placeholders(): void
    {
        $service = new ProjectBudgetSpecService;
        $text = $service->interpolateBudgetChatPrompt(
            'Hola {lead_name}. Proyecto {project_name}. {intake}. {requirements_json}',
            'Victor',
            '[{"key":"objetivo"}]',
            'Estimator',
            'Empresa: Idoneo',
        );

        $this->assertSame(
            'Hola Victor. Proyecto Estimator. Empresa: Idoneo. [{"key":"objetivo"}]',
            $text,
        );
    }
}
