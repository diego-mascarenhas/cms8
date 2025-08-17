<?php

namespace App\Console\Commands;

use App\Services\TwilioService;
use Illuminate\Console\Command;

class TestProductSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:products {phone : Phone number to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the product system with a phone number';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');

        $this->info("Testing product system with phone: {$phone}");

        // Test different product-related messages
        $testMessages = [
            'productos',
            'servicios',
            'catalogo',
            'precios',
            'hosting',
            'dominio',
            'desarrollo web',
        ];

        $twilioService = new TwilioService;

        foreach ($testMessages as $message) {
            $this->info("\nTesting message: '{$message}'");

            try {
                $response = $twilioService->processProductCommands($phone, $message);

                if ($response) {
                    $this->info('✅ Response: '.($response['message'] ?? 'Success'));
                } else {
                    $this->warn('⚠️ No response (message not recognized)');
                }
            } catch (\Exception $e) {
                $this->error('❌ Error: '.$e->getMessage());
            }
        }

        $this->info("\n🎉 Product system test completed!");
    }
}
