<?php

namespace App\Http\Controllers;

use App\DataTables\SurveyDataTable;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\Survey;
use App\Support\SurveySchema;
use App\Support\TeamModuleAccess;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next)
        {
            TeamModuleAccess::abortUnless('surveys');

            return $next($request);
        });

        $this->authorizeResource(Survey::class, 'survey');
    }

    public function index(SurveyDataTable $dataTable)
    {
        return $dataTable->render('survey.index');
    }

    public function create()
    {
        return view('survey.form', [
            'fields' => $this->blankFields(),
        ]);
    }

    public function store(StoreSurveyRequest $request): RedirectResponse
    {
        $survey = Survey::create($this->attributes($request));

        return redirect()
            ->route('survey.show', $survey)
            ->with('success', __('Encuesta creada.'));
    }

    public function show(Survey $survey)
    {
        $survey->load('responses.user');

        return view('survey.show', [
            'survey' => $survey,
            'results' => $survey->fieldResults(),
        ]);
    }

    public function edit(Survey $survey)
    {
        return view('survey.form', [
            'survey' => $survey,
            'fields' => $survey->schema_json['fields'] ?? $this->blankFields(),
        ]);
    }

    public function update(UpdateSurveyRequest $request, Survey $survey): RedirectResponse
    {
        $survey->update($this->attributes($request));

        return redirect()
            ->route('survey.show', $survey)
            ->with('success', __('Encuesta actualizada.'));
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        $survey->delete();

        return redirect()
            ->route('survey.index')
            ->with('success', __('Encuesta eliminada.'));
    }

    public function export(Survey $survey): StreamedResponse
    {
        $this->authorize('view', $survey);
        $survey->load('responses.user');
        $fields = $survey->schema_json['fields'] ?? [];

        return response()->streamDownload(function () use ($survey, $fields)
        {
            $handle = fopen('php://output', 'w');
            $header = ['Nombre', 'Email'];

            foreach ($fields as $field)
            {
                $header[] = (string) ($field['label'] ?? $field['key']);
            }

            fputcsv($handle, $header);

            foreach ($survey->responses as $response)
            {
                $answers = $response->payload_json['answers'] ?? [];
                $row = [
                    $response->user?->name,
                    $response->user?->email,
                ];

                foreach ($fields as $field)
                {
                    $value = (string) ($answers[$field['key']] ?? '');
                    $label = $value;

                    foreach ($field['options'] ?? [] as $option)
                    {
                        if ((string) ($option['value'] ?? '') === $value)
                        {
                            $label = (string) ($option['label'] ?? $value);
                            break;
                        }
                    }

                    $row[] = $label;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'survey-'.$survey->id.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreSurveyRequest $request): array
    {
        $validated = $request->validated();

        return [
            'team_id' => (int) $request->user()->currentTeam->id,
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'code' => $validated['code'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'schema_json' => SurveySchema::fromForm($validated['title'], $validated['fields'] ?? []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blankFields(): array
    {
        return [[
            'key' => '',
            'label' => '',
            'help' => '',
            'required' => false,
            'anonymous' => false,
            'for_certificate' => false,
            'options' => [
                ['label' => '', 'value' => ''],
            ],
        ]];
    }
}
