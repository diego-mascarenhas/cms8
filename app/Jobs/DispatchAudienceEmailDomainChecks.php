<?php

namespace App\Jobs;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class DispatchAudienceEmailDomainChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public int $teamId,
        public string $token,
        public string $startedAt,
    ) {}

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('mailer-audience-domain-dispatch-'.$this->teamId))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    public function handle(): void
    {
        if (! ValidateAudienceEmailDomainsJob::isRunning($this->teamId))
        {
            return;
        }

        $dispatched = 0;

        Contact::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(200, function ($contacts) use (&$dispatched): void
            {
                foreach ($contacts as $contact)
                {
                    ValidateAudienceEmailDomainsJob::dispatch(
                        $this->teamId,
                        $this->token,
                        $this->startedAt,
                        (int) $contact->id,
                    );
                    $dispatched++;
                }
            });

        ValidateAudienceEmailDomainsJob::alignTotal($this->teamId, $this->token, $dispatched);
    }
}
