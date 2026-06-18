<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CMS media library. Files are stored as WordPress-style `attachment` posts so they reuse the
 * bidirectional WordPress sync (upload pushes to WP, WP uploads are pulled in).
 */
class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);

        $media = Post::query()
            ->where('post_type', 'attachment')
            ->orderByDesc('post_date')
            ->paginate(40);

        $wordpressSyncEnabled = WordPressContentSyncService::make($request->user()->currentTeam)->isConfigured();

        return view('cms.media.index', compact('media', 'wordpressSyncEnabled'));
    }

    /**
     * JSON list for the media picker modal (featured image + editor insert).
     */
    public function list(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $query = Post::query()->where('post_type', 'attachment');

        if ($request->filled('search'))
        {
            $query->where('post_title', 'like', '%'.$request->string('search').'%');
        }

        $media = $query->orderByDesc('post_date')->paginate(30);

        $media->getCollection()->transform(fn (Post $post) => $this->transform($post));

        return response()->json($media);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $team = $request->user()->currentTeam;
        $file = $validated['file'];
        $original = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug(pathinfo($original, PATHINFO_FILENAME)).'-'.Str::random(6).($extension ? '.'.$extension : '');
        $path = $file->storeAs('cms-media/'.$team->id, $filename, 'public');

        $post = new Post;
        $post->post_type = 'attachment';
        $post->post_status = 'inherit';
        $post->post_title = pathinfo($original, PATHINFO_FILENAME);
        $post->post_name = Str::slug(pathinfo($original, PATHINFO_FILENAME));
        $post->post_mime_type = $file->getClientMimeType();
        $post->guid = Storage::disk('public')->url($path);
        $post->save();
        $post->setMeta('_humano_file_path', $path);

        return response()->json(['success' => true, 'media' => $this->transform($post->fresh('meta'))], 201);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        if ($post->post_type !== 'attachment')
        {
            return response()->json(['success' => false], 422);
        }

        // Remove from WordPress when linked and sync is enabled.
        if ($post->wp_id)
        {
            $service = WordPressContentSyncService::make($request->user()->currentTeam);
            if ($service->isEnabled())
            {
                (new \App\Services\WordPressService($request->user()->currentTeam))->deleteMedia((int) $post->wp_id);
            }
        }

        $localPath = $post->getMeta('_humano_file_path');
        if ($localPath && Storage::disk('public')->exists($localPath))
        {
            Storage::disk('public')->delete($localPath);
        }

        $post->forceDelete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Post $post): array
    {
        $thumb = $post->getMeta('_humano_thumb_url') ?: $post->guid;

        return [
            'id' => $post->id,
            'title' => $post->post_title,
            'url' => $post->guid,
            'thumb' => $thumb,
            'mime' => $post->post_mime_type,
            'is_image' => str_starts_with((string) $post->post_mime_type, 'image/'),
            'wp_id' => $post->wp_id,
        ];
    }
}
