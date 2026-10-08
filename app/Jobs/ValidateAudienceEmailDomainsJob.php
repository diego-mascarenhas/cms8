<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\Mail\EmailDomainDns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ValidateAudienceEmailDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $teamId, public string $token = '') {}

    public static function cacheKey(int $teamId): string
    {
        return 'mailer:audience:domain-check:'.$teamId;
    }

    public static function markRunning(int $teamId, string $token): void
    {
        Cache::put(self::cacheKey($teamId), [
            'status' => 'running',
            'token' => $token,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));
    }

    public static function isRunning(int $teamId): bool
    {
        $state = Cache::get(self::cacheKey($teamId));
        if (! is_array($state) || ($state['status'] ?? '') !== 'running')
        {
            return false;
        }

        $startedAt = $state['started_at'] ?? null;
        if (! is_string($startedAt) || $startedAt === '')
        {
            return false;
        }

        return Carbon::parse($startedAt)->greaterThan(now()->subMinutes(30));
    }

    public function handle(EmailDomainDns $dns): void
    {
        try
        {
            $known = [];

            Contact::withoutGlobalScopes()
                ->where('team_id', $this->teamId)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($contacts) use ($dns, &$known): void
                {
                    foreach ($contacts as $contact)
                    {
                        $domain = EmailDomainDns::domain((string) $contact->email) ?? '';
                        if (! array_key_exists($domain, $known))
                        {
                            $known[$domain] = $domain !== '' && $dns->domainAcceptsMail($domain);
                        }

                        $contact->applyEmailDomainCheck($known[$domain]);
                    }
                });
        } finally
        {
            $this->releaseRunningFlag();
        }
    }

    private function releaseRunningFlag(): void
    {
        $key = self::cacheKey($this->teamId);
        $state = Cache::get($key);
        if (is_array($state) && (string) ($state['token'] ?? '') === $this->token)
        {
            Cache::forget($key);
        }
    }
}
