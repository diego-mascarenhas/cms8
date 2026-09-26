<?php

namespace Tests\Unit;

use App\Support\DatabaseSync\PostgresCliBinaryResolver;
use App\Support\DatabaseSync\PostgresProdReadTableAccess;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostgresProdSyncSupportTest extends TestCase
{
    #[Test]
    public function it_resolves_a_postgres_binary_from_the_configured_directory(): void
    {
        $directory = sys_get_temp_dir().'/pg-bin-'.uniqid();
        mkdir($directory);
        $binary = $directory.'/pg_dump';
        file_put_contents($binary, "#!/bin/sh\n");
        chmod($binary, 0755);

        config(['database.pg_bin' => $directory]);

        try
        {
            $this->assertSame($binary, PostgresCliBinaryResolver::resolve('pg_dump'));
            $this->assertContains($binary, PostgresCliBinaryResolver::searchedPathsFor('pg_dump'));
            $this->assertNull(PostgresCliBinaryResolver::resolve('definitely-missing-pg-tool'));
        } finally
        {
            unlink($binary);
            rmdir($directory);
        }
    }

    #[Test]
    public function it_splits_tables_the_read_user_can_select(): void
    {
        $split = PostgresProdReadTableAccess::partitionRows([
            (object) ['name' => 'users', 'can_select' => true],
            (object) ['name' => 'secrets', 'can_select' => 'f'],
            (object) ['name' => 'invoices', 'can_select' => 't'],
        ]);

        $this->assertSame(['users', 'invoices'], $split['readable']);
        $this->assertSame(['secrets'], $split['denied']);
    }
}
