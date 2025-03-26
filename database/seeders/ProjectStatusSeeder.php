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
                'name' => 'Budget',
                'label_class' => 'bg-label-primary'
            ],
            [
                'id' => 2,
                'name' => 'Budgeted',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 3,
                'name' => 'Authorized',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 4,
                'name' => 'Sent',
                'label_class' => 'bg-label-info'
            ],
            [
                'id' => 5,
                'name' => 'Received',
                'label_class' => 'bg-label-info'
            ],
            [
                'id' => 7,
                'name' => 'Approved',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 8,
                'name' => 'Waiting for response',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 9,
                'name' => 'In progress',
                'label_class' => 'bg-label-primary'
            ],
            [
                'id' => 10,
                'name' => 'Finished',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 11,
                'name' => 'To invoice',
                'label_class' => 'bg-label-warning'
            ],
            [
                'id' => 12,
                'name' => 'Invoiced',
                'label_class' => 'bg-label-success'
            ],
            [
                'id' => 13,
                'name' => 'Not approved',
                'label_class' => 'bg-label-danger'
            ],
        ];

        DB::table('project_statuses')->insert($statuses);
    }
} 