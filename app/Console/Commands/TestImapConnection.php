<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class TestImapConnection extends Command
{
	protected $signature = 'mail:test-imap';

	protected $description = 'Test IMAP connection and fetch emails';

	public function handle()
	{
		try
		{
			// Set up IMAP configuration
			$config = [
				'host' => env('MAILBOX_HOST'),
				'port' => env('MAILBOX_PORT', 993),
				'encryption' => env('MAILBOX_ENCRYPTION', 'ssl'),
				'validate_cert' => env('MAILBOX_VALIDATE_CERT', true),
				'username' => env('MAILBOX_USERNAME'),
				'password' => env('MAILBOX_PASSWORD'),
				'protocol' => 'imap',
			];

			// Test connection
			$this->info('Connecting to IMAP...');
			$client = (new ClientManager)->make($config);
			$client->connect();
			$this->info('Connected successfully!');

			// Fetch and display messages
			$folder = $client->getFolder('INBOX');
			$messages = $folder->messages()
				->limit(5)
				->all()
				->get();

			$this->info('Found '.$messages->count().' messages');

			// Display message details
			foreach ($messages as $message)
			{
				$this->line('-------------------');
				$this->info('Subject: '.$message->getSubject()->first());
				$this->info('From: '.$message->getFrom()->first()->mail);
				$this->info('Date: '.$message->getDate()->first());
			}
		} catch (\Exception $e)
		{
			$this->error('Error: '.$e->getMessage());
		}
	}
}
