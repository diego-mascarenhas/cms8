<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeetingTranscriptStatus;
use App\Http\Controllers\Controller;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Models\Team;
use App\Services\MeetingCalendarSync;
use App\Services\MeetingTranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamMeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $team = $this->team($request);
        $meetings = MeetingTranscript::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $meetings->map(fn (MeetingTranscript $meeting): array => $this->payload($meeting))->values(),
        ]);
    }

    public function store(Request $request, MeetingCalendarSync $calendar): JsonResponse
    {
        $team = $this->team($request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'language' => ['nullable', 'string', 'size:2'],
            'external_ref' => ['nullable', 'string', 'max:160'],
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,mp4,webm,ogg,mpeg', 'max:512000'],
        ], [
            'title.required' => 'Indicá un título para la reunión.',
            'audio.required' => 'Subí el audio de la reunión.',
            'audio.mimes' => 'El audio tiene que ser mp3, wav, m4a, mp4, webm u ogg.',
            'audio.max' => 'El audio no puede pesar más de 500 MB.',
        ]);

        $audio = $request->file('audio');
        $path = $audio->store('meetings/'.$team->id, 'local');
        $meeting = MeetingTranscript::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $team->user_id,
            'title' => $validated['title'],
            'external_ref' => $validated['external_ref'] ?? null,
            'original_filename' => $audio->getClientOriginalName(),
            'audio_path' => $path,
            'language' => isset($validated['language']) ? strtolower($validated['language']) : 'es',
            'status' => MeetingTranscriptStatus::Pending,
        ]);

        $calendar->sync($meeting);
        TranscribeMeetingTranscript::dispatch($meeting->id)->afterResponse();

        return response()->json(['data' => $this->payload($meeting)], 201);
    }

    public function show(Request $request, int $meeting): JsonResponse
    {
        $row = $this->find($request, $meeting);
        if ($row === null)
        {
            return response()->json(['message' => 'La reunión no existe.'], 404);
        }

        return response()->json(['data' => $this->payload($row)]);
    }

    public function linkSpeaker(Request $request, MeetingTranscriptService $transcripts, int $meeting): JsonResponse
    {
        $team = $this->team($request);
        $row = $this->find($request, $meeting);
        if ($row === null)
        {
            return response()->json(['message' => 'La reunión no existe.'], 404);
        }

        $validated = $request->validate([
            'speaker' => ['required', 'string', 'max:40'],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(function ($query) use ($team)
                {
                    $query->where('team_id', $team->id)->whereNull('deleted_at');
                }),
            ],
        ], [
            'speaker.required' => 'Elegí la voz que querés vincular.',
            'contact_id.exists' => 'Esa persona no está en el equipo.',
        ]);

        $contactId = array_key_exists('contact_id', $validated) && $validated['contact_id'] !== null
            ? (int) $validated['contact_id']
            : null;
        $linked = $transcripts->linkSpeaker($row, $validated['speaker'], $contactId);
        if (! $linked)
        {
            return response()->json(['message' => 'Esa voz no está en la reunión.'], 422);
        }

        return response()->json(['data' => $this->payload($row->fresh())]);
    }

    private function team(Request $request): Team
    {
        $team = $request->attributes->get('team');
        if (! $team instanceof Team)
        {
            abort(401, 'Token not provided');
        }

        return $team;
    }

    private function find(Request $request, int $meeting): ?MeetingTranscript
    {
        return MeetingTranscript::withoutGlobalScope('team')
            ->where('team_id', $this->team($request)->id)
            ->find($meeting);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MeetingTranscript $meeting): array
    {
        $contactIds = collect($meeting->turns ?? [])->pluck('contact_id')->filter()->unique()->values();
        $contacts = Contact::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereIn('id', $contactIds)
            ->get()
            ->keyBy('id');

        $turns = collect($meeting->turns ?? [])->map(function (array $turn) use ($contacts): array
        {
            $contact = isset($turn['contact_id']) ? $contacts->get($turn['contact_id']) : null;
            $name = $contact ? trim($contact->name.' '.($contact->surname ?? '')) : '';

            return [
                'speaker' => $turn['speaker'] ?? '',
                'label' => $turn['label'] ?? 'Persona',
                'contact_id' => $contact?->id,
                'contact_name' => $name !== '' ? $name : null,
                'text' => $turn['text'] ?? '',
                'start_seconds' => $turn['start_seconds'] ?? 0,
                'end_seconds' => $turn['end_seconds'] ?? 0,
            ];
        })->values();

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'external_ref' => $meeting->external_ref,
            'status' => $meeting->status->value,
            'language' => $meeting->language,
            'transcript' => $meeting->transcript,
            'turns' => $turns,
            'summary' => $meeting->summary,
            'error' => $meeting->error,
        ];
    }
}
