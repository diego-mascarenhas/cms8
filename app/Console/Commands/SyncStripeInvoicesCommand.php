<?php

namespace App\Console\Commands;

use App\Models\InvoiceSync;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

class SyncStripeInvoicesCommand extends Command
{
    private const SETTING_BACKFILL_ORDERED_STATE = 'stripe_invoices_backfill_ordered_state';

    private const SETTING_BACKFILL_LEGACY_CURSOR = 'stripe_invoices_sync_cursor';

    private const SETTING_BACKFILL_LEGACY_CURSOR_UPDATED = 'stripe_invoices_sync_cursor_updated_at';

    private const SETTING_BACKFILL_COMPLETED_AT = 'stripe_invoices_backfill_completed_at';

    protected $signature = 'stripe:sync-invoices
                            {--team_id= : Sync only one team}
                            {--mode=auto : auto (backfill then mutable), backfill (ordered by time window), or mutable (refresh invoices that can still change)}
                            {--limit=500 : Maximum invoices to sync per team in this run}
                            {--starting-after= : Stripe invoice id cursor to continue within the current time window (advanced)}
                            {--backfill-window=month : Time window for ordered backfill: month or week}
                            {--backfill-start=2000-01-01 : First window start date (Y-m-d) for ordered backfill}
                            {--reset-backfill : Clear saved backfill progress and start over}
                            {--legacy-cursor : Use legacy full-list cursor backfill (not recommended)}
                            {--no-resume : Ignore saved team cursor and start without checkpoint}
                            {--recent-days=45 : In mutable mode, look back this many days when --from/--to are not set}
                            {--from= : Invoice created date from (Y-m-d)}
                            {--to= : Invoice created date to (Y-m-d)}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill Stripe invoices into invoice_syncs in blocks';

