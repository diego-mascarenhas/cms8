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

    public function test_missing_start_date_uses_the_remote_due_date(): void
    {
        $dates = WebDavInboundTaskSync::resolveInboundDates('2026-10-28', null, null);

        $this->assertSame('2026-10-28', $dates['start_date']);
        $this->assertSame('2026-10-28', $dates['due_date']);
    }

    public function test_existing_start_date_is_kept_when_the_reminder_is_updated(): void
    {
        $dates = WebDavInboundTaskSync::resolveInboundDates('2026-10-28', '2026-10-01', '2026-10-20');

        $this->assertSame('2026-10-01', $dates['start_date']);
        $this->assertSame('2026-10-28', $dates['due_date']);
    }

    public function test_missing_remote_due_date_falls_back_to_today(): void
    {
        Carbon::setTestNow('2026-11-09 14:16:20');

        $dates = WebDavInboundTaskSync::resolveInboundDates(null, null, null);

        $this->assertSame('2026-11-09', $dates['start_date']);
        $this->assertSame('2026-11-09', $dates['due_date']);

        Carbon::setTestNow();
    }
}
