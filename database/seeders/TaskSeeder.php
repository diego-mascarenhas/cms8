<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run()
    {
        // Get existing data to use in relationships
        $users = User::pluck('id')->toArray();
        $teams = Team::pluck('id')->toArray();
        $statuses = TaskStatus::pluck('id')->toArray();

        // Create 50 fake tasks
        for ($i = 0; $i < 50; $i++) {
            $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
            $dueDate = fake()->dateTimeBetween($startDate, '+2 months');

            Task::create([
                'team_id' => fake()->randomElement($teams),
                'responsible_id' => fake()->randomElement($users),
                'title' => fake()->sentence(4),
                'description' => fake()->paragraphs(2, true),
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'order' => $i,
                'status_id' => fake()->randomElement($statuses),
            ]);
        }

        // Create some tasks for current month specifically
        for ($i = 0; $i < 10; $i++) {
            $startDate = fake()->dateTimeBetween('now', '+1 week');
            $dueDate = fake()->dateTimeBetween('+1 week', '+2 weeks');

            Task::create([
                'team_id' => fake()->randomElement($teams),
                'responsible_id' => fake()->randomElement($users),
                'title' => 'Current Task: ' . fake()->sentence(3),
                'description' => fake()->paragraphs(2, true),
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'order' => $i + 50,
                'status_id' => fake()->randomElement($statuses),
            ]);
        }
    }
}
