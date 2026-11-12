<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TeamCertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $team = $request->attributes->get('team');
        $email = $this->email($request);
        $user = $this->user($email);

        if ($user === null)
        {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $certificates = SurveyResponse::withoutGlobalScope('team')
            ->with(['survey' => function ($query)
            {
                $query->withoutGlobalScope('team');
            }])
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('certified', true)
            ->get()
            ->filter(fn (SurveyResponse $response): bool => $response->survey !== null && filled($response->survey->certificate_url))
            ->map(fn (SurveyResponse $response): array => [
                'survey_id' => $response->survey_id,
                'title' => $response->survey->title,
                'name' => $user->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $certificates,
        ]);
    }

    public function download(Request $request, int $survey, SurveyCertificate $certificates): Response
    {
        $team = $request->attributes->get('team');
        $email = $this->email($request);
        $user = $this->user($email);
        $surveyModel = Survey::withoutGlobalScope('team')
            ->whereKey($survey)
            ->where('team_id', $team->id)
            ->whereNotNull('certificate_url')
            ->first();

        $response = $user === null || $surveyModel === null
            ? null
            : SurveyResponse::withoutGlobalScope('team')
                ->with('user')
                ->where('survey_id', $surveyModel->id)
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->where('certified', true)
                ->first();

        if ($response === null)
        {
            return response()->json([
                'success' => false,
                'message' => 'No hay un certificado para ese email en esta conferencia.',
            ], 404);
        }

        try
        {
            $jpeg = $certificates->jpeg($surveyModel, $response);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        $filename = $certificates->downloadFilename($surveyModel, $response);

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function email(Request $request): string
    {
        return mb_strtolower(trim((string) $request->validate([
            'email' => ['required', 'email'],
        ])['email']));
    }

    private function user(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }
}
