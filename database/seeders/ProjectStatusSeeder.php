<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = [
            [
                'id' => 1,
                'name' => 'BUDGET',
                'label_class' => 'bg-label-primary'
            ],
            [
                'id' => 2,
                'name' => 'BUDGETED',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 3,
                'name' => 'AUTHORIZED',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 4,
                'name' => 'SENT',
                'label_class' => 'bg-label-info'
            ],
            [
                'id' => 5,
                'name' => 'RECEIVED',
                'label_class' => 'bg-label-info'
            ],
            [
                'id' => 7,
                'name' => 'APPROVED',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 8,
                'name' => 'WAITING_FOR_RESPONSE',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 9,
                'name' => 'IN_PROGRESS',
                'label_class' => 'bg-label-primary'
            ],
            [
                'id' => 10,
                'name' => 'FINISHED',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 11,
                'name' => 'TO_INVOICE',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 12,
                'name' => 'INVOICED',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 13,
                'name' => 'NOT_APPROVED',
                'label_class' => 'bg-label-danger'
            ],
        ];

        DB::table('project_statuses')->insert($statuses);
    }
} 