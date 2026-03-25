<?php

namespace App\Http\Controllers;

use App\DataTables\TeamFileDataTable;
use App\Enums\MultimediaVisibility;
use App\Http\Requests\TeamFile\StoreTeamFileRequest;
use App\Http\Requests\TeamFile\UpdateTeamFileRequest;
use App\Models\TeamFile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeamFileController extends Controller
{
    public function index(TeamFileDataTable $dataTable)
    {
        $this->authorize('viewAny', TeamFile::class);

        $visibilityOptions = MultimediaVisibility::cases();

        return $dataTable->render('team-file.index', compact('visibilityOptions'));
    }

    public function create()
    {
        $this->authorize('create', TeamFile::class);

        $visibilityOptions = MultimediaVisibility::cases();

        return view('team-file.form', [
            'data' => new TeamFile(['visibility' => MultimediaVisibility::PRIVATE]),
            'visibilityOptions' => $visibilityOptions,
        ]);
    }

    public function store(StoreTeamFileRequest $request)
    {
        $teamFile = new TeamFile;
        $teamFile->fill(Arr::except($request->validated(), ['file']));
        $teamFile->save();

        $teamFile->addMediaFromRequest('file')->toMediaCollection('file');

        return redirect()->route('team-file.index')->with('success', __('Team file saved successfully.'));
    }

    public function edit(TeamFile $team_file)
    {
        $this->authorize('update', $team_file);

        $visibilityOptions = MultimediaVisibility::cases();

        return view('team-file.form', [
            'data' => $team_file,
            'visibilityOptions' => $visibilityOptions,
        ]);
    }

    public function update(UpdateTeamFileRequest $request, TeamFile $team_file)
    {
        $team_file->fill(Arr::except($request->validated(), ['file']));
        $team_file->save();

        if ($request->hasFile('file'))
        {
            $team_file->clearMediaCollection('file');
            $team_file->addMediaFromRequest('file')->toMediaCollection('file');
        }

        return redirect()->route('team-file.index')->with('success', __('Team file updated successfully.'));
    }

    public function destroy(TeamFile $team_file)
    {
        $this->authorize('delete', $team_file);

        $team_file->delete();

        return redirect()->route('team-file.index')->with('success', __('Team file deleted successfully.'));
    }

    public function download(Request $request, TeamFile $team_file): BinaryFileResponse
    {
        $this->authorize('view', $team_file);

        $media = $team_file->getFirstMedia('file');
        abort_if(! $media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }
}
