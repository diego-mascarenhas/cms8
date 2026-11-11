<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyCertificate;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SurveyCertificateController extends Controller
{
    public function create(int $survey): View
    {
        $survey = $this->survey($survey);

        return view('survey.certificate', [
            'survey' => $survey,
        ]);
    }

    public function download(Request $request, int $survey, SurveyCertificate $certificates): Response
    {
        $survey = $this->survey($survey);
        $email = mb_strtolower(trim((string) $request->validate([
            'email' => ['required', 'email'],
        ])['email']));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $response = $user === null
            ? null
            : SurveyResponse::withoutGlobalScope('team')
                ->with('user')
                ->where('survey_id', $survey->id)
                ->where('user_id', $user->id)
                ->where('certified', true)
                ->first();

        if ($response === null)
        {
            return back()
                ->withInput()
                ->withErrors(['email' => __('No hay un certificado para ese email en esta conferencia.')]);
        }

        return $this->file($survey, $response, $certificates);
    }

    public function adminDownload(Survey $survey, SurveyResponse $response, SurveyCertificate $certificates): Response
    {
        $this->authorize('view', $survey);

        if ($response->survey_id !== $survey->id)
        {
            abort(404);
        }

        return $this->file($survey, $response, $certificates);
    }

    private function survey(int $id): Survey
    {
        return Survey::withoutGlobalScope('team')
            ->whereKey($id)
            ->whereNotNull('certificate_url')
            ->firstOrFail();
    }

    private function file(Survey $survey, SurveyResponse $response, SurveyCertificate $certificates): Response
    {
        try
        {
            $jpeg = $certificates->jpeg($survey, $response);
        } catch (RuntimeException $exception)
        {
            abort(404, $exception->getMessage());
        }

        $filename = 'certificado-'.str($response->user?->name)->slug().'.jpg';

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
