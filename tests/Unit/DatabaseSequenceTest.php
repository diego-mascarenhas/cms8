<?php

namespace Tests\Unit;

use App\Support\DatabaseSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseSequenceTest extends TestCase
{
    #[Test]
    public function it_rejects_invalid_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DatabaseSequence::sync('categories;drop');
    }

    #[Test]
    public function it_syncs_postgres_serial_with_quoted_identifiers(): void
    {
        DB::shouldReceive('getDriverName')->once()->andReturn('pgsql');
        DB::shouldReceive('table')->once()->with('categories')->andReturn(
            tap(Mockery::mock(), function ($query): void
            {
                $query->shouldReceive('max')->once()->with('id')->andReturn(3366);
            }),
        );
        DB::shouldReceive('statement')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool
            {
                return $sql === "SELECT setval(pg_get_serial_sequence('categories', 'id'), ?, true)"
                    && $bindings === [3366];
            })
            ->andReturn(true);

        $this->assertSame(3367, DatabaseSequence::sync('categories'));
    }

    #[Test]
    public function it_syncs_mysql_auto_increment(): void
    {
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('table')->once()->with('categories')->andReturn(
            tap(Mockery::mock(), function ($query): void
            {
                $query->shouldReceive('max')->once()->with('id')->andReturn(100);
            }),
        );
        DB::shouldReceive('statement')
            ->once()
            ->with('ALTER TABLE `categories` AUTO_INCREMENT = 101')
            ->andReturn(true);

        $this->assertSame(101, DatabaseSequence::sync('categories'));
    }

    #[Test]
    public function it_noops_on_sqlite(): void
    {
        $this->assertNull(DatabaseSequence::sync('categories'));
    }
}
