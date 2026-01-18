<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Squareetlabs\VeriFactu\Services\AeatClient;
use Squareetlabs\VeriFactu\Models\Invoice as VerifactuInvoice;
use Squareetlabs\VeriFactu\Enums\InvoiceType;
use Illuminate\Support\Facades\File;

class TestVerifactuConnection extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'verifactu:test-connection {--send-test : Send a test invoice to AEAT}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Test Verifactu connection and configuration';

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		$this->info('🔍 Testing Verifactu Configuration...');
		$this->newLine();

		// Check if Verifactu is enabled
		if (!config('verifactu.enabled'))
		{
			$this->error('❌ Verifactu is disabled in configuration.');
			$this->info('Set VERIFACTU_ENABLED=true in your .env file.');

			return Command::FAILURE;
		}

		$this->info('✅ Verifactu is enabled');
		$this->newLine();

		// Check issuer configuration
		$issuerName = config('verifactu.issuer.name');
		$issuerVat = config('verifactu.issuer.vat');

		if (empty($issuerName) || empty($issuerVat))
		{
			$this->error('❌ Issuer information is missing.');
			$this->info('Please set VERIFACTU_ISSUER_NAME and VERIFACTU_ISSUER_VAT in your .env file.');

			return Command::FAILURE;
		}

		$this->info('✅ Issuer configuration:');
		$this->line("   Name: {$issuerName}");
		$this->line("   VAT: {$issuerVat}");
		$this->newLine();

		// Check certificate configuration
		$certPath = config('verifactu.certificate.path');
		$certPassword = config('verifactu.certificate.password');
		$production = config('verifactu.production', false);

		if (empty($certPath))
		{
			$this->error('❌ Certificate path is not configured.');
			$this->info('Please set VERIFACTU_CERT_PATH in your .env file.');

			return Command::FAILURE;
		}

		// Check if certificate file exists
		if (!File::exists($certPath))
		{
			$this->error("❌ Certificate file not found: {$certPath}");
			$this->info('Please check the VERIFACTU_CERT_PATH in your .env file.');

			return Command::FAILURE;
		}

		$this->info('✅ Certificate configuration:');
		$this->line("   Path: {$certPath}");
		$this->line("   Password: " . ($certPassword ? '***' : 'Not set'));
		$this->line("   Environment: " . ($production ? 'Production' : 'Testing (prewww1.aeat.es)'));
		$this->newLine();

		// Test SOAP connection
		$this->info('🔌 Testing SOAP connection to AEAT...');

		try
		{
			$client = new AeatClient($certPath, $certPassword, $production);

			// Test WSDL access (this will validate certificate and connection)
			$wsdl = $production
				? 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP?wsdl'
				: 'https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl';

			$options = [
				'local_cert' => $certPath,
				'passphrase' => $certPassword,
				'trace' => true,
				'exceptions' => true,
				'cache_wsdl' => 0,
				'soap_version' => SOAP_1_1,
				'connection_timeout' => 30,
				'stream_context' => stream_context_create([
					'ssl' => [
						'verify_peer' => true,
						'verify_peer_name' => true,
						'allow_self_signed' => false,
						'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
					],
					'http' => [
						'user_agent' => 'LaravelVerifactu/1.0',
					],
				]),
			];

			$soapClient = new \SoapClient($wsdl, $options);

			$this->info('✅ SOAP client created successfully');
			$this->line("   Environment: " . ($production ? 'Production (www1.aeat.es)' : 'Testing (prewww1.aeat.es)'));
			$this->line("   WSDL: {$wsdl}");
			$this->newLine();

			// If --send-test flag is provided, send a test invoice
			if ($this->option('send-test'))
			{
				$this->info('📤 Sending test invoice to AEAT...');
				$this->newLine();

				$testInvoice = $this->createTestInvoice();
				$result = $client->sendInvoice($testInvoice);

				if ($result['status'] === 'success')
				{
					$this->info('✅ Test invoice sent successfully!');
					$this->line("   Hash: {$result['hash']}");
					$this->line("   Number: {$result['number']}");
					$this->line("   Date: {$result['date']}");
					$this->newLine();
					$this->info('Response from AEAT:');
					$this->line(json_encode($result['aeat_response'], JSON_PRETTY_PRINT));
				} else
				{
					$this->error('❌ Failed to send test invoice:');
					$this->error($result['message'] ?? 'Unknown error');
					if (isset($result['response']))
					{
						$this->newLine();
						$this->line('Response:');
						$this->line($result['response']);
					}

					return Command::FAILURE;
				}
			} else
			{
				$this->info('💡 Tip: Use --send-test flag to send a test invoice to AEAT');
			}
		} catch (\SoapFault $e)
		{
			$this->error('❌ SOAP connection failed:');
			$this->error($e->getMessage());
			$this->newLine();
			$this->line('Common issues:');
			$this->line('  - Certificate file is invalid or corrupted');
			$this->line('  - Certificate password is incorrect');
			$this->line('  - Certificate has expired');
			$this->line('  - Network connectivity issues');
			$this->line('  - WSDL endpoint is not accessible');

			return Command::FAILURE;
		} catch (\Exception $e)
		{
			$this->error('❌ Connection test failed:');
			$this->error($e->getMessage());
			$this->newLine();
			$this->line('Error details:');
			$this->line($e->getTraceAsString());

			return Command::FAILURE;
		}

		$this->newLine();
		$this->info('✨ All tests passed! Verifactu is properly configured.');

		return Command::SUCCESS;
	}

	/**
	 * Create a test invoice for testing purposes
	 */
	private function createTestInvoice(): VerifactuInvoice
	{
		$invoice = new VerifactuInvoice();
		$invoice->number = 'TEST-' . now()->format('YmdHis');
		$invoice->date = now();
		$invoice->customer_name = 'Test Customer';
		$invoice->customer_tax_id = 'B12345678';
		$invoice->issuer_name = config('verifactu.issuer.name');
		$invoice->issuer_tax_id = config('verifactu.issuer.vat');
		$invoice->amount = 100.00;
		$invoice->tax = 21.00;
		$invoice->total = 121.00;
		$invoice->type = InvoiceType::STANDARD;
		$invoice->description = 'Test invoice for Verifactu connection';
		$invoice->status = 'draft';

		return $invoice;
	}
}
