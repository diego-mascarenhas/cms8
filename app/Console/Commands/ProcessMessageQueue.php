<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessMessageQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:process-queue {--timeout=60}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the message campaigns queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');

        $this->info("🚀 Starting message campaigns queue worker...");
        $this->info("⏱️ Timeout: {$timeout} seconds");

        // Process the message-campaigns queue
        Artisan::call('queue:work', [
            '--queue' => 'message-campaigns',
            '--timeout' => $timeout,
            '--tries' => 3,
            '--delay' => 3,
            '--rest' => 1,
            '--verbose' => true
        ]);

        $this->info("✅ Queue processing completed!");

        return 0;
    }
}
