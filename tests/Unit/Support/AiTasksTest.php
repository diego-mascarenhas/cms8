<?php

namespace Tests\Unit\Support;

use App\Support\AiTasks;
use Tests\TestCase;

class AiTasksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default_task_provider' => 'anthropic',
            'ai.default_task_model' => 'cheapest',
            'ai.tasks_failover' => ['openai'],
            'ai.tasks.assistant.provider' => 'anthropic',
            'ai.tasks.assistant.model' => 'cheapest',
            'ai.tasks.assistant.failover' => null,
            'ai.tasks.sentiment.provider' => 'deepseek',
            'ai.tasks.summary.provider' => 'deepseek',
            'ai.tasks.shop.provider' => 'deepseek',
            'ai.tasks.image.provider' => 'openai',
            'ai.providers.anthropic.key' => 'test-anthropic',
            'ai.providers.openai.key' => 'test-openai',
            'ai.providers.deepseek.key' => 'test-deepseek',
        ]);
    }

    public function test_assistant_uses_anthropic_with_openai_failover(): void
    {
        $this->assertSame(['anthropic', 'openai'], AiTasks::provider('assistant'));
        $this->assertSame('claude-haiku-4-5-20251001', AiTasks::model('assistant'));
    }

    public function test_insight_stays_on_haiku_with_openai_failover(): void
    {
        $this->assertSame(['anthropic', 'openai'], AiTasks::provider('insight'));
        $this->assertSame('claude-haiku-4-5-20251001', AiTasks::model('insight'));
    }

    public function test_dumb_tasks_use_deepseek_with_openai_failover(): void
    {
        foreach (['sentiment', 'summary', 'shop'] as $task)
        {
            $this->assertSame(['deepseek', 'openai'], AiTasks::provider($task), $task);
            $this->assertSame('deepseek-chat', AiTasks::model($task), $task);
        }
    }

    public function test_image_task_stays_on_openai(): void
    {
        $this->assertSame('openai', AiTasks::provider('image'));
    }

    public function test_skips_deepseek_when_api_key_is_missing(): void
    {
        config(['ai.providers.deepseek.key' => '']);

        $this->assertSame('openai', AiTasks::provider('sentiment'));
    }

    public function test_uses_explicit_model_when_configured(): void
    {
        config(['ai.tasks.assistant.model' => 'claude-sonnet-4-6']);

        $this->assertSame('claude-sonnet-4-6', AiTasks::model('assistant'));
    }

    public function test_anthropic_default_text_model_is_haiku(): void
    {
        $this->assertSame(
            'claude-haiku-4-5-20251001',
            config('ai.providers.anthropic.models.text.default'),
        );
    }
}
