<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (! $userId)
        {
            return response()->json(['success' => false], 401);
        }

        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));

        $paginator = Notification::query()
            ->forRecipientUser($userId)
            ->with(['type', 'contact'])
            ->latest()
            ->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (Notification $notification) => $this->formatNotification($notification));

        return response()->json([
            'success' => true,
            'data' => $items,
            'unread_count' => Notification::query()->forRecipientUser($userId)->unread()->count(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeRecipient($request, $notification);

        if (! $notification->is_read)
        {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatNotification($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (! $userId)
        {
            return response()->json(['success' => false], 401);
        }

        $notifications = Notification::query()
            ->forRecipientUser($userId)
            ->unread()
            ->get();

        foreach ($notifications as $notification)
        {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'marked_count' => $notifications->count(),
            'unread_count' => 0,
        ]);
    }

    public function dismiss(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeRecipient($request, $notification);
        $notification->delete();

        $userId = (int) $request->user()->id;

        return response()->json([
            'success' => true,
            'unread_count' => Notification::query()->forRecipientUser($userId)->unread()->count(),
        ]);
    }

    private function authorizeRecipient(Request $request, Notification $notification): void
    {
        $contactUserId = $notification->contact?->user_id;

        if ($contactUserId === null || (int) $contactUserId !== (int) $request->user()->id)
        {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'subject' => $notification->subject,
            'message' => strip_tags((string) $notification->message),
            'is_read' => (bool) $notification->is_read,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_formatted' => $notification->formatted_created_date,
            'type' => $notification->type ? [
                'id' => $notification->type->id,
                'name' => $notification->type->name,
            ] : null,
        ];
    }
}
