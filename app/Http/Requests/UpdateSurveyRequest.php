<?php

namespace App\Http\Requests;

use App\Models\Survey;

class UpdateSurveyRequest extends StoreSurveyRequest
{
    public function authorize(): bool
    {
        $survey = $this->route('survey');

        return $survey instanceof Survey && ($this->user()?->can('update', $survey) ?? false);
    }
}