    public function handle(): int
    {
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $mode = strtolower(trim((string) ($this->option('mode') ?? 'auto')));
        if (! in_array($mode, ['auto', 'backfill', 'mutable'], true))
        {
            $this->error("Invalid --mode={$mode}. Allowed: auto, backfill, mutable");

            return self::INVALID;
        }

        $maxPerTeam = max(1, (int) $this->option('limit'));
        $startingAfterOption = trim((string) ($this->option('starting-after') ?? ''));
        $backfillWindow = strtolower(trim((string) ($this->option('backfill-window') ?? 'month')));
        if (! in_array($backfillWindow, ['month', 'week'], true))
        {
            $this->error("Invalid --backfill-window={$backfillWindow}. Allowed: month, week");

            return self::INVALID;
        }
        $backfillStartDefault = trim((string) ($this->option('backfill-start') ?? '2000-01-01'));
        $resetBackfill = (bool) $this->option('reset-backfill');
        $legacyCursor = (bool) $this->option('legacy-cursor');
        $noResume = (bool) $this->option('no-resume');
        $recentDays = max(1, (int) $this->option('recent-days'));
        $dryRun = (bool) $this->option('dry-run');

        $createdFilter = $this->buildCreatedFilter(
            $this->option('from'),
            $this->option('to'),
        );
        if ($mode === 'mutable' && $createdFilter === [])
        {
            $createdFilter['gte'] = now()->subDays($recentDays)->startOfDay()->timestamp;
        }

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey($teamId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found for synchronization.');

            return self::SUCCESS;
        }

        $globalSynced = 0;
        $globalScanned = 0;
        $processedTeams = 0;

        foreach ($teams as $team)
        {
            $secret = trim((string) $team->getSetting('stripe_secret'));
            if ($secret === '')
            {
                $this->line("Skipping team {$team->id} ({$team->name}): missing stripe_secret in team settings.");

                continue;
            }

            if ($resetBackfill)
            {
                if (! $dryRun)
                {
                    $this->resetTeamBackfillState($team);
                }
                $this->line("Team {$team->id}: backfill state reset".($dryRun ? ' (dry-run; no DB change)' : '').'.');
            }

            $effectiveMode = $this->resolveEffectiveModeForTeam($team, $mode);

            $client = new StripeClient($secret);
            $syncedForTeam = 0;
            $scannedForTeam = 0;
            $lastProcessedId = null;
            $backfillStatusLine = null;
            $backfillComplete = false;

            if ($effectiveMode === 'mutable')
            {
                [$syncedForTeam, $scannedForTeam] = $this->syncMutableInvoicesForTeam(
                    $client,
                    $team->id,
                    $maxPerTeam,
                    $createdFilter,
                    $dryRun,
                );
            } else
            {
                if ($legacyCursor)
                {
                    $savedCursor = trim((string) $team->getSetting(self::SETTING_BACKFILL_LEGACY_CURSOR, ''));
                    $startingAfter = $startingAfterOption;
                    if ($startingAfter === '' && ! $noResume)
                    {
                        $startingAfter = $savedCursor;
                    }

                    [$syncedForTeam, $scannedForTeam, $lastProcessedId] = $this->syncBackfillInvoicesForTeam(
                        $client,
                        $team->id,
                        $maxPerTeam,
                        $createdFilter,
                        $startingAfter,
                        $dryRun,
                    );
                } else
                {
                    if (! $noResume)
                    {
                        $this->maybeClearLegacyCursorForOrderedBackfill($team);
                    }

                    $startingAfter = $startingAfterOption;
                    if ($startingAfter === '' && ! $noResume)
                    {
                        $state = $this->getTeamOrderedBackfillState($team);
                        $startingAfter = is_array($state) ? trim((string) ($state['starting_after'] ?? '')) : '';
                    }

                    [$syncedForTeam, $scannedForTeam, $lastProcessedId, $backfillStatusLine, $backfillComplete] = $this->syncOrderedBackfillInvoicesForTeam(
                        $client,
                        $team,
                        $maxPerTeam,
                        $backfillWindow,
                        $backfillStartDefault,
                        $this->option('from'),
                        $this->option('to'),
                        $startingAfter,
                        $noResume,
                        $dryRun,
                    );
                }
            }

            if ($effectiveMode === 'backfill' && $legacyCursor)
            {
                $backfillComplete = $syncedForTeam < $maxPerTeam;
            }

            $processedTeams++;
            $globalSynced += $syncedForTeam;
            $globalScanned += $scannedForTeam;

            $dryText = $dryRun ? ' [dry-run]' : '';
            $this->info("Team {$team->id} ({$team->name}) [{$effectiveMode}]: synced {$syncedForTeam}/{$maxPerTeam} invoices{$dryText}.");
            if ($effectiveMode === 'backfill' && $backfillStatusLine)
            {
                $this->line($backfillStatusLine);
            } elseif ($effectiveMode === 'backfill' && $legacyCursor && $lastProcessedId)
            {
                $this->line("Team {$team->id}: next cursor --legacy-cursor --starting-after={$lastProcessedId}");
            }

            if ($effectiveMode === 'mutable')
            {
                $this->line("Team {$team->id}: mutable refresh done (statuses: draft, open, uncollectible).");
            } else
            {
                if ($legacyCursor)
                {
                    if ($syncedForTeam >= $maxPerTeam)
                    {
                        if (! $dryRun)
                        {
                            $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR, (string) $lastProcessedId, [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                            $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR_UPDATED, now()->toDateTimeString(), [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                        }
                        $this->line("Team {$team->id}: limit reached, run command again to continue with next block.");
                    } else
                    {
                        if (! $dryRun)
                        {
                            $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR, '', [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                            $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR_UPDATED, now()->toDateTimeString(), [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                            $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, now()->toDateTimeString(), [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                        }
                        $this->line("Team {$team->id}: legacy backfill completed; use ordered backfill for chronological history in future.");
                    }
                } else
                {
                    if ($syncedForTeam >= $maxPerTeam)
                    {
                        $this->line("Team {$team->id}: limit reached for this run, run again to continue (ordered windows).");
                    } elseif (! $backfillComplete)
                    {
                        if ($dryRun)
                        {
                            $this->line("Team {$team->id}: dry-run preview (no saved progress).");
                        } else
                        {
                            $this->line("Team {$team->id}: ordered backfill in progress, run again to continue (ordered windows).");
                        }
                    } else
                    {
                        if (! $dryRun)
                        {
                            $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, null, [
                                'type' => 'json',
                                'group' => 'stripe',
                            ]);
                            $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, now()->toDateTimeString(), [
                                'type' => 'string',
                                'group' => 'stripe',
                            ]);
                            $this->clearLegacyBackfillCursor($team);
                        }
                        $this->line("Team {$team->id}: ordered backfill completed; next runs in auto mode will use mutable refresh.");
                    }
                }
            }
        }

        if ($processedTeams === 0)
        {
            $this->warn('No team with stripe_secret found. Configure team Stripe secret key first.');

            return self::SUCCESS;
        }

        $drySuffix = $dryRun ? ' [dry-run]' : '';
        $this->info("Stripe invoices sync complete: teams={$processedTeams}, synced={$globalSynced}, scanned={$globalScanned}{$drySuffix}.");

        return self::SUCCESS;
    }

    private function resolveEffectiveModeForTeam(Team $team, string $requestedMode): string
    {
        if ($requestedMode !== 'auto')
        {
            return $requestedMode;
        }

        $backfillCompletedAt = trim((string) $team->getSetting(self::SETTING_BACKFILL_COMPLETED_AT, ''));
        if ($backfillCompletedAt !== '')
        {
            return 'mutable';
        }

        return 'backfill';
    }

    private function resetTeamBackfillState(Team $team): void
    {
        $this->clearLegacyBackfillCursor($team);
        $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, null, [
            'type' => 'json',
            'group' => 'stripe',
        ]);
        $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, '', [
            'type' => 'string',
            'group' => 'stripe',
        ]);
    }

    private function clearLegacyBackfillCursor(Team $team): void
    {
        $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR, '', [
            'type' => 'string',
            'group' => 'stripe',
        ]);
        $team->setSetting(self::SETTING_BACKFILL_LEGACY_CURSOR_UPDATED, now()->toDateTimeString(), [
            'type' => 'string',
            'group' => 'stripe',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getTeamOrderedBackfillState(Team $team): ?array
    {
        $raw = $team->getSetting(self::SETTING_BACKFILL_ORDERED_STATE, null);
        if (! is_array($raw))
        {
            return null;
        }

        return $raw;
    }

    private function maybeClearLegacyCursorForOrderedBackfill(Team $team): void
    {
        $legacy = trim((string) $team->getSetting(self::SETTING_BACKFILL_LEGACY_CURSOR, ''));
        if ($legacy === '')
        {
            return;
        }

        if ($this->getTeamOrderedBackfillState($team) === null)
        {
            $this->clearLegacyBackfillCursor($team);
            $this->line("Team {$team->id}: cleared legacy backfill cursor; using ordered time-window backfill.");
        }
    }

    /**
     * @return array{0: int, 1: int, 2: string|null, 3: string|null, 4: bool}
     */
    private function syncOrderedBackfillInvoicesForTeam(
        StripeClient $client,
        Team $team,
        int $maxPerTeam,
        string $backfillWindow,
        string $backfillStartDefault,
        mixed $optFrom,
        mixed $optTo,
        string $cliStartingAfter,
        bool $noResume,
        bool $dryRun,
    ): array {
        $teamId = $team->id;
        $now = Carbon::now();
        $globalFrom = $this->parseYmdToStartOfDay($backfillStartDefault) ?? Carbon::create(2000, 1, 1)->startOfDay();
        $globalTo = $this->parseYmdToEndOfDay($optTo) ?? $now->copy()->endOfDay();

        if ($globalFrom->gt($globalTo))
        {
            $this->warn("Team {$teamId}: backfill from/to range invalid, skipping backfill.");

            return [0, 0, null, 'Team backfill: invalid from/to', false];
        }

        if (! $noResume)
        {
            $state = $this->getTeamOrderedBackfillState($team);
        } else
        {
            $state = null;
        }

        if ($state === null)
        {
            $windowStart = $this->maxCarbon($globalFrom, $this->parseYmdToStartOfDay($optFrom) ?? $globalFrom);
        } else
        {
            $ws = (string) ($state['window_start'] ?? '');
            $windowStart = Carbon::parse($ws)->startOfDay();
        }

        if ($windowStart->gt($globalTo))
        {
            if (! $dryRun)
            {
                $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, null, [
                    'type' => 'json',
                    'group' => 'stripe',
                ]);
                $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, now()->toDateTimeString(), [
                    'type' => 'string',
                    'group' => 'stripe',
                ]);
            }

            return [0, 0, null, "Team {$teamId}: no ordered windows in range".($dryRun ? ' (dry-run: no DB changes)' : ', marking backfill done.'), ! $dryRun];
        }

        if ($state !== null)
        {
            $sw = (string) ($state['backfill_window'] ?? '');
            if ($sw !== '' && $sw !== $backfillWindow)
            {
                $this->warn("Team {$teamId}: backfill window type changed; clearing saved progress to avoid mixed checkpoints.");
                if (! $dryRun)
                {
                    $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, null, [
                        'type' => 'json',
                        'group' => 'stripe',
                    ]);
                }
                $state = null;
                $windowStart = $this->maxCarbon($globalFrom, $this->parseYmdToStartOfDay($optFrom) ?? $globalFrom);
            }
        }

        $windowEnd = $this->windowEndFor($windowStart, $backfillWindow);
        if ($windowEnd->gt($globalTo))
        {
            $windowEnd = $globalTo->copy();
        }

        $synced = 0;
        $scanned = 0;
        $lastId = null;
        $startingAfter = $cliStartingAfter;
        if ($startingAfter === '' && $state !== null)
        {
            $startingAfter = trim((string) ($state['starting_after'] ?? ''));
        }

        $label = $windowStart->toDateString().' → '.$windowEnd->toDateString();
        if ($cliStartingAfter !== '')
        {
            $this->line("Team {$teamId}: ordered backfill window {$label} ({$backfillWindow}), manual --starting-after={$cliStartingAfter}");
        } else
        {
            $this->line("Team {$teamId}: ordered backfill window {$label} ({$backfillWindow})");
        }

        $pageIndex = 0;
        $pageStartingAfter = $startingAfter;
        $windowFinished = false;
        $reachedRunLimit = false;
        $hasMoreInWindow = false;

        while ($synced < $maxPerTeam)
        {
            $params = [
                'limit' => 100,
                'expand' => [
                    'data.customer',
                    'data.subscription',
                ],
                'created' => [
                    'gte' => $windowStart->timestamp,
                    'lte' => $windowEnd->timestamp,
                ],
            ];
            if ($pageStartingAfter !== '')
            {
                $params['starting_after'] = $pageStartingAfter;
            }

            $invoices = $client->invoices->all($params);
            $page = $invoices->toArray();
            $raw = is_array($page) ? ($page['data'] ?? []) : [];
            $hasMoreInWindow = (bool) ($page['has_more'] ?? false);
            $pageIndex++;

            if (! is_array($raw) || $raw === [])
            {
                $windowFinished = ! $hasMoreInWindow;
                break;
            }

            $rawLastCursor = $this->lastStripeListItemId($raw);
            $data = array_values(array_filter($raw, 'is_array'));
            if ($data === [])
            {
                $this->warn("Team {$teamId}: page had no valid invoice array rows, skipping page.");
                if (! $hasMoreInWindow)
                {
                    $windowFinished = true;
                } elseif ($rawLastCursor === null)
                {
                    $this->warn("Team {$teamId}: could not read cursor id for pagination; stop to avoid a loop.");
                    break;
                } else
                {
                    $pageStartingAfter = $rawLastCursor;
                }
                continue;
            }

            $remainingSlots = $maxPerTeam - $synced;
            if ($remainingSlots <= 0)
            {
                $reachedRunLimit = true;
                break;
            }

            $take = min($remainingSlots, count($data));
            $apiOrderSlice = array_slice($data, 0, $take);
            $toUpsert = $this->sortInvoicePayloadsOldestFirst($apiOrderSlice);
            $sliceLastCursor = $this->lastStripeListItemId($apiOrderSlice);

            foreach ($toUpsert as $row)
            {
                if ($synced >= $maxPerTeam)
                {
                    $reachedRunLimit = true;
                    break 2;
                }
                $scanned++;
                $lastId = (string) ($row['id'] ?? '');

                if (! $dryRun)
                {
                    $this->upsertInvoiceSyncRow($teamId, $row);
                }
                $synced++;
            }

            if ($reachedRunLimit)
            {
                if ($sliceLastCursor !== null)
                {
                    $lastId = $sliceLastCursor;
                }
                break;
            }

            if ($take < count($data))
            {
                $reachedRunLimit = true;
                if ($sliceLastCursor !== null)
                {
                    $lastId = $sliceLastCursor;
                }
                break;
            }

            if (! $hasMoreInWindow)
            {
                $windowFinished = true;
                break;
            }

            if ($rawLastCursor === null)
            {
                $this->warn("Team {$teamId}: could not read last invoice id for pagination; stop to avoid a loop.");
                $windowFinished = ! $hasMoreInWindow;
                break;
            }

            $pageStartingAfter = $rawLastCursor;
        }

        $statusLine = "Team {$teamId}: backfill status window {$label}, pages={$pageIndex}, synced in this run={$synced}, has_more in window=".($hasMoreInWindow ? 'yes' : 'no');

        if (! $dryRun)
        {
            if ($reachedRunLimit)
            {
                if ($lastId === null || $lastId === '')
                {
                    $this->warn("Team {$teamId}: reached run limit but missing last id; not saving state.");
                } else
                {
                    $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, [
                        'backfill_window' => $backfillWindow,
                        'window_start' => $windowStart->toDateString(),
                        'starting_after' => (string) $lastId,
                    ], [
                        'type' => 'json',
                        'group' => 'stripe',
                    ]);
                }
            } elseif ($hasMoreInWindow)
            {
                if ($lastId === null || $lastId === '')
                {
                    $this->warn("Team {$teamId}: unexpected: has_more but empty last id; not saving state.");
                } else
                {
                    $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, [
                        'backfill_window' => $backfillWindow,
                        'window_start' => $windowStart->toDateString(),
                        'starting_after' => (string) $lastId,
                    ], [
                        'type' => 'json',
                        'group' => 'stripe',
                    ]);
                }
            } elseif ($windowFinished)
            {
                $nextStart = $this->nextWindowStart($windowStart, $backfillWindow);
                if ($nextStart->gt($globalTo))
                {
                    $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, null, [
                        'type' => 'json',
                        'group' => 'stripe',
                    ]);
                } else
                {
                    $team->setSetting(self::SETTING_BACKFILL_ORDERED_STATE, [
                        'backfill_window' => $backfillWindow,
                        'window_start' => $nextStart->toDateString(),
                        'starting_after' => null,
                    ], [
                        'type' => 'json',
                        'group' => 'stripe',
                    ]);
                }
            }
        }

        $incomplete = $reachedRunLimit
            || $hasMoreInWindow
            || $this->getTeamOrderedBackfillState($team) !== null;

        $complete = ! $dryRun
            && ! $incomplete
            && $this->getTeamOrderedBackfillState($team) === null;

        return [$synced, $scanned, $lastId, $statusLine, $complete];
    }

    private function nextWindowStart(Carbon $start, string $backfillWindow): Carbon
    {
        if ($backfillWindow === 'week')
        {
            return $start->copy()->addDays(7)->startOfDay();
        }

        return $start->copy()->addMonthNoOverflow()->startOfMonth();
    }

    private function windowEndFor(Carbon $windowStart, string $backfillWindow): Carbon
    {
        if ($backfillWindow === 'week')
        {
            return $windowStart->copy()->addDays(7)->endOfDay();
        }

        return $windowStart->copy()->endOfMonth();
    }

    private function maxCarbon(Carbon $a, Carbon $b): Carbon
    {
        return $a->gt($b) ? $a->copy() : $b->copy();
    }

    /**
     * Last object on a Stripe list page; used for starting_after cursors.
     *
     * @param  array<int, mixed>  $data
     */
    private function lastStripeListItemId(array $data): ?string
    {
        if ($data === [])
        {
            return null;
        }
        $last = $data[array_key_last($data)];
        if (! is_array($last))
        {
            return null;
        }
        $id = trim((string) ($last['id'] ?? ''));

        return $id === '' ? null : $id;
    }

    /**
     * Sort invoice payloads for upsert so DB row ids (insert order) follow creation time
     * within a Stripe list slice. Stripe returns newest first; this reverses for writes only.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortInvoicePayloadsOldestFirst(array $rows): array
    {
        if ($rows === [])
        {
            return [];
        }
        usort(
            $rows,
            function (array $a, array $b): int {
                $ac = (int) ($a['created'] ?? 0);
                $bc = (int) ($b['created'] ?? 0);
                if ($ac !== $bc)
                {
                    return $ac <=> $bc;
                }

                $aid = (string) ($a['id'] ?? '');
                $bid = (string) ($b['id'] ?? '');

                return $aid <=> $bid;
            }
        );

        return array_values($rows);
    }

    private function parseYmdToStartOfDay(?string $date): ?Carbon
    {
        if (! is_string($date) || trim($date) === '')
        {
            return null;
        }
        try
        {
            return Carbon::parse($date)->startOfDay();
        } catch (\Throwable)
        {
            $this->warn("Ignoring invalid date value: {$date}");

            return null;
        }
    }

    private function parseYmdToEndOfDay(?string $date): ?Carbon
    {
        if (! is_string($date) || trim($date) === '')
        {
            return null;
        }
        try
        {
            return Carbon::parse($date)->endOfDay();
        } catch (\Throwable)
        {
            $this->warn("Ignoring invalid date value: {$date}");

            return null;
        }
    }

    /**
     * @param  array{gte?: int, lte?: int}  $createdFilter
     * @return array{0: int, 1: int, 2: string|null}
     */
    private function syncBackfillInvoicesForTeam(
        StripeClient $client,
        int $teamId,
        int $maxPerTeam,
        array $createdFilter,
        string $startingAfter,
        bool $dryRun,
    ): array {
        $params = [
            'limit' => 100,
            'expand' => [
                'data.customer',
                'data.subscription',
            ],
        ];

        if ($createdFilter !== [])
        {
            $params['created'] = $createdFilter;
        }
        if ($startingAfter !== '')
        {
            $params['starting_after'] = $startingAfter;
            $this->line("Team {$teamId}: resuming with cursor {$startingAfter}");
        }

        $synced = 0;
        $scanned = 0;
        $lastProcessedId = null;
        $pageStartingAfter = $startingAfter;
        $hasMore = true;
        $reachedRunLimit = false;

        while ($synced < $maxPerTeam && $hasMore)
        {
            if ($pageStartingAfter !== '')
            {
                $params['starting_after'] = $pageStartingAfter;
            } else
            {
                unset($params['starting_after']);
            }

            $page = $client->invoices->all($params)->toArray();
            $raw = is_array($page) ? ($page['data'] ?? []) : [];
            $hasMore = (bool) ($page['has_more'] ?? false);

            if (! is_array($raw) || $raw === [])
            {
                break;
            }

            $rawLastCursor = $this->lastStripeListItemId($raw);
            $data = array_values(array_filter($raw, 'is_array'));
            if ($data === [])
            {
                if (! $hasMore)
                {
                    break;
                } elseif ($rawLastCursor === null)
                {
                    $this->warn("Team {$teamId}: could not read cursor id for legacy backfill pagination, stopping.");
                    break;
                } else
                {
                    $pageStartingAfter = $rawLastCursor;
                }
                continue;
            }

            $remainingSlots = $maxPerTeam - $synced;
            if ($remainingSlots <= 0)
            {
                $reachedRunLimit = true;
                break;
            }

            $take = min($remainingSlots, count($data));
            $apiOrderSlice = array_slice($data, 0, $take);
            $toUpsert = $this->sortInvoicePayloadsOldestFirst($apiOrderSlice);
            $sliceLastCursor = $this->lastStripeListItemId($apiOrderSlice);

            foreach ($toUpsert as $row)
            {
                if ($synced >= $maxPerTeam)
                {
                    $reachedRunLimit = true;
                    break 2;
                }
                $scanned++;
                if (! $dryRun)
                {
                    $this->upsertInvoiceSyncRow($teamId, $row);
                }
                $lastProcessedId = (string) ($row['id'] ?? $lastProcessedId);
                $synced++;
            }

            if ($reachedRunLimit)
            {
                if ($sliceLastCursor !== null)
                {
                    $lastProcessedId = $sliceLastCursor;
                }
                break;
            }

            if ($take < count($data))
            {
                $reachedRunLimit = true;
                if ($sliceLastCursor !== null)
                {
                    $lastProcessedId = $sliceLastCursor;
                }
                break;
            }

            if (! $hasMore)
            {
                break;
            }
            if ($rawLastCursor === null)
            {
                $this->warn("Team {$teamId}: could not read last invoice id for legacy backfill pagination, stopping.");
                break;
            }
            $pageStartingAfter = $rawLastCursor;
        }

        return [$synced, $scanned, $lastProcessedId];
    }

    /**
     * @param  array{gte?: int, lte?: int}  $createdFilter
     * @return array{0: int, 1: int}
     */
    private function syncMutableInvoicesForTeam(
        StripeClient $client,
        int $teamId,
        int $maxPerTeam,
        array $createdFilter,
        bool $dryRun,
    ): array {
        $statuses = ['draft', 'open', 'uncollectible'];
        $synced = 0;
        $scanned = 0;

        foreach ($statuses as $status)
        {
            if ($synced >= $maxPerTeam)
            {
                break;
            }

            $remaining = $maxPerTeam - $synced;
            $params = [
                'limit' => min(100, $remaining),
                'status' => $status,
                'expand' => [
                    'data.customer',
                    'data.subscription',
                ],
            ];

            if ($createdFilter !== [])
            {
                $params['created'] = $createdFilter;
            }

            $pageStartingAfter = '';
            $hasMore = true;
            $reachedRunLimit = false;
            while ($synced < $maxPerTeam && $hasMore)
            {
                if ($pageStartingAfter !== '')
                {
                    $params['starting_after'] = $pageStartingAfter;
                } else
                {
                    unset($params['starting_after']);
                }

                $page = $client->invoices->all($params)->toArray();
                $raw = is_array($page) ? ($page['data'] ?? []) : [];
                $hasMore = (bool) ($page['has_more'] ?? false);

                if (! is_array($raw) || $raw === [])
                {
                    break;
                }

                $rawLastCursor = $this->lastStripeListItemId($raw);
                $data = array_values(array_filter($raw, 'is_array'));
                if ($data === [])
                {
                    if (! $hasMore)
                    {
                        break;
                    } elseif ($rawLastCursor === null)
                    {
                        $this->warn("Team {$teamId}: could not read cursor id for mutable sync pagination, stopping.");
                        break 2;
                    } else
                    {
                        $pageStartingAfter = $rawLastCursor;
                    }
                    continue;
                }

                $remainingSlots = $maxPerTeam - $synced;
                if ($remainingSlots <= 0)
                {
                    $reachedRunLimit = true;
                    break 2;
                }

                $take = min($remainingSlots, count($data));
                $apiOrderSlice = array_slice($data, 0, $take);
                $toUpsert = $this->sortInvoicePayloadsOldestFirst($apiOrderSlice);
                $sliceLastCursor = $this->lastStripeListItemId($apiOrderSlice);

                foreach ($toUpsert as $row)
                {
                    if ($synced >= $maxPerTeam)
                    {
                        $reachedRunLimit = true;
                        break 3;
                    }
                    $scanned++;
                    if (! $dryRun)
                    {
                        $this->upsertInvoiceSyncRow($teamId, $row);
                    }
                    $synced++;
                }

                if ($reachedRunLimit)
                {
                    break 2;
                }
                if ($take < count($data))
                {
                    break 2;
                }
                if (! $hasMore)
                {
                    break;
                }
                if ($rawLastCursor === null)
                {
                    $this->warn("Team {$teamId}: could not read last invoice id for mutable sync pagination, stopping.");
                    break 2;
                }
                $pageStartingAfter = $rawLastCursor;
            }
        }

        return [$synced, $scanned];
    }

    /**
     * @return array{gte?: int, lte?: int}
     */
    private function buildCreatedFilter(?string $from, ?string $to): array
    {
        $filter = [];

        if (is_string($from) && trim($from) !== '')
        {
            try
            {
                $fromTs = Carbon::parse($from)->startOfDay()->timestamp;
                $filter['gte'] = $fromTs;
            } catch (\Throwable)
            {
                $this->warn("Ignoring invalid --from value: {$from}");
            }
        }

        if (is_string($to) && trim($to) !== '')
        {
            try
            {
                $toTs = Carbon::parse($to)->endOfDay()->timestamp;
                $filter['lte'] = $toTs;
            } catch (\Throwable)
            {
                $this->warn("Ignoring invalid --to value: {$to}");
            }
        }

        return $filter;
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    private function upsertInvoiceSyncRow(int $teamId, array $invoicePayload): void
    {
        $externalId = trim((string) Arr::get($invoicePayload, 'id'));
        if ($externalId === '')
        {
            return;
        }

        $customerData = [];
        $customerField = Arr::get($invoicePayload, 'customer');
        if (is_array($customerField))
        {
            $customerData = $customerField;
        }

        $customerId = is_string($customerField)
            ? $customerField
            : Arr::get($customerData, 'id');

        $subscriptionField = Arr::get($invoicePayload, 'subscription');
        $subscriptionId = is_string($subscriptionField)
            ? $subscriptionField
            : Arr::get($subscriptionField, 'id');

        $discountLabels = [];
        $discounts = Arr::get($invoicePayload, 'discounts', []);
        if (is_array($discounts))
        {
            foreach ($discounts as $discount)
            {
                $name = Arr::get($discount, 'coupon.name')
                    ?? Arr::get($discount, 'coupon.id')
                    ?? Arr::get($discount, 'promotion_code.code');

                if (filled($name))
                {
                    $discountLabels[] = (string) $name;
                }
            }
        }

        InvoiceSync::updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'stripe',
                'external_id' => $externalId,
            ],
            [
                'stripe_subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'customer_email' => Arr::get($invoicePayload, 'customer_email')
                    ?? Arr::get($invoicePayload, 'customer_details.email')
                    ?? Arr::get($customerData, 'email'),
                'customer_name' => Arr::get($invoicePayload, 'customer_name')
                    ?? Arr::get($invoicePayload, 'customer_details.name')
                    ?? Arr::get($customerData, 'name'),
                'customer_description' => Arr::get($customerData, 'description'),
                'customer_tax_id' => Arr::get($invoicePayload, 'customer_tax_ids.0.value')
                    ?? Arr::get($invoicePayload, 'customer_details.tax_ids.0.value'),
                'customer_address_country' => strtoupper((string) (Arr::get($invoicePayload, 'customer_address.country')
                    ?? Arr::get($invoicePayload, 'customer_details.address.country')
                    ?? Arr::get($customerData, 'address.country'))) ?: null,
                'number' => Arr::get($invoicePayload, 'number'),
                'status' => Arr::get($invoicePayload, 'status'),
                'billing_reason' => Arr::get($invoicePayload, 'billing_reason'),
                'closed' => (bool) Arr::get($invoicePayload, 'closed', false),
                'currency' => strtolower((string) Arr::get($invoicePayload, 'currency', 'usd')),
                'amount_due' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_due_decimal'),
                    Arr::get($invoicePayload, 'amount_due'),
                ),
                'amount_paid' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_paid_decimal'),
                    Arr::get($invoicePayload, 'amount_paid'),
                ),
                'amount_remaining' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'amount_remaining_decimal'),
                    Arr::get($invoicePayload, 'amount_remaining'),
                ),
                'subtotal' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'subtotal_excluding_tax_decimal')
                    ?? Arr::get($invoicePayload, 'subtotal_decimal'),
                    Arr::get($invoicePayload, 'subtotal_excluding_tax')
                    ?? Arr::get($invoicePayload, 'subtotal'),
                ),
                'tax' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'tax_decimal'),
                    Arr::get($invoicePayload, 'tax'),
                ),
                'total' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'total_decimal'),
                    Arr::get($invoicePayload, 'total'),
                ),
                'total_discount_amount' => $this->normalizeAmount(
                    Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax_decimal')
                    ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount_decimal'),
                    Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax')
                    ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount'),
                ),
                'applied_coupons' => $discountLabels === [] ? null : implode(', ', $discountLabels),
                'invoice_created_at' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'created')),
                'invoice_due_date' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'due_date')),
                'paid' => (bool) Arr::get($invoicePayload, 'paid', false),
                'hosted_invoice_url' => Arr::get($invoicePayload, 'hosted_invoice_url'),
                'invoice_pdf' => Arr::get($invoicePayload, 'invoice_pdf'),
                'last_synced_at' => now(),
                'raw_payload' => $invoicePayload,
            ],
        );
    }

    private function normalizeAmount(?string $decimalAmount, mixed $integerAmount): ?float
    {
        if ($decimalAmount !== null)
        {
            return (float) $decimalAmount;
        }

        if (is_numeric($integerAmount))
        {
            return ((float) $integerAmount) / 100;
        }

        return null;
    }

    private function normalizeTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value))
        {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $value)->setTimezone(config('app.timezone'));
    }
}
