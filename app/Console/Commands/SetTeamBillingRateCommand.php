<?php

namespace App\Console\Commands;

use App\Enums\TeamBillingProduct;
use App\Models\Team;
use App\Models\TeamBillingRate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SetTeamBillingRateCommand extends Command
{
    protected $signature = 'billing:set-team-rate
                            {team_id : Team id, or 0 for the platform default}
                            {product : tokens_multiplier, whatsapp_send, or mailer_send}
                            {amount : New rate (multiplier or EUR per unit)}
                            {--from= : When the new rate starts (ISO datetime). Default: now}
                            {--currency= : ISO currency for money products}';

    protected $description = 'Set a team billing rate and keep the previous one for past usage';

    public function handle(): int
    {
        $teamId = (int) $this->argument('team_id');
        $product = TeamBillingProduct::tryFrom((string) $this->argument('product'));
        if ($product === null)
        {
            $this->error('Unknown product. Use tokens_multiplier, whatsapp_send, or mailer_send.');

            return self::FAILURE;
        }

        if ($teamId > 0 && ! Team::query()->find($teamId))
        {
            $this->error('Team '.$teamId.' was not found.');

            return self::FAILURE;
        }

        $fromOption = $this->option('from');
        $from = is_string($fromOption) && trim($fromOption) !== ''
            ? Carbon::parse($fromOption)
            : now();
        $currency = is_string($this->option('currency')) && trim((string) $this->option('currency')) !== ''
            ? strtoupper(trim((string) $this->option('currency')))
            : null;

        $rate = TeamBillingRate::setAmount(
            $teamId > 0 ? $teamId : null,
            $product,
            (float) $this->argument('amount'),
            $from,
            $currency,
        );

        $this->info(sprintf(
            'Rate %s for team %s is %s from %s.',
            $product->value,
            $teamId > 0 ? (string) $teamId : 'default',
            $rate->amount,
            $rate->effective_from?->toIso8601String(),
        ));

        return self::SUCCESS;
    }
}
