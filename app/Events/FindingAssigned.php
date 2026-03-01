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
 * Event broadcast when a finding is assigned to a user.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * finding assignment changes within their datacenter scope.
 */
class FindingAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Finding  $finding  The finding that was assigned
     * @param  User  $assignee  The user the finding was assigned to
     * @param  User  $assigner  The user who made the assignment
     */
    public function __construct(
        public Finding $finding,
        public User $assignee,
        public User $assigner,
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
     * about the finding assignment for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'finding_id' => $this->finding->id,
            'assignee' => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ],
            'assigner' => [
                'id' => $this->assigner->id,
                'name' => $this->assigner->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'finding.assigned';
    }

    /**
     * Get the datacenter ID for the finding.
     *
     * Traverses the relationship: Finding -> Audit -> Datacenter
     */
    protected function getDatacenterId(): int
    {
        $audit = $this->finding->audit;

        if ($audit) {
            return $audit->datacenter_id;
        }

        // Fallback if no datacenter found
        return 0;
    }
}
