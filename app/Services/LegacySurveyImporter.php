<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\SurveySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LegacySurveyImporter
{
    /**
     * Turn cms7 event rows into surveys and one response per contact.
     *
     * @param  list<array<string, mixed>|object>  $events
     * @param  list<array<string, mixed>|object>  $questions
     * @param  list<array<string, mixed>|object>  $options
     * @param  list<array<string, mixed>|object>  $contacts
     * @param  list<array<string, mixed>|object>  $answers
     * @param  list<array<string, mixed>|object>  $links
     * @return array{surveys: int, responses: int}
     */
    public function importRows(int $teamId, array $events, array $questions, array $options, array $contacts, array $answers, array $links = []): array
    {
        $questionsByEvent = [];
        $questionEvent = [];

        foreach ($questions as $question)
        {
            $question = (array) $question;
            $eventId = (int) ($question['id_evento'] ?? 0);
            $questionId = (int) ($question['id'] ?? 0);

            if ($eventId === 0 || $questionId === 0 || (int) ($question['estado'] ?? 1) <= 0)
            {
                continue;
            }

            $questionsByEvent[$eventId][] = $question;
            $questionEvent[$questionId] = $eventId;
        }

        foreach ($questionsByEvent as $eventId => $eventQuestions)
        {
            usort($eventQuestions, function (array $left, array $right)
            {
                return ((int) ($left['orden'] ?? 0) <=> (int) ($right['orden'] ?? 0))
                    ?: ((int) $left['id'] <=> (int) $right['id']);
            });
            $questionsByEvent[$eventId] = $eventQuestions;
        }

        $optionsByQuestion = [];

        foreach ($options as $option)
        {
            $option = (array) $option;
            $questionId = (int) ($option['id_pregunta'] ?? 0);

            if ($questionId === 0 || (int) ($option['estado'] ?? 1) <= 0)
            {
                continue;
            }

            $optionsByQuestion[$questionId][] = $option;
        }

        foreach ($optionsByQuestion as $questionId => $questionOptions)
        {
            usort($questionOptions, function (array $left, array $right)
            {
                return ((int) ($left['orden'] ?? 0) <=> (int) ($right['orden'] ?? 0))
                    ?: ((int) $left['id'] <=> (int) $right['id']);
            });
            $optionsByQuestion[$questionId] = $questionOptions;
        }

        $contactsById = [];
        $emails = [];

        foreach ($contacts as $contact)
        {
            $contact = (array) $contact;
            $contactsById[(int) ($contact['id'] ?? 0)] = $contact;
            $email = $this->blankToNull($contact['email'] ?? null);

            if ($email !== null)
            {
                $emails[] = mb_strtolower($email);
            }
        }

        $usersByEmail = $emails === []
            ? collect()
            : User::query()
                ->whereRaw('LOWER(email) IN ('.implode(',', array_fill(0, count(array_unique($emails)), '?')).')', array_values(array_unique($emails)))
                ->get()
                ->keyBy(fn (User $user): string => mb_strtolower((string) $user->email));

        $answersByEvent = [];

        foreach ($answers as $answer)
        {
            $answer = (array) $answer;
            $questionId = (int) ($answer['id_pregunta'] ?? 0);
            $contactId = (int) ($answer['id_contacto'] ?? 0);
            $optionId = (int) ($answer['id_respuesta'] ?? 0);
            $eventId = $questionEvent[$questionId] ?? 0;

            if ($eventId === 0 || $contactId === 0 || $optionId === 0)
            {
                continue;
            }

            $answersByEvent[$eventId][$contactId]['q'.$questionId] = (string) $optionId;
        }

        $certifiedByEvent = [];

        foreach ($links as $link)
        {
            $link = (array) $link;
            $eventId = (int) ($link['id_evento'] ?? 0);
            $contactId = (int) ($link['id_contacto'] ?? 0);

            if ($eventId === 0 || $contactId === 0)
            {
                continue;
            }

            $certifiedByEvent[$eventId][$contactId] = (int) ($link['certificado'] ?? 0) === 1;
        }

        $surveyCount = 0;
        $responseCount = 0;

        foreach ($events as $event)
        {
            $event = (array) $event;
            $eventId = (int) ($event['id'] ?? 0);

            if ($eventId === 0 || (int) ($event['estado'] ?? 1) <= 0)
            {
                continue;
            }

            $title = trim((string) ($event['titulo'] ?? ''));
            $title = $title !== '' ? $title : 'Encuesta';
            $code = $this->blankToNull($event['codigo'] ?? null);
            $schema = SurveySchema::fromLegacyEvent(
                $title,
                $questionsByEvent[$eventId] ?? [],
                $optionsByQuestion,
            );

            $survey = Survey::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $teamId,
                    'title' => $title,
                    'code' => $code,
                ],
                [
                    'subtitle' => $this->blankToNull($event['subtitulo'] ?? null),
                    'expires_at' => $this->blankToNull($event['fecha_vencimiento'] ?? null),
                    'is_active' => (int) ($event['estado'] ?? 0) === 2,
                    'schema_json' => $schema,
                    'certificate_url' => $this->blankToNull($event['certificate_url'] ?? null),
                ],
            );

            $surveyCount++;
            $keptIds = [];
            $contactIds = array_values(array_unique(array_merge(
                array_keys($answersByEvent[$eventId] ?? []),
                array_keys($certifiedByEvent[$eventId] ?? []),
            )));

            foreach ($contactIds as $contactId)
            {
                $contact = $contactsById[$contactId] ?? [];
                $user = $this->userForTeam($teamId, $contact, $usersByEmail);

                if ($user === null)
                {
                    continue;
                }

                $response = SurveyResponse::withoutGlobalScope('team')->updateOrCreate(
                    [
                        'survey_id' => $survey->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'team_id' => $teamId,
                        'certified' => $certifiedByEvent[$eventId][$contactId] ?? false,
                        'payload_json' => ['answers' => $answersByEvent[$eventId][$contactId] ?? []],
                    ],
                );

                $keptIds[] = $response->id;
                $responseCount++;
            }

            $stale = SurveyResponse::withoutGlobalScope('team')
                ->where('survey_id', $survey->id);

            if ($keptIds === [])
            {
                $stale->delete();
            } else
            {
                $stale->whereNotIn('id', $keptIds)->delete();
            }
        }

        return [
            'surveys' => $surveyCount,
            'responses' => $responseCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>|object>  $contacts
     */
    public function importCompanyUsers(int $teamId, array $contacts): int
    {
        $emails = [];

        foreach ($contacts as $contact)
        {
            $contact = (array) $contact;
            $email = $this->blankToNull($contact['email'] ?? null);

            if ($email !== null)
            {
                $emails[] = mb_strtolower($email);
            }
        }

        $emails = array_values(array_unique($emails));
        $usersByEmail = $emails === []
            ? collect()
            : User::query()
                ->whereRaw('LOWER(email) IN ('.implode(',', array_fill(0, count($emails), '?')).')', $emails)
                ->get()
                ->keyBy(fn (User $user): string => mb_strtolower((string) $user->email));

        $count = 0;
        $seen = [];

        foreach ($contacts as $contact)
        {
            $contact = (array) $contact;

            if ((int) ($contact['estado'] ?? 1) <= 0)
            {
                continue;
            }

            $user = $this->userForTeam($teamId, $contact, $usersByEmail);

            if ($user === null || isset($seen[$user->id]))
            {
                continue;
            }

            $seen[$user->id] = true;
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  Collection<string, User>  $usersByEmail
     */
    private function userForTeam(int $teamId, array $contact, Collection $usersByEmail): ?User
    {
        $email = $this->blankToNull($contact['email'] ?? null);

        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return null;
        }

        $key = mb_strtolower($email);
        $user = $usersByEmail->get($key);

        if (! $user instanceof User)
        {
            $name = trim(((string) ($contact['nombre'] ?? '')).' '.((string) ($contact['apellido'] ?? '')));
            $user = User::create([
                'name' => mb_substr($name !== '' ? $name : $key, 0, 255),
                'email' => $key,
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->forceFill(['current_team_id' => $teamId])->save();
            $usersByEmail->put($key, $user);
        }

        $user->teams()->syncWithoutDetaching([
            $teamId => ['role' => 'client'],
        ]);

        if ($user->current_team_id === null)
        {
            $user->forceFill(['current_team_id' => $teamId])->save();
        }

        return $user;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null)
        {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
