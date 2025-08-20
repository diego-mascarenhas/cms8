<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class DiagnoseSmtpConfiguration extends Command
{
	use ConfiguresTeamMail;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'smtp:diagnose
							{--team= : Test specific team ID only}
							{--send-test : Send actual test email}
							{--verbose : Show detailed output}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Diagnose SMTP configurations for teams and compare with system defaults';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->info('🔍 SMTP Configuration Diagnosis Tool');
		$this->info('=====================================');
		$this->newLine();

		// Store original configuration
		$originalConfig = $this->getOriginalConfig();

		$this->info('🌍 SYSTEM (.env) Configuration:');
		$this->displayConfiguration($originalConfig, 'system');
		$this->newLine();

		// Get teams to test
		$teams = $this->getTeamsToTest();

		if ($teams->isEmpty())
		{
			$this->error('No teams found to test.');

			return 1;
		}

		foreach ($teams as $team)
		{
			$this->diagnoseTeam($team, $originalConfig);
		}

		$this->info('✅ Diagnosis completed!');

		return 0;
	}

	protected function getTeamsToTest()
	{
		if ($teamId = $this->option('team'))
		{
			return Team::where('id', $teamId)->get();
		}

		// Get teams with email configurations
		return Team::whereHas('settings', function ($query)
		{
			$query->where('key', 'mail_host');
		})->get();
	}

	protected function getOriginalConfig()
	{
		return [
			'host' => env('MAIL_HOST'),
			'port' => env('MAIL_PORT'),
			'username' => env('MAIL_USERNAME'),
			'password' => env('MAIL_PASSWORD'),
			'encryption' => env('MAIL_ENCRYPTION'),
			'from_address' => env('MAIL_FROM_ADDRESS'),
			'from_name' => env('MAIL_FROM_NAME'),
		];
	}

	protected function diagnoseTeam(Team $team, array $originalConfig)
	{
		$this->info("🏢 Team: {$team->name} (ID: {$team->id})");
		$this->info(str_repeat('-', 50));

		// Check if team has custom SMTP
		$hasCustomSmtp = $team->hasOutgoingEmailConfig();

		if ($hasCustomSmtp)
		{
			$this->info('📬 Team HAS custom SMTP configuration');

			$teamConfig = $team->getOutgoingEmailConfig();
			$this->displayConfiguration($teamConfig, 'team');

			// Validate configuration
			$this->validateTeamConfiguration($team, $teamConfig);
		} else
		{
			$this->info('📧 Team uses SYSTEM SMTP configuration');
			$this->warn('Will show advertising footer');
		}

		// Test configuration by applying it
		$this->info('🔧 Testing configuration application...');

		// Store current Laravel config
		$beforeConfig = $this->getCurrentLaravelConfig();

		// Apply team configuration
		$this->configureMailForTeam($team);

		// Get config after application
		$afterConfig = $this->getCurrentLaravelConfig();

		// Compare configurations
		$this->compareConfigurations($beforeConfig, $afterConfig, $hasCustomSmtp ? 'team' : 'system');

		// Send test email if requested
		if ($this->option('send-test'))
		{
			$this->sendTestEmail($team);
		}

		$this->newLine();
	}

	protected function displayConfiguration(array $config, string $type)
	{
		$this->table(
			['Setting', 'Value', 'Status'],
			[
				['Host', $config['host'] ?? 'NULL', $this->getStatus($config['host'])],
				['Port', $config['port'] ?? 'NULL', $this->getStatus($config['port'])],
				['Username', $config['username'] ?? 'NULL', $this->getStatus($config['username'])],
				['Password', $config['password'] ? '****' : 'NULL', $this->getStatus($config['password'])],
				['Encryption', $config['encryption'] ?? 'NULL', $this->getStatus($config['encryption'])],
				['From Address', $config['from_address'] ?? 'NULL', $this->getStatus($config['from_address'])],
				['From Name', $config['from_name'] ?? 'NULL', $this->getStatus($config['from_name'])],
			],
		);
	}

	protected function getStatus($value)
	{
		return empty($value) ? '<fg=red>MISSING</>' : '<fg=green>OK</>';
	}

	protected function validateTeamConfiguration(Team $team, array $config)
	{
		$issues = [];

		if (empty($config['host']))
		{
			$issues[] = 'SMTP Host is missing';
		}

		if (empty($config['username']))
		{
			$issues[] = 'SMTP Username is missing';
		}

		if (empty($config['password']))
		{
			$issues[] = 'SMTP Password is missing';
		}

		if (empty($config['from_address']) || ! filter_var($config['from_address'], FILTER_VALIDATE_EMAIL))
		{
			$issues[] = 'From Address is missing or invalid';
		}

		if (! empty($issues))
		{
			$this->error('❌ Configuration Issues Found:');
			foreach ($issues as $issue)
			{
				$this->error("   • {$issue}");
			}
		} else
		{
			$this->info('✅ Configuration appears valid');
		}

		// Test connection if host is available
		if (! empty($config['host']) && ! empty($config['port']))
		{
			$this->testConnection($config['host'], $config['port']);
		}
	}

	protected function testConnection($host, $port)
	{
		$this->info("🔌 Testing connection to {$host}:{$port}...");

		$socket = @fsockopen($host, $port, $errno, $errstr, 10);

		if ($socket)
		{
			fclose($socket);
			$this->info('✅ Connection successful');
		} else
		{
			$this->error("❌ Connection failed: {$errstr} ({$errno})");
		}
	}

	protected function getCurrentLaravelConfig()
	{
		return [
			'host' => config('mail.mailers.smtp.host'),
			'port' => config('mail.mailers.smtp.port'),
			'username' => config('mail.mailers.smtp.username'),
			'password' => config('mail.mailers.smtp.password'),
			'encryption' => config('mail.mailers.smtp.encryption'),
			'from_address' => config('mail.from.address'),
			'from_name' => config('mail.from.name'),
		];
	}

	protected function compareConfigurations(array $before, array $after, string $expectedType)
	{
		$this->info('📊 Configuration Comparison:');

		$differences = [];
		foreach ($before as $key => $beforeValue)
		{
			$afterValue = $after[$key];
			if ($beforeValue !== $afterValue)
			{
				$differences[] = [
					ucfirst(str_replace('_', ' ', $key)),
					$beforeValue ?? 'NULL',
					$afterValue ?? 'NULL',
					$beforeValue !== $afterValue ? '<fg=yellow>CHANGED</>' : '<fg=green>SAME</>',
				];
			} else
			{
				if ($this->option('verbose'))
				{
					$differences[] = [
						ucfirst(str_replace('_', ' ', $key)),
						$beforeValue ?? 'NULL',
						$afterValue ?? 'NULL',
						'<fg=green>SAME</>',
					];
				}
			}
		}

		if (! empty($differences))
		{
			$this->table(['Setting', 'Before', 'After', 'Status'], $differences);
		} else
		{
			$this->info('No configuration changes detected');
		}
	}

	protected function sendTestEmail(Team $team)
	{
		$this->info('📧 Sending test email...');

		try
		{
			$testRecipient = $team->owner->email ?? 'test@example.com';

			Mail::raw('This is a test email sent by SMTP diagnosis tool.', function ($message) use ($testRecipient, $team)
			{
				$message->to($testRecipient)
					->subject("SMTP Test for Team: {$team->name}");
			});

			$this->info("✅ Test email sent successfully to: {$testRecipient}");
		} catch (\Exception $e)
		{
			$this->error("❌ Failed to send test email: {$e->getMessage()}");

			if ($this->option('verbose'))
			{
				$this->error('Exception: '.get_class($e));
				$this->error("File: {$e->getFile()}:{$e->getLine()}");
			}
		}
	}
}
