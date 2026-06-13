<?php

namespace Modules\Communication\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Communication\app\Models\Notification;
use Modules\Communication\app\Models\NotificationRecipient;
use Modules\Core\app\Http\Controllers\BaseApiController;

class CommunicationController extends BaseApiController
{
    public function myNotifications(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $userId = auth()->id();

        $query = NotificationRecipient::with(['notification'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $rows = $query->paginate($perPage);

        $rows->getCollection()->transform(function (NotificationRecipient $row) {
            $notification = $row->notification;
            return [
                'id' => $row->id,
                'notification_id' => $notification?->id,
                'title' => $notification?->title,
                'message' => $notification?->message,
                'type' => $notification?->type,
                'is_highlighted' => $row->is_highlighted,
                'is_read' => $row->read_at !== null,
                'read_at' => $row->read_at,
                'sent_at' => $notification?->sent_at,
                'created_at' => $row->created_at,
            ];
        });

        return $this->paginatedResponse($rows);
    }

    public function unreadCount(): JsonResponse
    {
        $count = NotificationRecipient::where('user_id', auth()->id())
            ->unread()
            ->count();

        return $this->success(['count' => $count]);
    }

    public function markRead(string $id): JsonResponse
    {
        $recipient = NotificationRecipient::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$recipient) {
            return $this->notFound('Notification not found');
        }

        $recipient->update([
            'read_at' => now(),
            'is_highlighted' => false,
        ]);

        return $this->success($recipient, 'Notification marked as read');
    }

    public function markAllRead(): JsonResponse
    {
        $updated = NotificationRecipient::where('user_id', auth()->id())
            ->unread()
            ->update([
                'read_at' => now(),
                'is_highlighted' => false,
            ]);

        return $this->success(['updated' => $updated], 'All notifications marked as read');
    }

    // === Notifications ===
    public function notifications(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $notifications = Notification::search($request->search)
            ->filter($request->only(['type', 'audience', 'status']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($notifications);
    }

    public function showNotification(string $id): JsonResponse
    {
        $notification = Notification::find($id);
        if (!$notification) return $this->notFound();
        return $this->success($notification);
    }

    public function storeNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:email,sms,push,in_app',
            'audience' => 'required|in:all,students,teachers,staff,parents,custom',
            'audience_ids' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
        ]);

        $validated['sent_by'] = auth()->id();
        $validated['status'] = $validated['scheduled_at'] ? 'scheduled' : 'draft';

        return $this->created(Notification::create($validated));
    }

    public function sendNotification(string $id): JsonResponse
    {
        $notification = Notification::find($id);
        if (!$notification) return $this->notFound();

        // TODO: Implement actual sending logic via queue
        $notification->update(['status' => 'sent', 'sent_at' => now()]);
        return $this->success($notification, 'Notification sent successfully');
    }

}
