<?php

namespace App\Events\AuditExecution;

use App\Http\Resources\AuditConnectionVerificationResource;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a verification is marked as verified or discrepant.
 *
 * Enables real-time updates for multi-operator audit execution by notifying
 * other users when a connection verification is completed.
 */
class VerificationCompleted implements ShouldBroadcast
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
        // Reload relationships for the resource transformation
        $this->verification->load([
            'expectedConnection.sourcePort.device',
            'expectedConnection.destPort.device',
            'expectedConnection.sourceDevice',
            'expectedConnection.destDevice',
            'connection.sourcePort.device',
            'connection.destinationPort.device',
            'verifiedBy',
            'lockedBy',
        ]);

        return [
            'verification' => (new AuditConnectionVerificationResource($this->verification))->resolve(),
            'verified_by' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'audit_id' => $this->audit->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'verification.completed';
    }
}
