<?php

namespace App\Console\Commands;

use App\Services\LegacySurveyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportSurveysCommand extends Command
{
    protected $signature = 'import:surveys
                            {--team= : Team that receives the surveys}
                            {--grupo= : Legacy cms7 group}
                            {--empresa= : Legacy cms7 company}';

    protected $description = 'Import cms7 surveys and the answers already given by respondents';

    public function handle(LegacySurveyImporter $importer): int
    {
        $teamId = (int) $this->option('team');
        $grupo = (int) ($this->option('grupo') ?: config('app.cms_group'));
        $empresaId = (int) ($this->option('empresa') ?: config('app.survey_empresa_id'));

        if ($teamId < 1)
        {
            $teamName = (string) config('app.survey_team_name');
            $teamId = (int) DB::table('teams')->where('name', $teamName)->value('id');

            if ($teamId < 1)
            {
                $this->error('Team '.$teamName.' does not exist.');

                return self::FAILURE;
            }
        } elseif (! DB::table('teams')->where('id', $teamId)->exists())
        {
            $this->error('Team '.$teamId.' does not exist.');

            return self::FAILURE;
        }

        $legacy = DB::connection('mysql_legacy');
        $events = $this->legacyEvents($grupo, $empresaId);
        $companyContacts = $this->legacyContacts($grupo, $empresaId);

        if ($events->isEmpty() && $companyContacts->isEmpty())
        {
            $this->info('No surveys to import for group '.$grupo.' and company '.$empresaId.'.');

            return self::SUCCESS;
        }

        $result = ['surveys' => 0, 'responses' => 0];

        if ($events->isNotEmpty())
        {
            $result = $this->importEvents($legacy, $importer, $teamId, $events);
        }

        $users = $importer->importCompanyUsers($teamId, $companyContacts->all());

        $this->info('Imported '.$result['surveys'].' surveys, '.$result['responses'].' responses and '.$users.' contacts into team '.$teamId.'.');

        return self::SUCCESS;
    }

    protected function legacyEvents(int $grupo, int $empresaId): Collection
    {
        return DB::connection('mysql_legacy')
            ->table('eventos')
            ->where('grupo', $grupo)
            ->where('id_empresa', $empresaId)
            ->where('estado', '>', 0)
            ->orderBy('id')
            ->get();
    }

    protected function legacyContacts(int $grupo, int $empresaId): Collection
    {
        return DB::connection('mysql_legacy')
            ->table('contactos')
            ->where('grupo', $grupo)
            ->where('id_empresa', $empresaId)
            ->where('estado', '>', 0)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $events
     * @return array{surveys: int, responses: int}
     */
    private function importEvents($legacy, LegacySurveyImporter $importer, int $teamId, Collection $events): array
    {
        $eventIds = $events->pluck('id')->all();
        $questions = $legacy->table('eventos_preguntas')
            ->whereIn('id_evento', $eventIds)
            ->where('estado', '>', 0)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $questionIds = $questions->pluck('id')->all();
        $options = $questionIds === []
            ? collect()
            : $legacy->table('eventos_respuestas')
                ->whereIn('id_pregunta', $questionIds)
                ->where('estado', '>', 0)
                ->orderBy('orden')
                ->orderBy('id')
                ->get();

        $answers = $questionIds === []
            ? collect()
            : $legacy->table('eventos_rel_contactos_respuestas')
                ->whereIn('id_pregunta', $questionIds)
                ->get();

        $links = $legacy->table('eventos_rel_evento_contactos')
            ->whereIn('id_evento', $eventIds)
            ->get(['id_evento', 'id_contacto', 'certificado']);

        $mediaIds = $events->pluck('id_media')->filter()->unique()->values()->all();
        $thumbs = $mediaIds === []
            ? collect()
            : $legacy->table('media_thumbs')
                ->where('id_tipo', 28)
                ->whereIn('referencia', $mediaIds)
                ->get()
                ->keyBy('referencia');

        $base = rtrim((string) config('app.legacy_media_base'), '/');

        foreach ($events as $event)
        {
            $thumb = $thumbs->get($event->id_media ?? null);
            $event->certificate_url = $thumb
                ? $base.'/'.$event->grupo.'/'.$event->id_empresa.'/'.$thumb->archivo
                : null;
        }

        $contactIds = $answers->pluck('id_contacto')
            ->merge($links->pluck('id_contacto'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $contacts = $contactIds === []
            ? collect()
            : $legacy->table('eventos_contactos')->whereIn('id', $contactIds)->get();

        return $importer->importRows(
            $teamId,
            $events->all(),
            $questions->all(),
            $options->all(),
            $contacts->all(),
            $answers->all(),
            $links->all(),
        );
    }
}
