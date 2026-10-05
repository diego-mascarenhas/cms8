<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportMailerAudienceCsvRequest;
use App\Services\MailerAudienceCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
                ...$importer->importOptions((int) $team->id),
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

        $path = $request->file('file')->getRealPath();
        $choices = $this->duplicateChoices($request);
        $countryId = (int) $request->input('country_id', MailerAudienceCsvImportService::DEFAULT_COUNTRY_ID);
        $categoryIds = $this->categoryIds($request);

        if ($request->boolean('preview'))
        {
            $result = $importer->preview($path, $team, $choices, $countryId);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        $result = $importer->import(
            $path,
            $team,
            (int) $request->user()->id,
            $choices,
            $countryId,
            $categoryIds,
        );

        if ($result['duplicates'] !== [])
        {
            return response()->json([
                'success' => false,
                'message' => __('Elegí qué teléfono conservar en los emails repetidos.'),
                'data' => array_merge($result, [
                    'contacts_count' => $importer->audienceCount((int) $team->id),
                ]),
            ], 422);
        }

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

    /**
     * @return array<string, string>
     */
    private function duplicateChoices(Request $request): array
    {
        $raw = $request->input('choices');
        if (! is_string($raw) || trim($raw) === '')
        {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded))
        {
            return [];
        }

        $choices = [];
        foreach ($decoded as $email => $phone)
        {
            if (! is_string($email) || ! is_string($phone))
            {
                continue;
            }

            $choices[Str::lower(trim($email))] = trim($phone);
        }

        return $choices;
    }

    /**
     * @return list<int>
     */
    private function categoryIds(Request $request): array
    {
        $raw = $request->input('category_ids');
        if (! is_string($raw) || trim($raw) === '')
        {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded))
        {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded)));
    }
}
