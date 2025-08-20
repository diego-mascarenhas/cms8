<?php

namespace App\Jobs;

use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDomainSiteType implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 2;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 60;

	/**
	 * The domain ID to process.
	 *
	 * @var int
	 */
	protected $domainId;

	/**
	 * Create a new job instance.
	 */
	public function __construct(int $domainId)
	{
		$this->domainId = $domainId;
		$this->onQueue('domain-updates');
	}

	/**
	 * Execute the job.
	 */
	public function handle()
	{
		Log::info("Processing WordPress detection for domain ID: {$this->domainId}");

		try
		{
			$domain = Domain::find($this->domainId);

			if (! $domain)
			{
				Log::warning("Domain with ID {$this->domainId} not found");

				return;
			}

			Log::info("Checking if {$domain->domain} is a WordPress site...");

			$wasWp = $domain->site_type === 'WordPress';
			$domain->updateSiteType();
			$isWp = $domain->site_type === 'WordPress';

			if (! $wasWp && $isWp)
			{
				Log::info("Domain {$domain->domain} was detected as WordPress and updated");
			} elseif ($wasWp && $isWp)
			{
				Log::info("Domain {$domain->domain} was already marked as WordPress");
			} else
			{
				Log::info("Domain {$domain->domain} is not a WordPress site");
			}
		} catch (\Exception $e)
		{
			Log::error("Error processing WordPress detection for domain ID {$this->domainId}: ".$e->getMessage());
			throw $e;
		}
	}
}
