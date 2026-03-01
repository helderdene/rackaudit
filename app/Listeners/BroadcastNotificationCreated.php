<?php

namespace App\Listeners;

use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Listener that broadcasts a real-time notification event when
 * a database notification is successfully sent.
 *
 * This enables the NotificationBell component to receive instant
 * updates without polling when new notifications are created.
 */
class BroadcastNotificationCreated
{
    /**
     * Handle the NotificationSent event.
     *
     * Only dispatches the broadcast event for database channel notifications
     * to avoid duplicate broadcasts for mail and other channels.
     */
    public function handle(NotificationSent $event): void
    {
        // Only broadcast for database channel notifications
        if ($event->channel !== 'database') {
            return;
        }

        // Only broadcast for User notifiables
        if (! $event->notifiable instanceof User) {
            return;
        }

        $userId = $event->notifiable->id;

        // Get notification data from the notification
        $notificationData = $event->notification->toArray($event->notifiable);

        // Get the database notification ID from the response
        $notificationId = $event->response;

        // Build the notification payload for broadcast
        $payload = array_merge($notificationData, [
            'id' => $notificationId,
            'read' => false,
            'created_at' => now()->toIso8601String(),
            'relative_time' => 'just now',
        ]);

        // Dispatch the broadcast event for real-time delivery
        NotificationCreated::dispatch($userId, $payload);
    }
}
