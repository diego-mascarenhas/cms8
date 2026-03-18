<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LaunchBusinessCreationRouteTest extends TestCase
{
    public function test_launch_business_creation_route_exists(): void
    {
        $this->assertTrue(Route::has('landing.business-creation'));
        $route = Route::getRoutes()->getByName('landing.business-creation');
        $this->assertNotNull($route);
        $this->assertStringContainsString('launch', $route->uri());
    }
}
