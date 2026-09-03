<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportMailerAudienceCsvRequest;
use App\Services\MailerAudienceCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailerAudienceImportController extends Controller
{
    use ChecksTeamModule;

    public function show(Request $request, MailerAudienceCsvImportService $importer): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if ($denied = $this->ensureTeamModule($team, 'contacts'))
        {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'required_columns' => MailerAudienceCsvImportService::REQUIRED_COLUMNS,
                'optional_columns' => MailerAudienceCsvImportService::OPTIONAL_COLUMNS,
                'sample_csv' => $importer->templateContents(),
                'contacts_count' => $importer->audienceCount((int) $team->id),
                'subscribers_limit' => $team->getContactLimit(),
            ],
        ]);
    }

    public function store(ImportMailerAudienceCsvRequest $request, MailerAudienceCsvImportService $importer): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if ($denied = $this->ensureTeamModule($team, 'contacts'))
        {
            return $denied;
        }

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            $team,
            (int) $request->user()->id,
        );

        $imported = $result['created'] + $result['updated'];

        return response()->json([
            'success' => $imported > 0,
            'message' => $imported > 0
                ? __(':created creados, :updated actualizados, :skipped salteados.', [
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                ])
                : __('No se importó ningún contacto.'),
            'data' => array_merge($result, [
                'contacts_count' => $importer->audienceCount((int) $team->id),
            ]),
        ], $imported > 0 ? 200 : 422);
    }
}
