<?php

namespace App\Events;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a Finding is resolved.
 *
 * Used to trigger auto-resolution of linked discrepancies.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * finding resolutions within their datacenter scope.
 */
class FindingResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Finding $finding,
        public User $resolvedBy,
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
     * about the finding resolution for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'finding_id' => $this->finding->id,
            'title' => $this->finding->title,
            'resolver' => [
                'id' => $this->resolvedBy->id,
                'name' => $this->resolvedBy->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'finding.resolved';
    }

    /**
     * Get the datacenter ID for the finding.
     *
     * Traverses the relationship: Finding -> Audit -> Datacenter
     */
    protected function getDatacenterId(): int
    {
        // Load the audit relationship if not already loaded
        $audit = $this->finding->audit;

        if ($audit) {
            return $audit->datacenter_id;
        }

        // Fallback if no datacenter found
        return 0;
    }
}
