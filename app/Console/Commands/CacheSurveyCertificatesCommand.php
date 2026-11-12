<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Services\SurveyCertificate;
use Illuminate\Console\Command;
use RuntimeException;

class CacheSurveyCertificatesCommand extends Command
{
    protected $signature = 'surveys:cache-certificates {--team= : Limit to one team id}';

    protected $description = 'Download survey certificate templates into local storage';

    public function handle(SurveyCertificate $certificates): int
    {
        $query = Survey::withoutGlobalScope('team')
            ->whereNotNull('certificate_url')
            ->where('certificate_url', '!=', '');

        if ($this->option('team'))
        {
            $query->where('team_id', (int) $this->option('team'));
        }

        $surveys = $query->orderBy('id')->get();
        $cached = 0;
        $failed = 0;

        foreach ($surveys as $survey)
        {
            try
            {
                $certificates->cacheTemplate($survey);
                $cached++;
                $this->line('Cached survey '.$survey->id);
            } catch (RuntimeException $exception)
            {
                $failed++;
                $this->warn('Survey '.$survey->id.': '.$exception->getMessage());
            }
        }

        $this->info('Cached '.$cached.' templates'.($failed ? ', '.$failed.' failed' : '').'.');

        return $failed > 0 && $cached === 0 ? self::FAILURE : self::SUCCESS;
    }
}
