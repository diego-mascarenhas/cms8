<?php

namespace App\Http\Controllers;

use App\DataTables\TeamFileDataTable;
use App\Enums\MultimediaVisibility;
use App\Http\Requests\TeamFile\StoreTeamFileRequest;
use App\Http\Requests\TeamFile\UpdateTeamFileRequest;
use App\Models\TeamFile;
use App\Models\TeamFileHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeamFileController extends Controller
{
    public function index(TeamFileDataTable $dataTable)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('viewAny', TeamFile::class);

        $visibilityOptions = MultimediaVisibility::cases();

        return $dataTable->render('team-file.index', compact('visibilityOptions'));
    }

    public function create()
    {
        $this->assertCurrentTeamHasTeamFilesModule();
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
        $this->syncShareHash($teamFile);
        $teamFile->save();

        $teamFile->addMediaFromRequest('file')->toMediaCollection('file');
        $this->recordHistory($teamFile, 'uploaded', $teamFile->getFirstMedia('file')?->file_name);

        return redirect()->route('team-file.index')->with('success', __('Team file saved successfully.'));
    }

    public function edit(TeamFile $team_file)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('update', $team_file);

        $visibilityOptions = MultimediaVisibility::cases();

        return view('team-file.form', [
            'data' => $team_file,
            'visibilityOptions' => $visibilityOptions,
        ]);
    }

    public function show(TeamFile $team_file)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('view', $team_file);

        $histories = $team_file->histories()->with('user')->get();

        return view('team-file.show', [
            'data' => $team_file,
            'histories' => $histories,
            'publicShareUrl' => $team_file->share_hash ? route('team-file.shared', $team_file->share_hash) : null,
        ]);
    }

    public function update(UpdateTeamFileRequest $request, TeamFile $team_file)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $existingFileName = $team_file->getFirstMedia('file')?->file_name;

        $team_file->fill(Arr::except($request->validated(), ['file']));
        $this->syncShareHash($team_file);
        $team_file->save();

        if ($request->hasFile('file'))
        {
            $archivedMediaId = $this->archiveCurrentFile($team_file);
            $team_file->clearMediaCollection('file');
            $team_file->addMediaFromRequest('file')->toMediaCollection('file');
            $this->recordHistory($team_file, 'replaced', $team_file->getFirstMedia('file')?->file_name, $archivedMediaId);
        } else
        {
            $this->recordHistory($team_file, 'updated', $existingFileName);
        }

        return redirect()->route('team-file.index')->with('success', __('Team file updated successfully.'));
    }

    public function destroy(TeamFile $team_file)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('delete', $team_file);
        $existingFileName = $team_file->getFirstMedia('file')?->file_name;
        $archivedMediaId = $this->archiveCurrentFile($team_file);

        $team_file->delete();
        $this->recordHistory($team_file, 'deleted', $existingFileName, $archivedMediaId);

        return redirect()->route('team-file.index')->with('success', __('Team file deleted successfully.'));
    }

    public function restoreVersion(TeamFile $team_file, TeamFileHistory $history)
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('update', $team_file);
        abort_if($history->team_file_id !== $team_file->id || ! $history->archived_media_id, 404);

        /** @var Media|null $archived */
        $archived = $team_file->media()
            ->whereKey($history->archived_media_id)
            ->where('collection_name', 'file_versions')
            ->first();
        abort_if(! $archived, 404);

        $currentArchivedMediaId = $this->archiveCurrentFile($team_file);
        $team_file->clearMediaCollection('file');
        $restored = $archived->copy($team_file, 'file');

        $this->recordHistory($team_file, 'restored', $restored?->file_name, $currentArchivedMediaId);

        return redirect()->route('team-file.show', $team_file)->with('success', __('Team file version restored successfully.'));
    }

    public function download(Request $request, TeamFile $team_file): BinaryFileResponse
    {
        $this->assertCurrentTeamHasTeamFilesModule();
        $this->authorize('view', $team_file);

        $media = $team_file->getFirstMedia('file');
        abort_if(! $media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function shared(string $hash): BinaryFileResponse
    {
        $teamFile = TeamFile::query()
            ->withoutGlobalScopes()
            ->where('share_hash', $hash)
            ->where('visibility', MultimediaVisibility::PUBLIC->value)
            ->firstOrFail();

        $media = $teamFile->getFirstMedia('file');
        abort_if(! $media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    private function assertCurrentTeamHasTeamFilesModule(): void
    {
        abort_unless(auth()->user()?->currentTeam?->hasModule('team_files'), 403);
    }

    private function archiveCurrentFile(TeamFile $teamFile): ?int
    {
        /** @var Media|null $currentMedia */
        $currentMedia = $teamFile->getFirstMedia('file');
        if (! $currentMedia)
        {
            return null;
        }

        $archivedMedia = $currentMedia->copy($teamFile, 'file_versions');

        return $archivedMedia?->id;
    }

    private function recordHistory(TeamFile $teamFile, string $action, ?string $fileName, ?int $archivedMediaId = null): void
    {
        TeamFileHistory::query()->create([
            'team_file_id' => $teamFile->id,
            'team_id' => $teamFile->team_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'file_name' => $fileName,
            'archived_media_id' => $archivedMediaId,
        ]);
    }

    private function syncShareHash(TeamFile $teamFile): void
    {
        $visibilityValue = $teamFile->visibility instanceof MultimediaVisibility
            ? $teamFile->visibility->value
            : (int) $teamFile->visibility;

        if ($visibilityValue !== MultimediaVisibility::PUBLIC->value)
        {
            $teamFile->share_hash = null;

            return;
        }

        if ($teamFile->share_hash)
        {
            return;
        }

        do
        {
            $hash = Str::random(40);
        } while (
            TeamFile::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->where('share_hash', $hash)
                ->exists()
        );

        $teamFile->share_hash = $hash;
    }
}
