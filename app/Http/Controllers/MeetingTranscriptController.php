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
        $contactId = $request->filled('contact_id') ? (int) $request->input('contact_id') : null;
        $linked = $transcripts->linkSpeaker($meeting, $speaker, $contactId);

        if (! $linked)
        {
            return redirect()
                ->route('meeting.show', $meeting)
                ->withErrors(['speaker' => 'Esa voz no está en la reunión.']);
        }

        $status = $contactId === null
            ? 'Se quitó el vínculo de la voz.'
            : 'La voz quedó vinculada en esta reunión. Persona 1 de otra grabación no es esta persona.';

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
