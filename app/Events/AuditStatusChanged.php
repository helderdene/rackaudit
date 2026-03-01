<?php

namespace App\Events;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when an audit's status transitions.
 *
 * Implements ShouldBroadcast to notify connected users in real-time about
 * audit status changes within their datacenter scope.
 */
class AuditStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Audit  $audit  The audit that had its status changed
     * @param  AuditStatus  $oldStatus  The previous status
     * @param  AuditStatus  $newStatus  The new status
     * @param  User  $user  The user who changed the status
     */
    public function __construct(
        public Audit $audit,
        public AuditStatus $oldStatus,
        public AuditStatus $newStatus,
        public User $user,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('datacenter.'.$this->audit->datacenter_id);
    }

    /**
     * Get the data to broadcast.
     *
     * Returns a minimal, serializable payload containing essential information
     * about the audit status change for real-time UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'audit_id' => $this->audit->id,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'audit.status_changed';
    }
}
