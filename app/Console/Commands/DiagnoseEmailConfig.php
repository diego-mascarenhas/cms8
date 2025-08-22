<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseEmailConfig extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'email:diagnose';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Diagnose email configuration and provider selection';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('=== DIAGNÓSTICO DE CONFIGURACIÓN EMAIL ===');
		$this->newLine();

		$this->info('--- Variables ENV ---');
		$this->line('EMAIL_PROVIDER: ' . env('EMAIL_PROVIDER', 'NOT_SET'));
		$this->line('MAILBABY_ENABLED: ' . env('MAILBABY_ENABLED', 'NOT_SET'));
		$this->line('MAILBABY_API_KEY: ' . (env('MAILBABY_API_KEY') ? 'CONFIGURADO' : 'NOT_SET'));
		$this->line('MAILGUN_SECRET: ' . (env('MAILGUN_SECRET') ? 'CONFIGURADO' : 'NOT_SET'));
		$this->line('MAIL_HOST: ' . env('MAIL_HOST', 'NOT_SET'));
		$this->line('MAIL_USERNAME: ' . env('MAIL_USERNAME', 'NOT_SET'));
		$this->newLine();

		$this->info('--- Config Services ---');
		$this->line('services.email.provider: ' . config('services.email.provider', 'NOT_SET'));
		$this->line('services.mailbaby.enabled: ' . (config('services.mailbaby.enabled', false) ? 'true' : 'false'));
		$this->line('services.mailbaby.api_key: ' . (config('services.mailbaby.api_key') ? 'CONFIGURADO' : 'NOT_SET'));
		$this->line('services.mailgun.secret: ' . (config('services.mailgun.secret') ? 'CONFIGURADO' : 'NOT_SET'));
		$this->newLine();

		$this->info('--- Lógica del Job ---');
		$emailProvider = env('EMAIL_PROVIDER', 'smtp');
		$mailbabyEnabled = config('services.mailbaby.enabled', false);
		$mailbabyApiKey = config('services.mailbaby.api_key');
		$mailgunSecret = config('services.mailgun.secret');

		$this->line('Proveedor seleccionado: ' . $emailProvider);
		$this->line('MailBaby habilitado: ' . ($mailbabyEnabled ? 'SÍ' : 'NO'));
		$this->line('MailBaby tiene API key: ' . (!empty($mailbabyApiKey) ? 'SÍ' : 'NO'));
		$this->line('Mailgun tiene secret: ' . (!empty($mailgunSecret) ? 'SÍ' : 'NO'));
		$this->newLine();

		$this->info('--- Qué proveedor usará ---');
		switch ($emailProvider) {
			case 'mailbaby':
				if ($mailbabyEnabled && $mailbabyApiKey) {
					$this->line('✅ Usará: MailBaby API');
				} else {
					$this->line('❌ MailBaby seleccionado pero mal configurado');
					$this->line('   - MailBaby enabled: ' . ($mailbabyEnabled ? 'SÍ' : 'NO'));
					$this->line('   - API key: ' . (!empty($mailbabyApiKey) ? 'SÍ' : 'NO'));
				}
				break;
			case 'mailgun':
				if ($mailgunSecret) {
					$this->line('✅ Usará: Mailgun API');
				} else {
					$this->line('❌ Mailgun seleccionado pero no configurado');
				}
				break;
			case 'smtp':
			default:
				$this->line('✅ Usará: SMTP (' . env('MAIL_HOST', 'NOT_SET') . ')');
				break;
		}

		$this->newLine();
		$this->info('--- Últimos MessageDeliveries ---');
		$deliveries = \App\Models\MessageDelivery::orderBy('id', 'desc')->limit(5)->get();
		foreach ($deliveries as $delivery) {
			$contact = $delivery->contact;
			$status = $delivery->status_id;
			$provider = $delivery->email_provider ?? 'N/A';
			$sent = $delivery->sent_at ? 'SÍ' : 'NO';

			$this->line("ID: {$delivery->id} | Email: " . ($contact ? $contact->email : 'N/A') . " | Status: {$status} | Provider: {$provider} | Sent: {$sent}");
		}

		$this->newLine();
		if ($emailProvider === 'smtp' && $mailbabyEnabled) {
			$this->warn('⚠️  PROBLEMA DETECTADO:');
			$this->warn('   EMAIL_PROVIDER=smtp pero MAILBABY_ENABLED=true');
			$this->warn('   Esto puede estar causando conflictos.');
			$this->warn('   Solución: Configurar MAILBABY_ENABLED=false en .env');
		}

		return Command::SUCCESS;
	}
}
