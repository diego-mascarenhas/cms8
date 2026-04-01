<?php

namespace Database\Seeders;

use App\Models\OpportunityStage;
use Illuminate\Database\Seeder;

class OpportunityStageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Qualification', 'slug' => 'qualification', 'sort_order' => 10],
            ['name' => 'Proposal', 'slug' => 'proposal', 'sort_order' => 20],
            ['name' => 'Negotiation', 'slug' => 'negotiation', 'sort_order' => 30],
            ['name' => 'Won', 'slug' => 'won', 'sort_order' => 40],
            ['name' => 'Lost', 'slug' => 'lost', 'sort_order' => 50],
        ];

        foreach ($rows as $row)
        {
            OpportunityStage::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row,
            );
        }
    }
}
