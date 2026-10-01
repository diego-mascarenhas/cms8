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

    #[Test]
    public function it_skips_sequences_the_read_user_cannot_select(): void
    {
        $split = PostgresProdReadTableAccess::partitionSequenceRows([
            (object) [
                'sequence_name' => 'users_id_seq',
                'table_name' => 'users',
                'column_name' => 'id',
                'can_select' => true,
            ],
            (object) [
                'sequence_name' => 'academy_chapters_id_seq',
                'table_name' => 'academy_chapters',
                'column_name' => 'id',
                'can_select' => 'f',
            ],
            (object) [
                'sequence_name' => 'orphan_seq',
                'table_name' => null,
                'column_name' => null,
                'can_select' => false,
            ],
        ]);

        $this->assertSame(['academy_chapters_id_seq', 'orphan_seq'], $split['denied']);
        $this->assertSame([
            [
                'sequence' => 'academy_chapters_id_seq',
                'table' => 'academy_chapters',
                'column' => 'id',
            ],
        ], $split['owned']);
        $this->assertSame([
            '--exclude-table=public.academy_chapters_id_seq',
            '--exclude-table=public.orphan_seq',
        ], PostgresProdReadTableAccess::excludeTableArguments('public', $split['denied']));
    }
}
