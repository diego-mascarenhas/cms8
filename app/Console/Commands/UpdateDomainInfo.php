<?php

namespace App\Console\Commands;

use App\Jobs\UpdateDomainInfo as UpdateDomainInfoJob;
use Illuminate\Console\Command;

class UpdateDomainInfo extends Command
{
    protected $signature = 'whm:update-domain-info';
    protected $description = 'Update domain information for all domains';

    public function handle()
    {
        $this->info('Dispatching domain info update job...');

        dispatch(new UpdateDomainInfoJob);

        $this->info('Domain info update job dispatched successfully!');
    }
}
