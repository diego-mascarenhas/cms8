<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\History;

class CheckForIncomingVoice extends Command
{
	protected $signature = 'check:incoming-voice';
	protected $description = 'Check for new incoming voice records in history table';

	public function __construct()
	{
		parent::__construct();
	}

	public function handle()
	{
		$newRecords = History::where('processed', false)->get();

		if ($newRecords->isEmpty())
		{
			$this->info('No new records found.');
			return;
		}

		foreach ($newRecords as $record)
		{
			$this->info('Processing record ID: ' . $record->id);

			$record->processed = true;
			$record->save();
		}

		$this->info('Checked and processed new records.');
	}
}