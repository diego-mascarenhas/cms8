<?php

namespace Tests\Unit;

use App\Support\DatabaseSync\TableDependencyResolver;
use PHPUnit\Framework\TestCase;

class TableDependencyResolverTest extends TestCase
{
    public function test_it_orders_tables_by_parent_dependency(): void
    {
        $dependencies = [
            'projects' => ['teams', 'enterprises'],
            'enterprises' => ['teams'],
            'teams' => [],
        ];

        $ordered = TableDependencyResolver::resolve($dependencies, ['projects', 'teams', 'enterprises']);

        $this->assertSame(['teams', 'enterprises', 'projects'], $ordered);
    }

    public function test_it_keeps_remaining_tables_when_cycle_exists(): void
    {
        $dependencies = [
            'a' => ['b'],
            'b' => ['a'],
            'c' => [],
        ];

        $ordered = TableDependencyResolver::resolve($dependencies, ['a', 'b', 'c']);

        $this->assertSame('c', $ordered[0]);
        $this->assertContains('a', $ordered);
        $this->assertContains('b', $ordered);
        $this->assertCount(3, $ordered);
    }
}
