<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Services\Mail\MailInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailInboxController extends Controller
{
    use ChecksTeamModule;

    public function __construct(
        private MailInboxService $mailInbox,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $validated = $request->validate([
            'folder' => 'nullable|string|max:32',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $folder = $validated['folder'] ?? 'inbox';
        $search = $validated['search'] ?? '';
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? MailInboxService::PER_PAGE);

        $paginator = $this->mailInbox->paginate($team, $folder, $search, $page);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn (Email $email) => $this->mailInbox->formatForList($email))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'folder_counts' => $this->mailInbox->folderCounts($team),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'label' => $this->mailInbox->paginationLabel($paginator),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        $email = Email::query()
            ->where('team_id', $team->id)
            ->whereKey($id)
            ->first();

        if (! $email)
        {
            return response()->json([
                'success' => false,
                'message' => __('Correo no encontrado.'),
            ], 404);
        }

        if (! $email->seen)
        {
            $email->update(['seen' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mailInbox->formatForList($email->fresh()),
        ]);
    }
}
