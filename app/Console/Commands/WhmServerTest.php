<?php

namespace App\Console\Commands;

use App\Jobs\WhmServerTest as WhmServerTestJob;
use Illuminate\Console\Command;

class WhmServerTest extends Command
{
    protected $signature = 'whm:test-servers';
    protected $description = 'Test WHM servers connections';

    public function handle()
    {
        $this->info('Testing WHM servers...');

        dispatch(new WhmServerTestJob);

        $this->info('Job dispatched successfully!');
    }
}
