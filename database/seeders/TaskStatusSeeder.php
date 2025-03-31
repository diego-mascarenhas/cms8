<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaskStatus;

class TaskStatusSeeder extends Seeder
{
    public function run()
    {
        $statuses = [
            [
                'name' => 'TO_DO',
                'label_class' => 'bg-label-secondary',
                'order' => 1
            ],
            [
                'name' => 'IN_PROGRESS',
                'label_class' => 'bg-label-primary',
                'order' => 2
            ],
            [
                'name' => 'REVIEW',
                'label_class' => 'bg-label-warning',
                'order' => 3
            ],
            [
                'name' => 'DONE',
                'label_class' => 'bg-label-success',
                'order' => 4
            ]
        ];

        foreach ($statuses as $status) {
            TaskStatus::create($status);
        }
    }
} 