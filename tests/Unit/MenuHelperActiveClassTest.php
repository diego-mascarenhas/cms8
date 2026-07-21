<?php

namespace Tests\Unit;

use App\Helpers\MenuHelper;
use Tests\TestCase;

class MenuHelperActiveClassTest extends TestCase
{
    public function test_prefix_slug_marks_funnel_and_automation_routes(): void
    {
        $funnelMenu = (object) ['slug' => 'funnel'];
        $actionMenu = (object) ['slug' => 'automation'];

        $this->assertSame('active', MenuHelper::menuActiveClass($funnelMenu, 'funnel-list'));
        $this->assertSame('active', MenuHelper::menuActiveClass($funnelMenu, 'funnel.create'));
        $this->assertSame('active', MenuHelper::menuActiveClass($funnelMenu, 'funnel.show'));
        $this->assertSame('active', MenuHelper::menuActiveClass($funnelMenu, 'funnel.flow'));
        $this->assertSame('active', MenuHelper::menuActiveClass($funnelMenu, 'funnel.edit'));
        $this->assertNull(MenuHelper::menuActiveClass($funnelMenu, 'automation-list'));
        $this->assertNull(MenuHelper::menuActiveClass($funnelMenu, 'automation.edit'));

        $this->assertSame('active', MenuHelper::menuActiveClass($actionMenu, 'automation-list'));
        $this->assertSame('active', MenuHelper::menuActiveClass($actionMenu, 'automation.create'));
        $this->assertSame('active', MenuHelper::menuActiveClass($actionMenu, 'automation.show'));
        $this->assertSame('active', MenuHelper::menuActiveClass($actionMenu, 'automation.edit'));
        $this->assertNull(MenuHelper::menuActiveClass($actionMenu, 'funnel-list'));
        $this->assertNull(MenuHelper::menuActiveClass($actionMenu, 'funnel.flow'));
    }

    public function test_prefix_slug_does_not_cross_match_unrelated_routes(): void
    {
        $this->assertTrue(MenuHelper::routeMatchesSlugs('task.show', 'task'));
        $this->assertFalse(MenuHelper::routeMatchesSlugs('tasks.show', 'task'));
        $this->assertTrue(MenuHelper::routeMatchesSlugs('funnel-list', 'funnel'));
        $this->assertFalse(MenuHelper::routeMatchesSlugs('automation-list', 'funnel'));
    }
}
