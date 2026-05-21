<?php

namespace Tests\Unit;

use App\Support\AssistantTaskStatusUpdate;
use PHPUnit\Framework\TestCase;

class AssistantTaskStatusUpdateTest extends TestCase
{
    public function test_extracts_task_status_update_from_tool_result_string(): void
    {
        $line = AssistantTaskStatusUpdate::formatSentinelLine(64, 2, 'IN_PROGRESS');
        $payload = AssistantTaskStatusUpdate::extractFromToolResults([
            "Prefix text\n{$line}\nTask moved.",
        ]);

        $this->assertNotNull($payload);
        $this->assertSame(64, $payload['task_id']);
        $this->assertSame(2, $payload['status_id']);
        $this->assertSame('IN_PROGRESS', $payload['status_name']);
    }
}
