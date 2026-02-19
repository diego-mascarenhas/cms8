<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnterpriseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds. Idempotent: safe to run multiple times.
     */
    public function run(): void
    {
        $types = [
            1 => 'Cliente',
            2 => 'Proveedor',
            3 => 'Alianza',
        ];

        foreach ($types as $id => $name)
        {
            DB::table('enterprise_types')->updateOrInsert(
                ['id' => $id],
                ['name' => $name],
            );
        }
    }
}
