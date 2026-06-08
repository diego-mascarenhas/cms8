<?php

namespace Tests\Unit;

use App\Support\WebDavInboundTaskSync;
use Carbon\Carbon;
use Tests\TestCase;

class WebDavInboundTaskSyncTest extends TestCase
{
    public function test_new_remote_task_uses_todo_or_done_from_completion_flag(): void
    {
        $this->assertSame(1, WebDavInboundTaskSync::resolveInboundStatusId(false, null, 1, 4));
        $this->assertSame(4, WebDavInboundTaskSync::resolveInboundStatusId(true, null, 1, 4));
    }

    public function test_remote_completion_always_marks_done_in_humano(): void
    {
        $this->assertSame(4, WebDavInboundTaskSync::resolveInboundStatusId(true, 2, 1, 4));
        $this->assertSame(4, WebDavInboundTaskSync::resolveInboundStatusId(true, 3, 1, 4));
        $this->assertSame(4, WebDavInboundTaskSync::resolveInboundStatusId(true, 1, 1, 4));
    }

    public function test_remote_incompletion_preserves_workflow_statuses(): void
    {
        $this->assertSame(2, WebDavInboundTaskSync::resolveInboundStatusId(false, 2, 1, 4));
        $this->assertSame(3, WebDavInboundTaskSync::resolveInboundStatusId(false, 3, 1, 4));
        $this->assertSame(1, WebDavInboundTaskSync::resolveInboundStatusId(false, 1, 1, 4));
    }

    public function test_remote_incompletion_reopens_done_tasks(): void
    {
        $this->assertSame(1, WebDavInboundTaskSync::resolveInboundStatusId(false, 4, 1, 4));
    }

    public function test_remote_content_applies_to_new_tasks(): void
    {
        $this->assertTrue(WebDavInboundTaskSync::shouldApplyRemoteContent(null, null, true));
        $this->assertTrue(WebDavInboundTaskSync::shouldApplyRemoteContent(100, Carbon::now(), true));
    }

    public function test_remote_content_skips_when_local_is_newer(): void
    {
        $localUpdatedAt = Carbon::createFromTimestamp(200);

        $this->assertFalse(WebDavInboundTaskSync::shouldApplyRemoteContent(100, $localUpdatedAt, false));
        $this->assertTrue(WebDavInboundTaskSync::shouldApplyRemoteContent(250, $localUpdatedAt, false));
    }

    public function test_remote_content_skips_when_remote_has_no_timestamp(): void
    {
        $this->assertFalse(WebDavInboundTaskSync::shouldApplyRemoteContent(null, Carbon::now(), false));
    }
}
