<?php

namespace App\Events;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a rack is created, updated, or deleted.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * rack modifications within their datacenter scope.
 */
class RackChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Rack  $rack  The rack that was changed
     * @param  string  $action  The action that occurred: 'created', 'updated', 'deleted'
     * @param  User|null  $user  The user who made the change
     */
    public function __construct(
        public Rack $rack,
        public string $action,
        public ?User $user = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('datacenter.'.$this->getDatacenterId());
    }

    /**
     * Get the data to broadcast.
     *
     * Returns a minimal, serializable payload containing essential information
     * about the rack change for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'rack_id' => $this->rack->id,
            'room_id' => $this->getRoomId(),
            'action' => $this->action,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'rack.changed';
    }

    /**
     * Get the room ID for the rack.
     *
     * Traverses the relationship: Rack -> Row -> Room
     */
    protected function getRoomId(): ?int
    {
        $row = $this->rack->row;

        if ($row && $row->room) {
            return $row->room->id;
        }

        return null;
    }

    /**
     * Get the datacenter ID for the rack.
     *
     * Traverses the relationship hierarchy:
     * Rack -> Row -> Room -> Datacenter
     */
    protected function getDatacenterId(): int
    {
        $row = $this->rack->row;

        if ($row) {
            $room = $row->room;

            if ($room) {
                return $room->datacenter_id;
            }
        }

        // Fallback if no datacenter found
        return 0;
    }
}
