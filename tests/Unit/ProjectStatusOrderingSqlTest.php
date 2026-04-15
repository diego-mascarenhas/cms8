<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectStatusOrderingSqlTest extends TestCase
{
    public function test_dashboard_project_status_order_uses_portable_case_expression(): void
    {
        DB::statement('DROP TABLE IF EXISTS project_status_order_test');
        DB::statement('CREATE TABLE project_status_order_test (id INTEGER PRIMARY KEY, status_id INTEGER)');

        DB::table('project_status_order_test')->insert([
            ['id' => 1, 'status_id' => 1],
            ['id' => 2, 'status_id' => 9],
            ['id' => 3, 'status_id' => 2],
        ]);

        $orderedIds = DB::table('project_status_order_test')
            ->orderByRaw('CASE status_id WHEN 9 THEN 1 WHEN 2 THEN 2 WHEN 1 THEN 3 ELSE 4 END')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([2, 3, 1], $orderedIds);

        DB::statement('DROP TABLE IF EXISTS project_status_order_test');
    }
}
