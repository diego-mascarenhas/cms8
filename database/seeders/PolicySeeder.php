<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Policies\ClientPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;

class PolicySeeder extends Seeder
{
    public function run()
    {
        Gate::policy(Enterprise::class, ClientPolicy::class);
    }
}
