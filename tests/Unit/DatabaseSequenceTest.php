<?php

namespace Tests\Unit;

use App\Support\DatabaseSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use PDOException;
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

    #[Test]
    public function it_retries_after_postgres_primary_key_violation(): void
    {
        $calls = 0;
        $exception = self::uniqueViolation(
            'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "module_prompts_pkey" DETAIL: Key (id)=(66) already exists.',
        );

        $result = DatabaseSequence::retryOnDuplicateId('module_prompts', function () use (&$calls, $exception)
        {
            $calls++;
            if ($calls === 1)
            {
                throw $exception;
            }

            return 'created';
        });

        $this->assertSame('created', $result);
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function it_does_not_retry_composite_unique_violations(): void
    {
        $exception = self::uniqueViolation(
            'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "module_prompts_team_id_module_id_section_key_unique"',
        );

        $this->expectException(UniqueConstraintViolationException::class);

        DatabaseSequence::retryOnDuplicateId('module_prompts', function () use ($exception)
        {
            throw $exception;
        });
    }

    private static function uniqueViolation(string $message): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            'pgsql',
            'insert into module_prompts',
            [],
            new PDOException($message),
        );
    }
}
