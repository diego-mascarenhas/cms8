<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnterpriseFiscalConditionTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('enterprise_fiscal_condition_types')->insert([
            [
                'id' => 1,
                'name' => 'Registered',
            ],
            [
                'id' => 2,
                'name' => 'Monotax',
            ],
            [
                'id' => 3,
                'name' => 'Final Consumer',
            ],
            [
                'id' => 4,
                'name' => 'Exempt',
            ],
        ]);
    }
}
