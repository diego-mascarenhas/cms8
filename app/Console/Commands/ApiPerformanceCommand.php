<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Services\Api\ApiPerformanceProbe;
use Illuminate\Console\Command;

class ApiPerformanceCommand extends Command
{
    protected $signature = 'api:performance
                            {--team= : Team whose rows fill {id} parameters}
                            {--user= : User whose Sanctum token calls auth routes. Defaults to the team owner}
                            {--timeout=20 : Seconds before a single request is abandoned}
                            {--base-url= : API origin. Defaults to APP_URL}
                            {--token= : Sanctum token issued by the target server}
                            {--team-token= : Team API token issued by the target server}';

    protected $description = 'Measure every API GET and print status, time and size';

    public function handle(ApiPerformanceProbe $probe): int
    {
        $team = $this->resolveTeam();
        if (! $team)
        {
            $this->error('Pass --team= or set TEAM_TOKEN to the team API token.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($team);
        if (! $user)
        {
            $this->error('Team '.$team->id.' has no user to call the API.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $timeout = max(1, (int) $this->option('timeout'));
        $suppliedToken = trim((string) $this->option('token'));
        $suppliedTeamToken = trim((string) $this->option('team-token'));
        $remote = $this->targetsAnotherDatabase($baseUrl);
        $teamToken = $suppliedTeamToken !== '' ? $suppliedTeamToken : (string) config('services.landing_widget.team_token');
        if ($remote && $suppliedToken === '' && $teamToken === '')
        {
            $this->error('Set TEAM_TOKEN in .env to the API token of the team on that server.');

            return self::FAILURE;
        }

        $targets = $probe->targets($team, $baseUrl);
        $accessToken = null;
        if ($remote)
        {
            $userBearer = $suppliedToken !== '' ? $suppliedToken : $teamToken;
        } else
        {
            $accessToken = $suppliedToken === '' ? $user->createToken('api-performance') : null;
            $userBearer = $accessToken?->plainTextToken ?? $suppliedToken;
        }

        $this->info('API GETs on '.$baseUrl.' as user '.$user->id.' of team '.$team->id.'.');
        $this->line('POST, PUT, and DELETE are not called.');
        if ($remote && $suppliedToken === '')
        {
            $this->line('Authenticated routes use TEAM_TOKEN from .env. Sample ids come from this database.');
        } elseif ($remote)
        {
            $this->line('Sample ids come from this database.');
        }
        $this->newLine();

        $measured = [];
        $skipped = [];

        try
        {
            $bar = $this->output->createProgressBar(count($targets));
            $bar->start();

            foreach ($targets as $target)
            {
                $skip = $target['skip'];
                if ($skip === null && $target['auth'] === 'team' && $teamToken === '')
                {
                    $skip = 'TEAM_TOKEN is missing';
                }

                if ($skip !== null)
                {
                    $skipped[$skip] = ($skipped[$skip] ?? 0) + 1;
                    $bar->advance();

                    continue;
                }

                $bearer = match ($target['auth'])
                {
                    'team' => $teamToken,
                    'user' => $userBearer,
                    default => null,
                };

                $result = $probe->measure((string) $target['url'], $bearer, $timeout);
                $measured[] = [
                    'milliseconds' => $result['milliseconds'],
                    'status' => $result['status'] === 0 ? 'error' : (string) $result['status'],
                    'bytes' => $result['bytes'],
                    'uri' => $target['uri'],
                ];
                $bar->advance();
            }

            $bar->finish();
        } finally
        {
            if ($accessToken !== null)
            {
                $user->tokens()->where('name', 'api-performance')->delete();
            }
        }

        $this->newLine(2);
        usort($measured, fn (array $left, array $right): int => $right['milliseconds'] <=> $left['milliseconds']);

        $this->table(
            ['ms', 'status', 'bytes', 'route'],
            array_map(fn (array $row): array => [
                $row['milliseconds'],
                $row['status'],
                $row['bytes'],
                $row['uri'],
            ], $measured),
        );

        $times = array_column($measured, 'milliseconds');
        sort($times);
        $median = $times === [] ? 0 : $times[(int) floor((count($times) - 1) / 2)];
        $buckets = ['<150' => 0, '150-299' => 0, '300-999' => 0, '≥1000' => 0];
        foreach ($times as $time)
        {
            $key = match (true)
            {
                $time >= 1000 => '≥1000',
                $time >= 300 => '300-999',
                $time >= 150 => '150-299',
                default => '<150',
            };
            $buckets[$key]++;
        }

        $this->newLine();
        $this->info('Measured: '.count($measured).'. Skipped: '.array_sum($skipped).'. Median: '.$median.' ms.');
        $this->line('<150 ms: '.$buckets['<150'].' · 150-299: '.$buckets['150-299'].' · 300-999: '.$buckets['300-999'].' · ≥1000: '.$buckets['≥1000']);

        foreach ($skipped as $reason => $count)
        {
            $this->line($count.' skipped: '.$reason.'.');
        }

        return self::SUCCESS;
    }

    private function resolveTeam(): ?Team
    {
        $id = $this->option('team');
        if ($id !== null && $id !== '')
        {
            return Team::query()->find((int) $id);
        }

        $plain = (string) config('services.landing_widget.team_token');
        if ($plain === '')
        {
            return null;
        }

        $hash = hash('sha256', $plain);

        return Team::query()
            ->whereHas('settings', function ($query)
            {
                $query->whereIn('key', ['api_token_hash', 'api_tokens']);
            })
            ->with('settings')
            ->get()
            ->first(fn (Team $team): bool => $team->findApiTokenByHash($hash) !== null);
    }

    private function resolveUser(Team $team): ?User
    {
        $id = $this->option('user');
        if ($id !== null && $id !== '')
        {
            return User::query()->find((int) $id);
        }

        return User::query()->find($team->user_id);
    }

    private function targetsAnotherDatabase(string $baseUrl): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($appHost) && $appHost !== ''
            && is_string($targetHost) && $targetHost !== ''
            && strcasecmp($appHost, $targetHost) !== 0;
    }
}
