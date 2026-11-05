<?php

namespace App\Http\Controllers;

use App\DataTables\MeetingTranscriptDataTable;
use App\Enums\MeetingTranscriptStatus;
use App\Http\Requests\StoreMeetingTranscriptRequest;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\MeetingTranscript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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
        return view('meeting.show', [
            'meeting' => $meeting,
        ]);
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
