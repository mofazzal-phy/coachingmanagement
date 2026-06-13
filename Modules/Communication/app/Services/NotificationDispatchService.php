<?php

namespace Modules\Communication\app\Services;

use Modules\Communication\app\Models\Notification;
use Modules\Communication\app\Models\NotificationRecipient;

class NotificationDispatchService
{
    /**
     * Send an in-app notification to one or more users.
     *
     * @param  array<int, string>  $userIds
     */
    public function sendToUsers(array $userIds, string $title, string $message, array $options = []): ?Notification
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return null;
        }

        $notification = Notification::create([
            'title' => $title,
            'message' => $message,
            'type' => $options['type'] ?? 'in_app',
            'audience' => $options['audience'] ?? 'custom',
            'audience_ids' => $userIds,
            'sent_by' => $options['sent_by'] ?? auth()->id(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        foreach ($userIds as $userId) {
            NotificationRecipient::create([
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'is_highlighted' => $options['is_highlighted'] ?? true,
            ]);
        }

        return $notification;
    }

    /**
     * Send personalized notifications (one record per user).
     *
     * @param  array<int, array{user_id: string, title: string, message: string}>  $items
     */
    public function sendPersonalized(array $items, array $options = []): int
    {
        $sent = 0;

        foreach ($items as $item) {
            if (empty($item['user_id'])) {
                continue;
            }

            $this->sendToUsers(
                [$item['user_id']],
                $item['title'],
                $item['message'],
                array_merge($options, ['audience' => $options['audience'] ?? 'custom'])
            );
            $sent++;
        }

        return $sent;
    }
}
