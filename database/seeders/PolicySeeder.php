<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Policies\ClientPolicy;
use Illuminate\Support\Facades\Gate;

class PolicySeeder extends Seeder
{
    public function run()
    {
        Gate::policy(Client::class, ClientPolicy::class);
    }
}
