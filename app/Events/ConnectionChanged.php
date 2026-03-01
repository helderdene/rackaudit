<?php

namespace App\Events;

use App\Models\Connection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Event dispatched when a connection is created, updated, or deleted.
 *
 * Triggers discrepancy detection for the affected connection to identify
 * any differences between expected and actual connections. The detection
 * is scoped to only the affected ports to prevent full datacenter rescans.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * connection changes within their datacenter scope.
 */
class ConnectionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Connection  $connection  The connection that was changed
     * @param  string  $action  The action that occurred: 'created', 'updated', or 'deleted'
     * @param  array<int>  $affectedPortIds  The port IDs affected by this change
     */
    public function __construct(
        public Connection $connection,
        public string $action,
        public array $affectedPortIds = []
    ) {
        // If no affected port IDs provided, derive them from the connection
        if (empty($this->affectedPortIds)) {
            $this->affectedPortIds = array_filter([
                $connection->source_port_id,
                $connection->destination_port_id,
            ]);
        }
    }

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
     * about the connection change for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $user = Auth::user();

        return [
            'connection_id' => $this->connection->id,
            'action' => $this->action,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
            ] : null,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'connection.changed';
    }

    /**
     * Get the datacenter ID for the connection.
     *
     * Traverses the relationship hierarchy:
     * Connection -> Port -> Device -> Rack -> Row -> Room -> Datacenter
     */
    protected function getDatacenterId(): int
    {
        // Load the relationship chain if not already loaded
        $sourcePort = $this->connection->sourcePort;

        if ($sourcePort && $sourcePort->device) {
            $device = $sourcePort->device;

            if ($device->rack) {
                $rack = $device->rack;

                if ($rack->row) {
                    $row = $rack->row;

                    if ($row->room) {
                        return $row->room->datacenter_id;
                    }
                }
            }
        }

        // Fallback: try destination port
        $destPort = $this->connection->destinationPort;

        if ($destPort && $destPort->device) {
            $device = $destPort->device;

            if ($device->rack) {
                $rack = $device->rack;

                if ($rack->row) {
                    $row = $rack->row;

                    if ($row->room) {
                        return $row->room->datacenter_id;
                    }
                }
            }
        }

        // If no datacenter found, return 0 (event won't be received but won't error)
        return 0;
    }
}
