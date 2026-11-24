<?php

namespace App\Http\Controllers;

use App\DataTables\MeetingTranscriptDataTable;
use App\Enums\MeetingTranscriptStatus;
use App\Http\Requests\LinkMeetingSpeakerRequest;
use App\Http\Requests\StoreMeetingTranscriptRequest;
use App\Http\Requests\UpdateMeetingTranscriptRequest;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Services\MeetingCalendarSync;
use App\Services\MeetingSummaryService;
use App\Services\MeetingTranscriptService;
use App\Support\MeetingOpinionSpread;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class MeetingTranscriptController extends Controller
{
    public function index(MeetingTranscriptDataTable $dataTable)
    {
        $this->ensureMeetingsModule();

        return $dataTable->render('meeting.index');
    }

    public function create(): View
    {
        $this->ensureMeetingsModule();

        return view('meeting.create');
    }

    public function store(StoreMeetingTranscriptRequest $request, MeetingCalendarSync $calendar): RedirectResponse
    {
        $this->ensureMeetingsModule();

        $team = $request->user()?->currentTeam;
        $audio = $request->file('audio');
        $path = $audio->store('meetings/'.$team->id, 'local');

        $meeting = MeetingTranscript::query()->create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'title' => $request->string('title')->toString(),
            'original_filename' => $audio->getClientOriginalName(),
            'audio_path' => $path,
            'language' => $request->filled('language') ? strtolower($request->string('language')->toString()) : 'es',
            'status' => MeetingTranscriptStatus::Pending,
            'public_token' => Str::random(40),
        ]);

        $calendar->sync($meeting);
        TranscribeMeetingTranscript::dispatch($meeting->id)->afterResponse();

        return redirect()
            ->route('meeting.show', $meeting)
            ->with('status', 'El audio quedó cargado. Se están separando las voces.');
    }

    public function show(MeetingTranscript $meeting): View
    {
        $this->ensureMeetingsModule();

        return view('meeting.show', [
            'meeting' => $meeting,
            'contacts' => $this->contactsFor($meeting),
        ]);
    }

    public function edit(MeetingTranscript $meeting): View
    {
        $this->ensureMeetingsModule();

        return view('meeting.edit', ['meeting' => $meeting]);
    }

    public function update(UpdateMeetingTranscriptRequest $request, MeetingTranscript $meeting, MeetingCalendarSync $calendar): RedirectResponse
    {
        $this->ensureMeetingsModule();

        $meeting->forceFill([
            'title' => $request->string('title')->toString(),
        ])->save();
        $calendar->sync($meeting);

        return redirect()
            ->route('meeting.show', $meeting)
            ->with('status', 'El nombre quedó actualizado.');
    }

    public function summary(MeetingTranscript $meeting): View
    {
        $this->ensureMeetingsModule();

        $token = $meeting->publicToken();

        return view('meeting.summary', [
            'meeting' => $meeting,
            'publicUrl' => route('meeting.public', $token),
        ]);
    }

    public function publicShow(string $token): View
    {
        $meeting = MeetingTranscript::withoutGlobalScope('team')
            ->where('public_token', $token)
            ->firstOrFail();

        return view('meeting.public', [
            'meeting' => $meeting,
            'contacts' => $this->contactsFor($meeting),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $this->ensureMeetingsModule();

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2)
        {
            return response()->json(['results' => []]);
        }

        $query = Contact::query();
        SearchNormalizer::applyContactNavbarConditions($query, $term);

        $results = $query
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function (Contact $contact): array
            {
                return [
                    'id' => $contact->id,
                    'text' => trim($contact->name.' '.($contact->surname ?? '')),
                    'avatar' => $contact->avatarUrl(40),
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function linkSpeaker(LinkMeetingSpeakerRequest $request, MeetingTranscript $meeting, MeetingTranscriptService $transcripts): RedirectResponse
    {
        $this->ensureMeetingsModule();

        $speaker = $request->string('speaker')->toString();
        $participant = $this->participant($meeting, $request->string('participant')->toString());
        if ($request->filled('participant') && $participant === null)
        {
            return redirect()
                ->route('meeting.show', $meeting)
                ->withErrors(['speaker' => 'Esa persona no participa de este grupo o iniciativa.']);
        }

        $keptAName = false;
        if ($request->boolean('clear'))
        {
            $linked = $transcripts->linkSpeaker($meeting, $speaker, null);
        } elseif ($participant !== null)
        {
            $contact = $this->contactForParticipant($meeting, $participant);
            $keptAName = true;
            $linked = $transcripts->linkSpeaker(
                $meeting,
                $speaker,
                $contact?->id,
                $participant['name'],
                $participant['email'],
            );
        } else
        {
            $contactId = $request->filled('contact_id') ? (int) $request->input('contact_id') : null;
            if ($contactId === null)
            {
                return redirect()
                    ->route('meeting.show', $meeting)
                    ->withErrors(['speaker' => 'Elegí una persona.']);
            }

            $keptAName = true;
            $linked = $transcripts->linkSpeaker($meeting, $speaker, $contactId);
        }

        if (! $linked)
        {
            return redirect()
                ->route('meeting.show', $meeting)
                ->withErrors(['speaker' => 'Esa voz no está en la reunión.']);
        }

        $status = $keptAName ? 'La voz quedó vinculada en esta reunión. Persona 1 de otra grabación no es esta persona.' : 'Se quitó el vínculo de la voz.';

        return redirect()
            ->route('meeting.show', $meeting)
            ->with('status', $status);
    }

    public function summarize(MeetingTranscript $meeting, MeetingSummaryService $summaries): RedirectResponse
    {
        $this->ensureMeetingsModule();

        try
        {
            $summaries->summarize($meeting);
        } catch (RuntimeException $exception)
        {
            return redirect()
                ->route('meeting.summary.show', $meeting)
                ->withErrors(['summary' => $exception->getMessage()]);
        }

        return redirect()
            ->route('meeting.summary.show', $meeting)
            ->with('status', 'El resumen quedó guardado.');
    }

    public function toggleObjective(Request $request, MeetingTranscript $meeting): RedirectResponse
    {
        $this->ensureMeetingsModule();

        $summary = is_array($meeting->summary) ? $meeting->summary : [];
        $objectives = MeetingOpinionSpread::objectives($summary);
        $index = (int) $request->input('index');
        if (! array_key_exists($index, $objectives))
        {
            abort(404);
        }

        $objectives[$index]['marked'] = ! $objectives[$index]['marked'];
        $summary['objectives'] = $objectives;
        $meeting->forceFill(['summary' => $summary])->save();

        return redirect()->route('meeting.summary.show', $meeting);
    }

    public function destroy(MeetingTranscript $meeting, MeetingCalendarSync $calendar): RedirectResponse
    {
        $this->ensureMeetingsModule();

        if ($meeting->audio_path !== '')
        {
            Storage::disk('local')->delete($meeting->audio_path);
        }

        $calendar->forget($meeting);
        $meeting->delete();

        return redirect()
            ->route('meeting.index')
            ->with('status', 'La reunión se eliminó.');
    }

    /**
     * @return array{key: string, name: string, email: string}|null
     */
    private function participant(MeetingTranscript $meeting, string $key): ?array
    {
        $key = trim($key);
        if ($key === '')
        {
            return null;
        }

        foreach (is_array($meeting->participants) ? $meeting->participants : [] as $row)
        {
            if (! is_array($row) || (string) ($row['key'] ?? '') !== $key)
            {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '')
            {
                return null;
            }

            return [
                'key' => $key,
                'name' => $name,
                'email' => trim((string) ($row['email'] ?? '')),
            ];
        }

        return null;
    }

    /**
     * @param  array{key: string, name: string, email: string}  $participant
     */
    private function contactForParticipant(MeetingTranscript $meeting, array $participant): ?Contact
    {
        $email = mb_strtolower($participant['email']);
        if ($email === '')
        {
            return null;
        }

        return Contact::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function ensureMeetingsModule(): void
    {
        if (! auth()->user()?->currentTeam?->hasModule('meetings'))
        {
            abort(403);
        }
    }

    /**
     * @return Collection<int, Contact>
     */
    private function contactsFor(MeetingTranscript $meeting): Collection
    {
        $contactIds = collect($meeting->turns ?? [])
            ->pluck('contact_id')
            ->filter()
            ->unique()
            ->values();

        return Contact::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereIn('id', $contactIds)
            ->get()
            ->keyBy('id');
    }
}
