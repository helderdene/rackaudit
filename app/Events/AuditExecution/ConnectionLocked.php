<?php

namespace App\Events\AuditExecution;

use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a connection is locked by an operator.
 *
 * Enables real-time updates for multi-operator audit execution by notifying
 * other users when a connection is locked for verification.
 */
class ConnectionLocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AuditConnectionVerification $verification,
        public Audit $audit,
        public User $user,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('audit.'.$this->audit->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'verification_id' => $this->verification->id,
            'audit_id' => $this->audit->id,
            'locked_by' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'locked_at' => $this->verification->locked_at?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'connection.locked';
    }
}
