<?php

namespace App\Http\Controllers;

use App\DataTables\MeetingTranscriptDataTable;
use App\Enums\MeetingTranscriptStatus;
use App\Http\Requests\LinkMeetingSpeakerRequest;
use App\Http\Requests\StoreMeetingTranscriptRequest;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Services\MeetingSummaryService;
use App\Services\MeetingTranscriptService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class MeetingTranscriptController extends Controller
{
    public function index(MeetingTranscriptDataTable $dataTable)
    {
        return $dataTable->render('meeting.index');
    }

    public function create(): View
    {
        return view('meeting.create');
    }

    public function store(StoreMeetingTranscriptRequest $request): RedirectResponse
    {
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
        ]);

        TranscribeMeetingTranscript::dispatch($meeting->id)->afterResponse();

        return redirect()
            ->route('meeting.show', $meeting)
            ->with('status', 'El audio quedó cargado. Se están separando las voces.');
    }

    public function show(MeetingTranscript $meeting): View
    {
        $contactIds = collect($meeting->turns ?? [])
            ->pluck('contact_id')
            ->filter()
            ->unique()
            ->values();

        return view('meeting.show', [
            'meeting' => $meeting,
            'contacts' => Contact::query()->whereIn('id', $contactIds)->get()->keyBy('id'),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
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
        try
        {
            $summaries->summarize($meeting);
        } catch (RuntimeException $exception)
        {
            return redirect()
                ->route('meeting.show', $meeting)
                ->withErrors(['summary' => $exception->getMessage()]);
        }

        return redirect()
            ->route('meeting.show', $meeting)
            ->with('status', 'El resumen quedó guardado.');
    }

    public function destroy(MeetingTranscript $meeting): RedirectResponse
    {
        if ($meeting->audio_path !== '')
        {
            Storage::disk('local')->delete($meeting->audio_path);
        }

        $meeting->delete();

        return redirect()
            ->route('meeting.index')
            ->with('status', 'La reunión se eliminó.');
    }
}
