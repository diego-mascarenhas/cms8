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

    private const RUN_MINUTES = 120;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public int $teamId,
        public string $token = '',
        public string $startedAt = '',
        public int $contactId = 0,
    ) {}

    public static function cacheKey(int $teamId): string
    {
        return 'mailer:audience:domain-check:'.$teamId;
    }

    public static function countContacts(int $teamId): int
    {
        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();
    }

    /**
     * @return array{running: bool, total: int, checked: int}
     */
    public static function progress(int $teamId): array
    {
        $state = Cache::get(self::cacheKey($teamId));
        $running = self::isRunning($teamId);

        if (! $running || ! is_array($state))
        {
            return [
                'running' => false,
                'total' => 0,
                'checked' => 0,
            ];
        }

        $total = (int) ($state['total'] ?? 0);
        $checked = (int) ($state['checked'] ?? 0);
        $startedAt = (string) ($state['started_at'] ?? '');
        $validated = self::countCheckedSince($teamId, $startedAt);
        if ($validated > $checked)
        {
            $checked = min($total, $validated);
            $state['checked'] = $checked;
            if ($total > 0 && $checked >= $total)
            {
                Cache::forget(self::cacheKey($teamId));

                return [
                    'running' => false,
                    'total' => 0,
                    'checked' => 0,
                ];
            }

            Cache::put(self::cacheKey($teamId), $state, now()->addMinutes(self::RUN_MINUTES));
        }

        return [
            'running' => true,
            'total' => $total,
            'checked' => $checked,
        ];
    }

    public static function alignTotal(int $teamId, string $token, int $total): void
    {
        $key = self::cacheKey($teamId);
        Cache::lock($key.':lock', 10)->block(5, function () use ($key, $token, $total): void
        {
            $state = Cache::get($key);
            if (! is_array($state) || (string) ($state['token'] ?? '') !== $token)
            {
                return;
            }

            $state['total'] = $total;
            if ($total < 1 || (int) ($state['checked'] ?? 0) >= $total)
            {
                Cache::forget($key);

                return;
            }

            Cache::put($key, $state, now()->addMinutes(self::RUN_MINUTES));
        });
    }

    public static function markRunning(int $teamId, string $token, int $total = 0): void
    {
        Cache::put(self::cacheKey($teamId), [
            'status' => 'running',
            'token' => $token,
            'total' => $total,
            'checked' => 0,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::RUN_MINUTES));
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

        return Carbon::parse($startedAt)->greaterThan(now()->subMinutes(self::RUN_MINUTES));
    }

    public function handle(EmailDomainDns $dns): void
    {
        $contact = $this->contact();
        if (! $contact instanceof Contact)
        {
            $this->advance(1);

            return;
        }

        if ($this->startedAt !== '' && $this->checkedDuringThisRun($contact))
        {
            $this->advance(1);

            return;
        }

        $domain = EmailDomainDns::domain((string) $contact->email) ?? '';
        $accepts = $domain === '' ? false : $this->domainAcceptsMail($dns, $domain);
        $contact->applyEmailDomainCheck($accepts);
        $this->advance(1);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->advance(1);
    }

    private function contact(): ?Contact
    {
        if ($this->contactId < 1)
        {
            return null;
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->whereKey($this->contactId)
            ->first();
    }

    private function domainAcceptsMail(EmailDomainDns $dns, string $domain): ?bool
    {
        if ($this->token === '')
        {
            return $dns->domainAcceptsMail($domain);
        }

        $key = self::cacheKey($this->teamId).':mx:'.$this->token.':'.$domain;
        $cached = Cache::get($key);
        if (is_bool($cached))
        {
            return $cached;
        }

        if ($cached === 'unknown')
        {
            return null;
        }

        $accepts = $dns->domainAcceptsMail($domain);
        Cache::put($key, $accepts === null ? 'unknown' : $accepts, now()->addMinutes(self::RUN_MINUTES));

        return $accepts;
    }

    private function advance(int $count): void
    {
        if ($count < 1 || $this->token === '')
        {
            return;
        }

        $key = self::cacheKey($this->teamId);
        Cache::lock($key.':lock', 10)->block(5, function () use ($key, $count): void
        {
            $state = Cache::get($key);
            if (! is_array($state) || (string) ($state['token'] ?? '') !== $this->token)
            {
                return;
            }

            if ($this->contactId > 0)
            {
                $counted = is_array($state['counted'] ?? null) ? $state['counted'] : [];
                $contactKey = (string) $this->contactId;
                if (isset($counted[$contactKey]))
                {
                    return;
                }

                $counted[$contactKey] = 1;
                $state['counted'] = $counted;
                $count = 1;
            }

            $total = (int) ($state['total'] ?? 0);
            $state['checked'] = min($total, (int) ($state['checked'] ?? 0) + $count);
            if ($total > 0 && (int) $state['checked'] >= $total)
            {
                Cache::forget($key);

                return;
            }

            Cache::put($key, $state, now()->addMinutes(self::RUN_MINUTES));
        });
    }

    private function checkedDuringThisRun(Contact $contact): bool
    {
        $checkedAt = $this->emailCheckedAt($contact);
        if ($checkedAt === null || $this->startedAt === '')
        {
            return false;
        }

        return Carbon::parse($checkedAt)->greaterThanOrEqualTo(Carbon::parse($this->startedAt));
    }

    private function emailCheckedAt(Contact $contact): ?string
    {
        $data = $contact->data;
        if (is_object($data))
        {
            $encoded = json_encode($data);
            $data = is_string($encoded) ? json_decode($encoded, true) : null;
        }

        if (! is_array($data))
        {
            return null;
        }

        $checkedAt = $data['channels']['email']['checked_at'] ?? null;

        return is_string($checkedAt) && $checkedAt !== '' ? $checkedAt : null;
    }

    private static function countCheckedSince(int $teamId, string $startedAt): int
    {
        if ($startedAt === '')
        {
            return 0;
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('data->channels->email->checked_at', '>=', $startedAt)
            ->count();
    }
}
