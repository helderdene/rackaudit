<?php

namespace App\Events;

use App\Models\Device;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a device is placed, moved, removed, or has status changed.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * device changes within their datacenter scope.
 */
class DeviceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Device  $device  The device that was changed
     * @param  string  $action  The action that occurred: 'placed', 'moved', 'removed', 'status_changed'
     * @param  User|null  $user  The user who made the change
     */
    public function __construct(
        public Device $device,
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
     * about the device change for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->device->id,
            'rack_id' => $this->device->rack_id,
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
        return 'device.changed';
    }

    /**
     * Get the datacenter ID for the device.
     *
     * Traverses the relationship hierarchy:
     * Device -> Rack -> Row -> Room -> Datacenter
     */
    protected function getDatacenterId(): int
    {
        // Load the relationship chain if not already loaded
        $rack = $this->device->rack;

        if ($rack) {
            $row = $rack->row;

            if ($row) {
                $room = $row->room;

                if ($room) {
                    return $room->datacenter_id;
                }
            }
        }

        // Fallback if no datacenter found
        return 0;
    }
}
