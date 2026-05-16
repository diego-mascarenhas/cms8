<?php

namespace App\Http\Controllers;

use App\DataTables\NotificationDataTable;
use App\Jobs\SendNotificationJob;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\UserDailyPerformanceInsight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications
     */
    public function index(NotificationDataTable $dataTable)
    {
        return $dataTable->render('notification.index');
    }

    /**
     * Show the form for creating a new notification
     */
    public function create()
    {
        $contacts = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->orderBy('name')->get();
        $types = NotificationType::getActiveOptions();

        return view('notification.create', compact('contacts', 'types'));
    }

    /**
     * Store a newly created notification
     */
    public function store(Request $request)
    {
        $request->validate([
            'type_id' => 'required|exists:notification_types,id',
            'contact_id' => 'required|exists:contacts,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'reference' => 'nullable|string|max:255',
            'send_immediately' => 'boolean',
        ]);

        $notification = Notification::create([
            'team_id' => auth()->user()->currentTeam->id,
            'type_id' => $request->type_id,
            'contact_id' => $request->contact_id,
            'user_id' => auth()->id(),
            'reference' => $request->reference,
            'subject' => $request->subject,
            'message' => $request->message,
            'metadata' => $request->metadata ? json_decode($request->metadata, true) : null,
        ]);

        if ($request->boolean('send_immediately'))
        {
            $this->sendNotification($notification);
        }

        return redirect()->route('notification.index')
            ->with('success', 'Notificación creada correctamente'.
                   ($request->boolean('send_immediately') ? ' y enviada' : ''));
    }

    /**
     * Display the specified notification
     */
    public function show(Notification $notification)
    {
        $notification->load(['contact', 'type', 'user', 'team']);

        if ($this->userIsNotificationRecipient($notification) && ! $notification->is_read)
        {
            $notification->markAsRead();
            $notification->refresh();
        }

        $dailyPerformanceInsight = $this->resolveDailyPerformanceInsightForNotification($notification);

        return view('notification.show', compact('notification', 'dailyPerformanceInsight'));
    }

    private function resolveDailyPerformanceInsightForNotification(Notification $notification): ?UserDailyPerformanceInsight
    {
        if (! $notification->isDailyPerformanceInsight())
        {
            return null;
        }

        $insightId = (int) ($notification->metadata['performance_insight_id'] ?? $notification->reference ?? 0);

        if ($insightId <= 0)
        {
            return null;
        }

        return UserDailyPerformanceInsight::query()
            ->where('team_id', $notification->team_id)
            ->find($insightId);
    }

    /**
     * Show the form for editing the specified notification
     */
    public function edit(Notification $notification)
    {
        if ($notification->is_sent)
        {
            return redirect()->route('notification.show', $notification)
                ->with('error', 'No se puede editar una notificación que ya ha sido enviada');
        }

        $contacts = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->orderBy('name')->get();
        $types = NotificationType::getActiveOptions();

        return view('notification.edit', compact('notification', 'contacts', 'types'));
    }

    /**
     * Update the specified notification
     */
    public function update(Request $request, Notification $notification)
    {
        if ($notification->is_sent)
        {
            return redirect()->route('notification.show', $notification)
                ->with('error', 'No se puede editar una notificación que ya ha sido enviada');
        }

        $request->validate([
            'type_id' => 'required|exists:notification_types,id',
            'contact_id' => 'required|exists:contacts,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'reference' => 'nullable|string|max:255',
        ]);

        $notification->update([
            'type_id' => $request->type_id,
            'contact_id' => $request->contact_id,
            'reference' => $request->reference,
            'subject' => $request->subject,
            'message' => $request->message,
            'metadata' => $request->metadata ? json_decode($request->metadata, true) : null,
        ]);

        return redirect()->route('notification.show', $notification)
            ->with('success', 'Notificación actualizada correctamente');
    }

    /**
     * Remove the specified notification
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();

        return redirect()->route('notification.index')
            ->with('success', 'Notificación eliminada correctamente');
    }

    /**
     * Send a notification via email
     */
    public function send(Notification $notification)
    {
        try
        {
            $this->sendNotification($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada correctamente',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend a notification
     */
    public function resend(Notification $notification)
    {
        try
        {
            $this->sendNotification($notification, true);

            return response()->json([
                'success' => true,
                'message' => 'Notificación reenviada correctamente',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar la notificación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark notification as read (navbar / recipient).
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorizeNotificationRecipient($notification);

        if (! $notification->is_read)
        {
            $notification->markAsRead();
            $notification->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => __('app.navbar_notification_marked_read'),
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at?->toIso8601String(),
            'read_at_formatted' => $notification->formatted_read_at,
        ]);
    }

    /**
     * Mark notification as unread (navbar / recipient).
     */
    public function markAsUnread(Notification $notification): JsonResponse
    {
        $this->authorizeNotificationRecipient($notification);

        if ($notification->is_read)
        {
            $notification->markAsUnread();
            $notification->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => __('app.navbar_notification_marked_unread'),
            'is_read' => $notification->is_read,
            'created_at_formatted' => $notification->created_at->isoFormat('D MMM YYYY, HH:mm'),
        ]);
    }

    /**
     * Dismiss a notification from the navbar dropdown (recipient only).
     */
    public function dismiss(Notification $notification): JsonResponse
    {
        $this->authorizeNotificationRecipient($notification);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => __('app.navbar_notification_dismissed'),
        ]);
    }

    /**
     * Mark all unread notifications for the authenticated recipient as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $userId = auth()->id();
        if (! $userId)
        {
            abort(401);
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
            'message' => __('app.navbar_notification_marked_all_read'),
            'marked_count' => $notifications->count(),
            'unread_count' => 0,
            'read_at_formatted' => now()->isoFormat('D MMM YYYY, HH:mm'),
        ]);
    }

    private function userIsNotificationRecipient(Notification $notification): bool
    {
        $contactUserId = $notification->contact?->user_id;

        return $contactUserId !== null && (int) $contactUserId === (int) auth()->id();
    }

    private function authorizeNotificationRecipient(Notification $notification): void
    {
        if (! $this->userIsNotificationRecipient($notification))
        {
            abort(403);
        }
    }

    /**
     * Get notification template by type
     */
    public function getTemplate(Request $request)
    {
        $request->validate([
            'type_id' => 'required|exists:notification_types,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'reference' => 'nullable|string',
        ]);

        $type = NotificationType::findOrFail($request->type_id);
        $contact = $request->contact_id ? Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->find($request->contact_id) : null;

        // Prepare placeholder data
        $placeholders = [
            'contact_name' => $contact ? $contact->name : '{contact_name}',
            'team_name' => auth()->user()->currentTeam->name,
            'sender_name' => auth()->user()->name,
            'reference' => $request->reference ?? '{reference}',
            'custom_message' => '{custom_message}',
        ];

        $subject = $type->replacePlaceholders($placeholders, 'template_subject');
        $message = $type->replacePlaceholders($placeholders, 'template_body');

        return response()->json([
            'success' => true,
            'subject' => $subject,
            'message' => $message,
            'is_customizable' => $type->is_customizable,
        ]);
    }

    /**
     * Bulk send notifications
     */
    public function bulkSend(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $notifications = Notification::whereIn('id', $request->notification_ids)
            ->unsent()
            ->get();

        $sentCount = 0;
        $errors = [];

        foreach ($notifications as $notification)
        {
            try
            {
                $this->sendNotification($notification);
                $sentCount++;
            } catch (\Exception $e)
            {
                $errors[] = "Notificación ID {$notification->id}: ".$e->getMessage();
            }
        }

        $message = "{$sentCount} notificaciones enviadas correctamente";
        if (! empty($errors))
        {
            $message .= '. Errores: '.implode(', ', $errors);
        }

        return response()->json([
            'success' => empty($errors),
            'message' => $message,
            'sent_count' => $sentCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Quick send notification to collaborator (legacy method support)
     */
    public function quickSend(Request $request, $contactId)
    {
        $request->validate([
            'message' => 'required|string',
            'type_id' => 'nullable|exists:notification_types,id',
            'subject' => 'nullable|string|max:255',
        ]);

        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])->findOrFail($contactId);

        // Use General Message type if not specified
        $typeId = $request->type_id ?? NotificationType::where('name', 'General Message')->first()?->id ?? 3;

        $notification = Notification::create([
            'team_id' => auth()->user()->currentTeam->id,
            'type_id' => $typeId,
            'contact_id' => $contactId,
            'user_id' => auth()->id(),
            'subject' => $request->subject ?? 'Mensaje de '.auth()->user()->currentTeam->name,
            'message' => $request->message,
        ]);

        try
        {
            $this->sendNotification($notification);

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada correctamente',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send notification via email using queue
     */
    private function sendNotification(Notification $notification, bool $isResend = false)
    {
        $notification->load(['contact', 'user', 'team']);

        if (! $notification->contact->email)
        {
            throw new \Exception('El contacto no tiene email configurado');
        }

        try
        {
            // Dispatch the job to the notifications queue
            SendNotificationJob::dispatch($notification, $isResend);

            // For immediate feedback, we can still mark as "queued" or keep the original behavior
            // The actual sent status will be updated when the job processes
        } catch (\Exception $e)
        {
            throw new \Exception('Error al encolar la notificación: '.$e->getMessage());
        }
    }
}
