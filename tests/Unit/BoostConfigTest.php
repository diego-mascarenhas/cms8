<?php

namespace Tests\Unit;

use Tests\TestCase;

class BoostConfigTest extends TestCase
{
    public function test_browser_logs_watcher_is_disabled_by_default(): void
    {
        $this->assertFalse(config('boost.browser_logs_watcher'));
    }
}
