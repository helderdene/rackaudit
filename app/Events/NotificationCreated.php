<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event broadcast when a new notification is created and stored in the database.
 *
 * Implements ShouldBroadcastNow for immediate delivery without queuing,
 * ensuring users receive real-time notification updates instantly.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param  int  $userId  The ID of the user receiving the notification
     * @param  array<string, mixed>  $notification  The notification data payload
     */
    public function __construct(
        public int $userId,
        public array $notification
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts to a user-specific private channel to ensure
     * only the intended recipient receives the notification.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    /**
     * Get the data to broadcast.
     *
     * Returns the notification payload for the frontend to process
     * and display in the NotificationBell component.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
